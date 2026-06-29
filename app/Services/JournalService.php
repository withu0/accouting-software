<?php

namespace App\Services;

use App\Enums\ConsumptionTaxCategory;
use App\Enums\JournalSource;
use App\Exceptions\UnbalancedJournalException;
use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalService
{
    /**
     * @param  array<int, array{account_id: int, debit: int, credit: int}>  $lines
     */
    public function createBalancedEntry(
        Company $company,
        Carbon $entryDate,
        string $description,
        JournalSource $source,
        array $lines,
        ?string $idempotencyKey = null,
        ?ConsumptionTaxCategory $consumptionTaxCategory = null,
        ?bool $hasQualifiedInvoice = null,
    ): JournalEntry {
        $this->validateLines($lines);
        $this->validateIdempotencyKey($company, $idempotencyKey);

        $fiscalYear = $company->activeFiscalYear();
        if ($fiscalYear === null) {
            throw new InvalidArgumentException('No active fiscal year configured for this company.');
        }

        if ($entryDate->lt($fiscalYear->start_date) || $entryDate->gt($fiscalYear->end_date)) {
            throw new InvalidArgumentException('日付は会計期間内である必要があります。');
        }

        $accountIds = array_column($lines, 'account_id');
        $existingCount = Account::whereIn('id', $accountIds)->count();
        if ($existingCount !== count(array_unique($accountIds))) {
            throw new InvalidArgumentException('One or more account IDs are invalid.');
        }

        return DB::transaction(function () use ($company, $fiscalYear, $entryDate, $description, $source, $lines, $idempotencyKey, $consumptionTaxCategory, $hasQualifiedInvoice) {
            $entry = JournalEntry::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $fiscalYear->id,
                'entry_date' => $entryDate,
                'description' => $description,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'consumption_tax_category' => $consumptionTaxCategory,
                'has_qualified_invoice' => $hasQualifiedInvoice,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * @param  array<int, array{account_id: int, debit: int, credit: int}>  $lines
     */
    public function updateBalancedEntry(
        JournalEntry $entry,
        Company $company,
        Carbon $entryDate,
        string $description,
        array $lines,
        ?ConsumptionTaxCategory $consumptionTaxCategory = null,
        ?bool $hasQualifiedInvoice = null,
    ): JournalEntry {
        $this->validateLines($lines);

        $fiscalYear = $company->activeFiscalYear();
        if ($fiscalYear === null) {
            throw new InvalidArgumentException('No active fiscal year configured for this company.');
        }

        if ($entryDate->lt($fiscalYear->start_date) || $entryDate->gt($fiscalYear->end_date)) {
            throw new InvalidArgumentException('日付は会計期間内である必要があります。');
        }

        $accountIds = array_column($lines, 'account_id');
        $existingCount = Account::whereIn('id', $accountIds)->count();
        if ($existingCount !== count(array_unique($accountIds))) {
            throw new InvalidArgumentException('One or more account IDs are invalid.');
        }

        return DB::transaction(function () use ($entry, $fiscalYear, $entryDate, $description, $lines, $consumptionTaxCategory, $hasQualifiedInvoice) {
            $entry->update([
                'fiscal_year_id' => $fiscalYear->id,
                'entry_date' => $entryDate,
                'description' => $description,
                'consumption_tax_category' => $consumptionTaxCategory,
                'has_qualified_invoice' => $hasQualifiedInvoice,
            ]);

            $entry->lines()->delete();

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * @param  array<int, array{account_id: int, debit: int, credit: int}>  $lines
     */
    public function validateLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry must have at least two lines.');
        }

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $debit = $line['debit'] ?? 0;
            $credit = $line['credit'] ?? 0;

            if (($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                throw new InvalidArgumentException('Each line must have exactly one of debit or credit greater than zero.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if ($totalDebit !== $totalCredit || $totalDebit === 0) {
            throw new UnbalancedJournalException(
                "Journal entry is unbalanced: debits={$totalDebit}, credits={$totalCredit}.",
            );
        }
    }

    private function validateIdempotencyKey(Company $company, ?string $idempotencyKey): void
    {
        if ($idempotencyKey === null) {
            return;
        }

        $exists = JournalEntry::where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('A journal entry with this idempotency key already exists.');
        }
    }
}
