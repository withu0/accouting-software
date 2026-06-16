<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * MVP: No opening balance entries are assumed for the first fiscal year.
     * Net income is shown as 当期純利益 on the equity side of the balance sheet
     * rather than posting a closing entry to 元入金.
     */
    public function profitAndLoss(Company $company, FiscalYear $fiscalYear): array
    {
        $balances = $this->accountBalances($company, $fiscalYear);

        $revenueRows = $this->rowsForType($balances, AccountType::Revenue);
        $expenseRows = $this->rowsForType($balances, AccountType::Expense);

        $totalRevenue = array_sum(array_column($revenueRows, 'amount'));
        $totalExpense = array_sum(array_column($expenseRows, 'amount'));
        $netIncome = $totalRevenue - $totalExpense;

        return [
            'revenue_rows' => $revenueRows,
            'expense_rows' => $expenseRows,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income' => $netIncome,
        ];
    }

    public function balanceSheet(Company $company, FiscalYear $fiscalYear): array
    {
        $pl = $this->profitAndLoss($company, $fiscalYear);
        $balances = $this->accountBalances($company, $fiscalYear);

        $assetRows = $this->rowsForType($balances, AccountType::Asset);
        $liabilityRows = $this->rowsForType($balances, AccountType::Liability);
        $equityRows = $this->rowsForType($balances, AccountType::Equity);

        if ($pl['net_income'] !== 0) {
            $equityRows[] = [
                'account_id' => null,
                'account_code' => null,
                'account_name' => '当期純利益',
                'amount' => $pl['net_income'],
            ];
        }

        $totalAssets = array_sum(array_column($assetRows, 'amount'));
        $totalLiabilities = array_sum(array_column($liabilityRows, 'amount'));
        $totalEquity = array_sum(array_column($equityRows, 'amount'));

        return [
            'asset_rows' => $assetRows,
            'liability_rows' => $liabilityRows,
            'equity_rows' => $equityRows,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => $totalLiabilities + $totalEquity,
            'is_balanced' => $totalAssets === ($totalLiabilities + $totalEquity),
        ];
    }

    public function journalBook(Company $company, FiscalYear $fiscalYear): array
    {
        $entries = $company->journalEntries()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->with(['lines.account'])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'voucher_no' => $entry->id,
                'entry_date' => $entry->entry_date->format('Y-m-d'),
                'description' => $entry->description,
                'source' => $entry->source->value,
                'lines' => $entry->lines->map(fn ($line) => [
                    'account_code' => $line->account->code,
                    'account_name' => $line->account->name,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ])->values()->all(),
                'total_amount' => $entry->lines->sum('debit'),
            ])
            ->values()
            ->all();

        return [
            'entries' => $entries,
        ];
    }

    public function generalLedger(Company $company, FiscalYear $fiscalYear): array
    {
        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->orderBy('accounts.display_order')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id')
            ->select([
                'accounts.id as account_id',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'accounts.type as account_type',
                'journal_entries.entry_date',
                'journal_entries.description',
                'journal_lines.debit',
                'journal_lines.credit',
            ])
            ->get();

        $accounts = [];

        foreach ($lines as $line) {
            $accountId = (int) $line->account_id;

            if (! isset($accounts[$accountId])) {
                $accounts[$accountId] = [
                    'account_id' => $accountId,
                    'account_code' => $line->account_code,
                    'account_name' => $line->account_name,
                    'account_type' => $line->account_type,
                    'opening_balance' => 0,
                    'lines' => [],
                    'closing_balance' => 0,
                ];
            }

            $accountType = AccountType::from($line->account_type);
            $runningBalance = $accounts[$accountId]['closing_balance'];
            $runningBalance += $this->signedMovement($accountType, (int) $line->debit, (int) $line->credit);

            $accounts[$accountId]['lines'][] = [
                'entry_date' => $line->entry_date,
                'description' => $line->description,
                'debit' => (int) $line->debit,
                'credit' => (int) $line->credit,
                'balance' => $runningBalance,
            ];

            $accounts[$accountId]['closing_balance'] = $runningBalance;
        }

        return [
            'accounts' => array_values($accounts),
        ];
    }

    /**
     * @return Collection<int, object{account_id: int, account_name: string, account_type: string, total_debit: int, total_credit: int}>
     */
    private function accountBalances(Company $company, FiscalYear $fiscalYear): Collection
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.display_order')
            ->orderBy('accounts.display_order')
            ->select([
                'accounts.id as account_id',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'accounts.type as account_type',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit'),
            ])
            ->get();
    }

    /**
     * @param  Collection<int, object>  $balances
     * @return list<array{account_id: int|null, account_code: ?string, account_name: string, amount: int}>
     */
    private function rowsForType(Collection $balances, AccountType $type): array
    {
        $rows = [];

        foreach ($balances as $balance) {
            if ($balance->account_type !== $type->value) {
                continue;
            }

            $amount = $this->signedBalance(
                $type,
                (int) $balance->total_debit,
                (int) $balance->total_credit,
            );

            if ($amount === 0) {
                continue;
            }

            $rows[] = [
                'account_id' => (int) $balance->account_id,
                'account_code' => $balance->account_code,
                'account_name' => $balance->account_name,
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    private function signedBalance(AccountType $type, int $totalDebit, int $totalCredit): int
    {
        return match ($type) {
            AccountType::Asset, AccountType::Expense => $totalDebit - $totalCredit,
            AccountType::Liability, AccountType::Equity, AccountType::Revenue => $totalCredit - $totalDebit,
        };
    }

    private function signedMovement(AccountType $type, int $debit, int $credit): int
    {
        return $this->signedBalance($type, $debit, $credit);
    }
}
