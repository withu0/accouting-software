<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OpeningBalanceService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    /**
     * @param  array<int, array{account_id: int, amount: int}>  $inputLines
     */
    public function saveDraft(Company $company, FiscalYear $fiscalYear, array $inputLines): OpeningBalance
    {
        $this->assertFiscalYear($company, $fiscalYear);

        return DB::transaction(function () use ($company, $fiscalYear, $inputLines) {
            return $this->persistLines($company, $fiscalYear, $inputLines, confirm: false);
        });
    }

    /**
     * @param  array<int, array{account_id: int, amount: int}>  $inputLines
     */
    public function confirm(Company $company, FiscalYear $fiscalYear, array $inputLines): OpeningBalance
    {
        $this->assertFiscalYear($company, $fiscalYear);

        $totals = $this->totalsFromInput($inputLines);
        if ($totals['difference'] !== 0) {
            throw new InvalidArgumentException(
                '貸借が一致していません。差額：'.number_format(abs($totals['difference'])).'円',
            );
        }

        return DB::transaction(function () use ($company, $fiscalYear, $inputLines) {
            $opening = $this->persistLines($company, $fiscalYear, $inputLines, confirm: true);
            $this->syncJournal($company, $fiscalYear, $opening);

            return $opening;
        });
    }

    /**
     * Replace a confirmed opening balance (used by year-end carry-forward).
     *
     * @param  array<int, int>  $amountsByAccountId
     */
    public function replaceConfirmed(Company $company, FiscalYear $fiscalYear, array $amountsByAccountId): OpeningBalance
    {
        $inputLines = [];
        foreach ($amountsByAccountId as $accountId => $amount) {
            $inputLines[] = [
                'account_id' => (int) $accountId,
                'amount' => (int) $amount,
            ];
        }

        return $this->confirm($company, $fiscalYear, $inputLines);
    }

    public function formState(Company $company, FiscalYear $fiscalYear): array
    {
        $this->assertFiscalYear($company, $fiscalYear);

        $opening = $this->find($company, $fiscalYear);
        $linesByAccountId = [];

        if ($opening !== null) {
            foreach ($opening->lines as $line) {
                $linesByAccountId[$line->account_id] = $this->signedAmount($line->account, $line->debit, $line->credit);
            }
        }

        $groups = [
            '資産' => [],
            '負債' => [],
            '純資産' => [],
        ];

        foreach (Account::balanceSheetAccounts() as $account) {
            $label = match ($account->type) {
                AccountType::Asset => '資産',
                AccountType::Liability => '負債',
                AccountType::Equity => '純資産',
                default => null,
            };

            if ($label === null) {
                continue;
            }

            $groups[$label][] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'is_contra' => $account->is_contra,
                'is_required' => in_array($account->name, ['資本金', '繰越利益剰余金'], true),
                'amount' => $linesByAccountId[$account->id] ?? 0,
            ];
        }

        $hasPeriodJournals = $company->journalEntries()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('source', '!=', JournalSource::OpeningBalance)
            ->exists();

        return [
            'groups' => $groups,
            'is_confirmed' => $opening?->isConfirmed() ?? false,
            'confirmed_at' => $opening?->confirmed_at?->format('Y-m-d H:i'),
            'has_period_journals' => $hasPeriodJournals,
            'fiscal_year' => [
                'id' => $fiscalYear->id,
                'start_date' => $fiscalYear->start_date->format('Y-m-d'),
                'end_date' => $fiscalYear->end_date->format('Y-m-d'),
            ],
        ];
    }

    public function find(Company $company, FiscalYear $fiscalYear): ?OpeningBalance
    {
        return OpeningBalance::query()
            ->with('lines.account')
            ->where('company_id', $company->id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->first();
    }

    public function isConfirmed(Company $company, FiscalYear $fiscalYear): bool
    {
        return $this->find($company, $fiscalYear)?->isConfirmed() ?? false;
    }

    public function hasPeriodJournals(Company $company, FiscalYear $fiscalYear): bool
    {
        return $company->journalEntries()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('source', '!=', JournalSource::OpeningBalance)
            ->exists();
    }

    /**
     * @param  array<int, array{account_id: int, amount: int}>  $inputLines
     * @return array{asset_total: int, liability_total: int, equity_total: int, difference: int}
     */
    public function totalsFromInput(array $inputLines): array
    {
        $accounts = Account::whereIn('id', array_column($inputLines, 'account_id'))->get()->keyBy('id');

        $assetTotal = 0;
        $liabilityTotal = 0;
        $equityTotal = 0;

        foreach ($inputLines as $line) {
            $account = $accounts->get((int) $line['account_id']);
            if ($account === null) {
                continue;
            }

            $signed = $account->is_contra ? -(int) $line['amount'] : (int) $line['amount'];

            match ($account->type) {
                AccountType::Asset => $assetTotal += $signed,
                AccountType::Liability => $liabilityTotal += $signed,
                AccountType::Equity => $equityTotal += $signed,
                default => null,
            };
        }

        return [
            'asset_total' => $assetTotal,
            'liability_total' => $liabilityTotal,
            'equity_total' => $equityTotal,
            'difference' => $assetTotal - ($liabilityTotal + $equityTotal),
        ];
    }

    /**
     * @param  array<int, array{account_id: int, amount: int}>  $inputLines
     */
    private function persistLines(Company $company, FiscalYear $fiscalYear, array $inputLines, bool $confirm): OpeningBalance
    {
        $opening = OpeningBalance::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'fiscal_year_id' => $fiscalYear->id,
            ],
            ['confirmed_at' => null],
        );

        $accounts = Account::balanceSheetAccounts()->keyBy('id');
        $opening->lines()->delete();

        foreach ($inputLines as $line) {
            $account = $accounts->get((int) $line['account_id']);
            if ($account === null) {
                throw new InvalidArgumentException('開始残高に使えない勘定科目が含まれています。');
            }

            $sides = $this->toDebitCredit($account, (int) $line['amount']);
            if ($sides['debit'] === 0 && $sides['credit'] === 0) {
                continue;
            }

            $opening->lines()->create([
                'account_id' => $account->id,
                'debit' => $sides['debit'],
                'credit' => $sides['credit'],
            ]);
        }

        if ($confirm) {
            $opening->update(['confirmed_at' => now()]);
        }

        return $opening->load('lines.account');
    }

    private function syncJournal(Company $company, FiscalYear $fiscalYear, OpeningBalance $opening): void
    {
        $key = $this->idempotencyKey($fiscalYear);
        $existing = JournalEntry::query()
            ->where('company_id', $company->id)
            ->where('idempotency_key', $key)
            ->first();

        $journalLines = [];
        foreach ($opening->lines as $line) {
            if ($line->debit === 0 && $line->credit === 0) {
                continue;
            }

            $journalLines[] = [
                'account_id' => $line->account_id,
                'debit' => $line->debit,
                'credit' => $line->credit,
            ];
        }

        if ($journalLines === []) {
            $existing?->delete();

            return;
        }

        $entryDate = Carbon::parse($fiscalYear->start_date);

        if ($existing !== null) {
            $this->journalService->updateBalancedEntry(
                $existing,
                $company,
                $entryDate,
                '開始残高',
                $journalLines,
                null,
                null,
                $fiscalYear,
            );

            return;
        }

        $this->journalService->createBalancedEntry(
            $company,
            $entryDate,
            '開始残高',
            JournalSource::OpeningBalance,
            $journalLines,
            $key,
            null,
            null,
            $fiscalYear,
        );
    }

    /**
     * @return array{debit: int, credit: int}
     */
    private function toDebitCredit(Account $account, int $amount): array
    {
        if ($amount === 0) {
            return ['debit' => 0, 'credit' => 0];
        }

        if ($account->normalBalanceIsDebit()) {
            return $amount > 0
                ? ['debit' => $amount, 'credit' => 0]
                : ['debit' => 0, 'credit' => abs($amount)];
        }

        return $amount > 0
            ? ['debit' => 0, 'credit' => $amount]
            : ['debit' => abs($amount), 'credit' => 0];
    }

    private function signedAmount(Account $account, int $debit, int $credit): int
    {
        return $account->normalBalanceIsDebit() ? $debit - $credit : $credit - $debit;
    }

    private function idempotencyKey(FiscalYear $fiscalYear): string
    {
        return "opening_balance:{$fiscalYear->id}";
    }

    private function assertFiscalYear(Company $company, FiscalYear $fiscalYear): void
    {
        if ($fiscalYear->company_id !== $company->id) {
            throw new InvalidArgumentException('Fiscal year does not belong to this company.');
        }
    }
}
