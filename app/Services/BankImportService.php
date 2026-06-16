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
        private readonly DescriptionRuleMatcher $ruleMatcher,
        private readonly JournalService $journalService,
    ) {}

    /**
     * @return array{
     *     import: BankImport,
     *     total: int,
     *     new: int,
     *     duplicates: int,
     * }
     */
    public function import(Company $company, UploadedFile $file): array
    {
        $fiscalYear = $company->activeFiscalYear();
        if ($fiscalYear === null) {
            throw new InvalidArgumentException('No active fiscal year configured for this company.');
        }

        $parsedRows = $this->parser->parse($file->get());

        $bankImport = BankImport::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYear->id,
            'original_filename' => $file->getClientOriginalName(),
            'status' => BankImportStatus::Pending,
            'row_count' => count($parsedRows),
            'imported_at' => now(),
        ]);

        $newCount = 0;
        $duplicateCount = 0;

        foreach ($parsedRows as $row) {
            $existingRow = BankImportRow::where('company_id', $company->id)
                ->where('row_hash', $row['row_hash'])
                ->first();

            if ($existingRow !== null) {
                $duplicateCount++;

                continue;
            }

            $suggestedAccountId = null;
            if ($row['withdrawal_amount'] > 0) {
                $suggested = $this->ruleMatcher->suggestAccount($company, $row['description']);
                $suggestedAccountId = $suggested?->id;
            }

            BankImportRow::create([
                'bank_import_id' => $bankImport->id,
                'company_id' => $company->id,
                'row_hash' => $row['row_hash'],
                'transaction_date' => $row['transaction_date'],
                'description' => $row['description'],
                'deposit_amount' => $row['deposit_amount'],
                'withdrawal_amount' => $row['withdrawal_amount'],
                'balance' => $row['balance'],
                'suggested_account_id' => $suggestedAccountId,
                'status' => BankImportRowStatus::Pending,
            ]);

            $newCount++;
        }

        return [
            'import' => $bankImport,
            'total' => count($parsedRows),
            'new' => $newCount,
            'duplicates' => $duplicateCount,
        ];
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
                    ->firstOrFail();

                $accountId = $confirmation['account_id'] ?? null;
                $entry = $this->postRow($company, $row, $accountId);

                $row->update([
                    'status' => BankImportRowStatus::Confirmed,
                    'journal_entry_id' => $entry->id,
                ]);

                if ($row->withdrawal_amount > 0 && $accountId !== null) {
                    $this->ruleMatcher->learnFromConfirmation($company, $row->description, $accountId);
                }

                $confirmed++;
            }

            $this->updateImportStatus($bankImport);
        });

        return ['confirmed' => $confirmed, 'skipped' => 0];
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
                [
                    ['account_id' => $depositAccount->id, 'debit' => $row->deposit_amount, 'credit' => 0],
                    ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $row->deposit_amount],
                ],
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
            [
                ['account_id' => $expenseAccountId, 'debit' => $row->withdrawal_amount, 'credit' => 0],
                ['account_id' => $depositAccount->id, 'debit' => 0, 'credit' => $row->withdrawal_amount],
            ],
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
