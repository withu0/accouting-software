<?php

namespace App\Services;

use App\Enums\BankImportRowStatus;
use App\Enums\BankImportStatus;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\BankImport;
use App\Models\BankImportRow;
use App\Models\Company;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankImportService
{
    public function __construct(
        private readonly BankCsvParser $parser,
        private readonly JournalService $journalService,
        private readonly DescriptionRuleMatcher $ruleMatcher,
        private readonly ConsumptionTaxService $consumptionTaxService,
    ) {}

    /**
     * @return array{
     *     import: BankImport,
     *     total: int,
     *     new: int,
     *     duplicates: int,
     *     out_of_period: int,
     *     resumed?: bool,
     * }
     */
    public function import(Company $company, UploadedFile $file): array
    {
        $fiscalYear = $company->activeFiscalYear();
        if ($fiscalYear === null) {
            throw new InvalidArgumentException('No active fiscal year configured for this company.');
        }

        $parsedRows = $this->parser->parse($file->get());

        $newCount = 0;
        $duplicateCount = 0;
        $outOfPeriodCount = 0;
        $rowsToCreate = [];
        $resumableImportIds = [];

        foreach ($parsedRows as $row) {
            $transactionDate = $row['transaction_date'];

            if ($transactionDate->lt($fiscalYear->start_date) || $transactionDate->gt($fiscalYear->end_date)) {
                $outOfPeriodCount++;

                continue;
            }

            $existingRow = BankImportRow::where('company_id', $company->id)
                ->where('row_hash', $row['row_hash'])
                ->first();

            if ($existingRow !== null) {
                $duplicateCount++;

                if ($existingRow->status === BankImportRowStatus::Pending) {
                    $resumableImportIds[$existingRow->bank_import_id] = ($resumableImportIds[$existingRow->bank_import_id] ?? 0) + 1;
                }

                continue;
            }

            $rowsToCreate[] = [
                'row' => $row,
            ];
            $newCount++;
        }

        if ($newCount === 0) {
            $importableRowCount = count($parsedRows) - $outOfPeriodCount;

            if ($importableRowCount > 0 && $duplicateCount === $importableRowCount) {
                $resumableImportId = $this->resolveResumableImportId($resumableImportIds);

                if ($resumableImportId !== null) {
                    return [
                        'import' => BankImport::findOrFail($resumableImportId),
                        'total' => count($parsedRows),
                        'new' => 0,
                        'duplicates' => $duplicateCount,
                        'out_of_period' => $outOfPeriodCount,
                        'resumed' => true,
                    ];
                }
            }

            throw new InvalidArgumentException($this->buildImportRejectionMessage(
                $fiscalYear->start_date->format('Y-m-d'),
                $fiscalYear->end_date->format('Y-m-d'),
                $duplicateCount,
                $outOfPeriodCount,
            ));
        }

        $bankImport = BankImport::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYear->id,
            'original_filename' => $file->getClientOriginalName(),
            'status' => BankImportStatus::Pending,
            'row_count' => $newCount,
            'imported_at' => now(),
        ]);

        foreach ($rowsToCreate as $item) {
            $row = $item['row'];
            $suggestedAccount = $row['withdrawal_amount'] > 0
                ? $this->ruleMatcher->suggestAccount($company, $row['description'])
                : null;

            BankImportRow::create([
                'bank_import_id' => $bankImport->id,
                'company_id' => $company->id,
                'row_hash' => $row['row_hash'],
                'transaction_date' => $row['transaction_date'],
                'description' => $row['description'],
                'deposit_amount' => $row['deposit_amount'],
                'withdrawal_amount' => $row['withdrawal_amount'],
                'balance' => $row['balance'],
                'suggested_account_id' => $suggestedAccount?->id,
                'status' => BankImportRowStatus::Pending,
            ]);
        }

        return [
            'import' => $bankImport,
            'total' => count($parsedRows),
            'new' => $newCount,
            'duplicates' => $duplicateCount,
            'out_of_period' => $outOfPeriodCount,
        ];
    }

    private function buildImportRejectionMessage(
        string $fiscalYearStart,
        string $fiscalYearEnd,
        int $duplicateCount,
        int $outOfPeriodCount,
    ): string {
        if ($duplicateCount > 0 && $outOfPeriodCount === 0) {
            return 'すべての取引は既に取込済みです。同じCSVを再度アップロードすることはできません。';
        }

        if ($outOfPeriodCount > 0 && $duplicateCount === 0) {
            return "CSVの取引日が会計期間（{$fiscalYearStart} 〜 {$fiscalYearEnd}）外のため取込できません。会計期間設定を確認してください。";
        }

        if ($duplicateCount > 0 && $outOfPeriodCount > 0) {
            return "取込可能な取引がありません。{$duplicateCount}件は既に取込済み、{$outOfPeriodCount}件は会計期間（{$fiscalYearStart} 〜 {$fiscalYearEnd}）外です。";
        }

        return '取込可能な取引がありません。';
    }

    /**
     * @param  array<int, int>  $resumableImportIds
     */
    private function resolveResumableImportId(array $resumableImportIds): ?int
    {
        if ($resumableImportIds === []) {
            return null;
        }

        arsort($resumableImportIds);

        return array_key_first($resumableImportIds);
    }

    /**
     * @param  array<int, array{row_id: int, account_id?: int}>  $confirmations
     * @return array{confirmed: int, skipped: int}
     */
    public function confirmRows(Company $company, BankImport $bankImport, array $confirmations): array
    {
        $confirmed = 0;

        DB::transaction(function () use ($company, $bankImport, $confirmations, &$confirmed) {
            foreach ($confirmations as $confirmation) {
                $row = BankImportRow::where('bank_import_id', $bankImport->id)
                    ->where('company_id', $company->id)
                    ->where('id', $confirmation['row_id'])
                    ->where('status', BankImportRowStatus::Pending)
                    ->first();

                if ($row === null) {
                    throw new InvalidArgumentException('選択した取引は既に記帳済みか、無効です。ページを更新してから再度お試しください。');
                }

                $accountId = $confirmation['account_id'] ?? null;
                $entry = $this->postRow($company, $row, $accountId);

                if ($accountId !== null && $row->withdrawal_amount > 0) {
                    $this->ruleMatcher->learnFromConfirmation($company, $row->description, $accountId);
                }

                $row->update([
                    'status' => BankImportRowStatus::Confirmed,
                    'journal_entry_id' => $entry->id,
                ]);

                $confirmed++;
            }

            $this->updateImportStatus($bankImport);
        });

        return ['confirmed' => $confirmed, 'skipped' => 0];
    }

    public function deletePostedJournal(Company $company, JournalEntry $entry): void
    {
        if ($entry->company_id !== $company->id || $entry->source !== JournalSource::BankCsv) {
            throw new InvalidArgumentException('Only bank CSV journal entries can be deleted through this action.');
        }

        DB::transaction(fn () => $this->deletePostedJournalEntry($entry));
    }

    /**
     * @param  iterable<JournalEntry>  $entries
     */
    public function deletePostedJournals(Company $company, iterable $entries): void
    {
        foreach ($entries as $entry) {
            if ($entry->company_id !== $company->id || $entry->source !== JournalSource::BankCsv) {
                throw new InvalidArgumentException('Only bank CSV journal entries can be deleted through this action.');
            }
        }

        DB::transaction(function () use ($entries) {
            foreach ($entries as $entry) {
                $this->deletePostedJournalEntry($entry);
            }
        });
    }

    private function deletePostedJournalEntry(JournalEntry $entry): void
    {
        $row = BankImportRow::where('journal_entry_id', $entry->id)->first();

        if ($row !== null) {
            $bankImport = $row->bankImport;
            $row->delete();
            $this->updateImportStatus($bankImport);
        }

        $entry->delete();
    }

    public function skipRow(Company $company, BankImportRow $row): void
    {
        if ($row->company_id !== $company->id) {
            throw new InvalidArgumentException('Row does not belong to this company.');
        }

        if ($row->status !== BankImportRowStatus::Pending) {
            throw new InvalidArgumentException('Only pending rows can be skipped.');
        }

        $row->update(['status' => BankImportRowStatus::Skipped]);
        $this->updateImportStatus($row->bankImport);
    }

    /**
     * @param  array{transaction_date: string, description: string, amount: int, account_id: int}  $data
     */
    public function updateRow(Company $company, BankImportRow $row, array $data): void
    {
        if ($row->company_id !== $company->id) {
            throw new InvalidArgumentException('Row does not belong to this company.');
        }

        if ($row->status === BankImportRowStatus::Skipped) {
            throw new InvalidArgumentException('Skipped rows cannot be edited.');
        }

        $isDeposit = $row->deposit_amount > 0;

        $fiscalYear = $company->activeFiscalYear();
        if ($fiscalYear === null) {
            throw new InvalidArgumentException('No active fiscal year configured for this company.');
        }

        $entryDate = Carbon::parse($data['transaction_date']);
        if ($entryDate->lt($fiscalYear->start_date) || $entryDate->gt($fiscalYear->end_date)) {
            throw new InvalidArgumentException('日付は会計期間内である必要があります。');
        }

        $amount = (int) $data['amount'];
        if ($amount <= 0) {
            throw new InvalidArgumentException('金額は1以上である必要があります。');
        }

        $accountId = (int) $data['account_id'];

        if (Account::where('id', $accountId)->doesntExist()) {
            throw new InvalidArgumentException('Invalid account selected.');
        }

        $rowUpdates = [
            'transaction_date' => $entryDate,
            'description' => $data['description'],
            'deposit_amount' => $isDeposit ? $amount : 0,
            'withdrawal_amount' => $isDeposit ? 0 : $amount,
        ];

        if ($row->status === BankImportRowStatus::Pending) {
            $row->update($rowUpdates);

            return;
        }

        $entry = JournalEntry::where('id', $row->journal_entry_id)
            ->where('company_id', $company->id)
            ->first();

        if ($entry === null) {
            throw new InvalidArgumentException('Linked journal entry not found.');
        }

        $previousAccountId = $this->resolveCounterAccountId($entry, $row);
        $depositAccount = Account::findByName('預金');

        $lines = $isDeposit
            ? $this->consumptionTaxService->buildTaxableRevenueLines($amount, $depositAccount->id, $accountId)
            : $this->consumptionTaxService->buildTaxableExpenseLines($amount, $accountId, $depositAccount->id);

        DB::transaction(function () use ($company, $row, $rowUpdates, $entry, $entryDate, $data, $lines, $accountId, $previousAccountId, $isDeposit) {
            $row->update($rowUpdates);

            $this->journalService->updateBalancedEntry(
                $entry,
                $company,
                $entryDate,
                $data['description'],
                $lines,
            );

            if (! $isDeposit && $accountId !== $previousAccountId) {
                $this->ruleMatcher->learnFromConfirmation($company, $data['description'], $accountId);
            }
        });
    }

    public function resolveCounterAccountId(JournalEntry $entry, BankImportRow $row): int
    {
        $entry->loadMissing('lines.account');

        $excludedNames = ['預金', '仮払消費税', '仮受消費税'];

        foreach ($entry->lines as $line) {
            if (in_array($line->account->name, $excludedNames, true)) {
                continue;
            }

            return $line->account_id;
        }

        if ($row->deposit_amount > 0) {
            return Account::findByName('売上高')->id;
        }

        throw new InvalidArgumentException('Could not resolve counter account from journal lines.');
    }

    private function postRow(Company $company, BankImportRow $row, ?int $expenseAccountId): JournalEntry
    {
        $idempotencyKey = "bank_csv:{$row->row_hash}";

        $existingEntry = JournalEntry::where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingEntry !== null) {
            return $existingEntry;
        }

        $depositAccount = Account::findByName('預金');

        if ($row->deposit_amount > 0) {
            $revenueAccount = Account::findByName('売上高');

            return $this->journalService->createBalancedEntry(
                $company,
                Carbon::parse($row->transaction_date),
                $row->description,
                JournalSource::BankCsv,
                $this->consumptionTaxService->buildTaxableRevenueLines(
                    $row->deposit_amount,
                    $depositAccount->id,
                    $revenueAccount->id,
                ),
                $idempotencyKey,
            );
        }

        if ($expenseAccountId === null) {
            throw new InvalidArgumentException('Expense account is required for withdrawal rows.');
        }

        $expenseAccountIds = Account::expenseAccounts()->pluck('id')->all();
        if (! in_array($expenseAccountId, $expenseAccountIds, true)) {
            throw new InvalidArgumentException('Invalid expense account selected.');
        }

        return $this->journalService->createBalancedEntry(
            $company,
            Carbon::parse($row->transaction_date),
            $row->description,
            JournalSource::BankCsv,
            $this->consumptionTaxService->buildTaxableExpenseLines(
                $row->withdrawal_amount,
                $expenseAccountId,
                $depositAccount->id,
            ),
            $idempotencyKey,
        );
    }

    private function updateImportStatus(BankImport $bankImport): void
    {
        $pendingCount = $bankImport->rows()->where('status', BankImportRowStatus::Pending)->count();

        if ($pendingCount === 0) {
            $bankImport->update(['status' => BankImportStatus::Completed]);
        }
    }
}
