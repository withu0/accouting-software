<?php

namespace App\Services;

use App\Enums\ConsumptionTaxCategory;
use App\Enums\CreditCardImportRowStatus;
use App\Enums\CreditCardImportStatus;
use App\Enums\CreditCardCsvFormat;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\CreditCardImport;
use App\Models\CreditCardImportRow;
use App\Models\JournalEntry;
use App\Services\CreditCardCsv\CreditCardCsvParser;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditCardImportService
{
    public function __construct(
        private readonly CreditCardCsvParser $parser,
        private readonly JournalService $journalService,
        private readonly DescriptionRuleMatcher $ruleMatcher,
        private readonly ConsumptionTaxService $consumptionTaxService,
    ) {}

    /**
     * @return array{
     *     import: CreditCardImport,
     *     total: int,
     *     new: int,
     *     duplicates: int,
     *     out_of_period: int,
     *     detected_format?: CreditCardCsvFormat,
     *     resumed?: bool,
     * }
     */
    public function import(Company $company, UploadedFile $file): array
    {
        $fiscalYear = $company->activeFiscalYear();
        if ($fiscalYear === null) {
            throw new InvalidArgumentException('No active fiscal year configured for this company.');
        }

        $parseResult = $this->parser->parse($file->get());
        $parsedRows = $parseResult['rows'];
        $detectedFormat = $parseResult['format'];

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

            $existingRow = CreditCardImportRow::where('company_id', $company->id)
                ->where('row_hash', $row['row_hash'])
                ->first();

            if ($existingRow !== null) {
                $duplicateCount++;

                if ($existingRow->status === CreditCardImportRowStatus::Pending) {
                    $resumableImportIds[$existingRow->credit_card_import_id] = ($resumableImportIds[$existingRow->credit_card_import_id] ?? 0) + 1;
                }

                continue;
            }

            $rowsToCreate[] = ['row' => $row];
            $newCount++;
        }

        if ($newCount === 0) {
            $importableRowCount = count($parsedRows) - $outOfPeriodCount;

            if ($importableRowCount > 0 && $duplicateCount === $importableRowCount) {
                $resumableImportId = $this->resolveResumableImportId($resumableImportIds);

                if ($resumableImportId !== null) {
                    return [
                        'import' => CreditCardImport::findOrFail($resumableImportId),
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

        $creditCardImport = CreditCardImport::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYear->id,
            'original_filename' => $file->getClientOriginalName(),
            'detected_format' => $detectedFormat->value,
            'card_name' => $parseResult['card_name'],
            'payment_date' => $parseResult['payment_date'],
            'billing_amount' => $parseResult['billing_amount'],
            'status' => CreditCardImportStatus::Pending,
            'row_count' => $newCount,
            'imported_at' => now(),
        ]);

        foreach ($rowsToCreate as $item) {
            $row = $item['row'];
            $suggestedAccount = $this->ruleMatcher->suggestAccount($company, $row['description']);

            CreditCardImportRow::create([
                'credit_card_import_id' => $creditCardImport->id,
                'company_id' => $company->id,
                'row_hash' => $row['row_hash'],
                'transaction_date' => $row['transaction_date'],
                'description' => $row['description'],
                'amount' => $row['amount'],
                'suggested_account_id' => $suggestedAccount?->id,
                'status' => CreditCardImportRowStatus::Pending,
            ]);
        }

        return [
            'import' => $creditCardImport,
            'total' => count($parsedRows),
            'new' => $newCount,
            'duplicates' => $duplicateCount,
            'out_of_period' => $outOfPeriodCount,
            'detected_format' => $detectedFormat,
        ];
    }

    /**
     * @param  array<int, array{row_id: int, account_id?: int, consumption_tax_category?: string, has_qualified_invoice?: bool}>  $confirmations
     * @return array{confirmed: int, skipped: int}
     */
    public function confirmRows(Company $company, CreditCardImport $creditCardImport, array $confirmations): array
    {
        $confirmed = 0;

        DB::transaction(function () use ($company, $creditCardImport, $confirmations, &$confirmed) {
            foreach ($confirmations as $confirmation) {
                $row = CreditCardImportRow::where('credit_card_import_id', $creditCardImport->id)
                    ->where('company_id', $company->id)
                    ->where('id', $confirmation['row_id'])
                    ->where('status', CreditCardImportRowStatus::Pending)
                    ->first();

                if ($row === null) {
                    throw new InvalidArgumentException('選択した取引は既に記帳済みか、無効です。ページを更新してから再度お試しください。');
                }

                $accountId = $confirmation['account_id'] ?? null;
                if ($accountId === null) {
                    throw new InvalidArgumentException('経費科目を選択してください。');
                }

                $entry = $this->postRow($company, $row, (int) $accountId, $confirmation);
                $this->ruleMatcher->learnFromConfirmation($company, $row->description, (int) $accountId);

                $row->update([
                    'status' => CreditCardImportRowStatus::Confirmed,
                    'journal_entry_id' => $entry->id,
                ]);

                $confirmed++;
            }

            $this->updateImportStatus($creditCardImport);
        });

        return ['confirmed' => $confirmed, 'skipped' => 0];
    }

    public function deletePostedJournal(Company $company, JournalEntry $entry): void
    {
        if ($entry->company_id !== $company->id || $entry->source !== JournalSource::CreditCardCsv) {
            throw new InvalidArgumentException('Only credit card CSV journal entries can be deleted through this action.');
        }

        DB::transaction(fn () => $this->deletePostedJournalEntry($entry));
    }

    /**
     * @param  iterable<JournalEntry>  $entries
     */
    public function deletePostedJournals(Company $company, iterable $entries): void
    {
        foreach ($entries as $entry) {
            if ($entry->company_id !== $company->id || $entry->source !== JournalSource::CreditCardCsv) {
                throw new InvalidArgumentException('Only credit card CSV journal entries can be deleted through this action.');
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
        $row = CreditCardImportRow::where('journal_entry_id', $entry->id)->first();

        if ($row !== null) {
            $creditCardImport = $row->creditCardImport;
            $row->delete();
            $this->updateImportStatus($creditCardImport);
        }

        $entry->delete();
    }

    public function skipRow(Company $company, CreditCardImportRow $row): void
    {
        if ($row->company_id !== $company->id) {
            throw new InvalidArgumentException('Row does not belong to this company.');
        }

        if ($row->status !== CreditCardImportRowStatus::Pending) {
            throw new InvalidArgumentException('Only pending rows can be skipped.');
        }

        $row->update(['status' => CreditCardImportRowStatus::Skipped]);
        $this->updateImportStatus($row->creditCardImport);
    }

    /**
     * @param  array{transaction_date: string, description: string, amount: int, account_id: int, consumption_tax_category?: string, has_qualified_invoice?: bool}  $data
     */
    public function updateRow(Company $company, CreditCardImportRow $row, array $data): void
    {
        if ($row->company_id !== $company->id) {
            throw new InvalidArgumentException('Row does not belong to this company.');
        }

        if ($row->status === CreditCardImportRowStatus::Skipped) {
            throw new InvalidArgumentException('Skipped rows cannot be edited.');
        }

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
            'amount' => $amount,
        ];

        if ($row->status === CreditCardImportRowStatus::Pending) {
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
        $lines = $this->buildExpenseJournalLines($company, $entryDate, $amount, $accountId, $data);

        DB::transaction(function () use ($company, $row, $rowUpdates, $entry, $entryDate, $data, $lines, $accountId, $previousAccountId) {
            $row->update($rowUpdates);

            $taxCategory = $this->resolveBaseCategory($data, $accountId);
            $hasQualifiedInvoice = $this->resolveHasQualifiedInvoice($taxCategory, $data);

            $this->journalService->updateBalancedEntry(
                $entry,
                $company,
                $entryDate,
                $data['description'],
                $lines,
                $taxCategory,
                $hasQualifiedInvoice,
            );

            if ($accountId !== $previousAccountId) {
                $this->ruleMatcher->learnFromConfirmation($company, $data['description'], $accountId);
            }
        });
    }

    public function resolveCounterAccountId(JournalEntry $entry, CreditCardImportRow $row): int
    {
        $entry->loadMissing('lines.account');

        $excludedNames = ['未払金', '仮払消費税', '仮受消費税'];

        foreach ($entry->lines as $line) {
            if (in_array($line->account->name, $excludedNames, true)) {
                continue;
            }

            return $line->account_id;
        }

        throw new InvalidArgumentException('Could not resolve counter account from journal lines.');
    }

    /**
     * @param  array{consumption_tax_category?: string, has_qualified_invoice?: bool}  $options
     */
    private function postRow(Company $company, CreditCardImportRow $row, int $expenseAccountId, array $options = []): JournalEntry
    {
        $idempotencyKey = "credit_card_csv:{$row->row_hash}";

        $existingEntry = JournalEntry::where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingEntry !== null) {
            return $existingEntry;
        }

        $expenseAccountIds = Account::expenseAccounts()->pluck('id')->all();
        if (! in_array($expenseAccountId, $expenseAccountIds, true)) {
            throw new InvalidArgumentException('Invalid expense account selected.');
        }

        $payableAccount = Account::findByName('未払金');
        $entryDate = Carbon::parse($row->transaction_date);
        $baseCategory = $this->resolveBaseCategory($options, $expenseAccountId);
        $hasQualifiedInvoice = $this->resolveHasQualifiedInvoice($baseCategory, $options);
        $effectiveCategory = $this->consumptionTaxService->resolveEffectiveCategory(
            $baseCategory,
            $hasQualifiedInvoice,
            $entryDate,
        );
        $lines = $this->consumptionTaxService->buildJournalLines(
            $effectiveCategory,
            $row->amount,
            $expenseAccountId,
            $payableAccount->id,
            false,
        );

        return $this->journalService->createBalancedEntry(
            $company,
            $entryDate,
            $row->description,
            JournalSource::CreditCardCsv,
            $lines,
            $idempotencyKey,
            $baseCategory,
            $hasQualifiedInvoice,
        );
    }

    /**
     * @param  array{consumption_tax_category?: string, has_qualified_invoice?: bool}  $options
     * @return array<int, array{account_id: int, debit: int, credit: int}>
     */
    private function buildExpenseJournalLines(Company $company, Carbon $entryDate, int $amount, int $expenseAccountId, array $options): array
    {
        $payableAccount = Account::findByName('未払金');
        $baseCategory = $this->resolveBaseCategory($options, $expenseAccountId);
        $hasQualifiedInvoice = $this->resolveHasQualifiedInvoice($baseCategory, $options);
        $effectiveCategory = $this->consumptionTaxService->resolveEffectiveCategory(
            $baseCategory,
            $hasQualifiedInvoice,
            $entryDate,
        );

        return $this->consumptionTaxService->buildJournalLines(
            $effectiveCategory,
            $amount,
            $expenseAccountId,
            $payableAccount->id,
            false,
        );
    }

    /**
     * @param  array{consumption_tax_category?: string}  $options
     */
    private function resolveBaseCategory(array $options, int $accountId): ConsumptionTaxCategory
    {
        if (! empty($options['consumption_tax_category'])) {
            return ConsumptionTaxCategory::from($options['consumption_tax_category']);
        }

        $account = Account::findOrFail($accountId);

        if ($account->default_consumption_tax_category !== null) {
            return $account->default_consumption_tax_category;
        }

        return ConsumptionTaxCategory::TaxablePurchase10;
    }

    /**
     * @param  array{has_qualified_invoice?: bool}  $options
     */
    private function resolveHasQualifiedInvoice(ConsumptionTaxCategory $baseCategory, array $options): ?bool
    {
        if (! $baseCategory->isBasePurchase()) {
            return null;
        }

        return array_key_exists('has_qualified_invoice', $options)
            ? (bool) $options['has_qualified_invoice']
            : true;
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

    private function updateImportStatus(CreditCardImport $creditCardImport): void
    {
        $pendingCount = $creditCardImport->rows()->where('status', CreditCardImportRowStatus::Pending)->count();

        if ($pendingCount === 0) {
            $creditCardImport->update(['status' => CreditCardImportStatus::Completed]);
        }
    }
}
