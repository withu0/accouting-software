<?php

namespace App\Services;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\EquityStatementOverride;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\DB;

class EquityStatementService
{
    public const COLUMNS = [
        'capital' => '資本金',
        'capital_surplus' => '資本剰余金',
        'retained_earnings' => '利益剰余金',
        'treasury' => '自己株式',
    ];

    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function build(Company $company, FiscalYear $fiscalYear): array
    {
        $accounts = $this->equityAccounts();
        $beginning = $this->beginningBalances($company, $fiscalYear, $accounts);
        $movements = $this->periodMovements($company, $fiscalYear, $accounts);
        $netIncome = $this->reportService->profitAndLoss($company, $fiscalYear)['net_income'];

        $override = $this->findOverride($company, $fiscalYear);
        $isManual = $override !== null;

        $changeRows = [];
        if ($netIncome !== 0) {
            $changeRows[] = $this->makeRow(
                $netIncome >= 0 ? '当期純利益' : '当期純損失',
                ['capital' => 0, 'capital_surplus' => 0, 'retained_earnings' => $netIncome, 'treasury' => 0],
                'net_income',
            );
        }

        $editableRows = $isManual
            ? $this->rowsFromOverride($override)
            : $this->classifiedMovementRows($movements);

        foreach ($editableRows as $row) {
            $changeRows[] = $this->makeRow($row['label'], $row, 'movement');
        }

        $changeTotals = $this->emptyAmounts();
        foreach ($changeRows as $row) {
            foreach (array_keys(self::COLUMNS) as $key) {
                $changeTotals[$key] += $row[$key];
            }
        }

        $ending = $this->emptyAmounts();
        foreach (array_keys(self::COLUMNS) as $key) {
            $ending[$key] = $beginning[$key] + $changeTotals[$key];
        }

        $rows = [
            ...[$this->makeRow('当期首残高', $beginning, 'beginning')],
            ...$changeRows,
            $this->makeRow('当期変動額合計', $changeTotals, 'subtotal'),
            $this->makeRow('当期末残高', $ending, 'ending'),
        ];

        $mismatches = $this->compareWithBalanceSheet($company, $fiscalYear, $ending, $accounts);

        return [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'beginning' => $this->withTotal($beginning),
            'ending' => $this->withTotal($ending),
            'net_income' => $netIncome,
            'is_consistent' => $mismatches === [],
            'mismatches' => $mismatches,
            'is_manual' => $isManual,
            'editable_rows' => $editableRows,
        ];
    }

    /**
     * @param  array<int, array{label: string, capital: int, capital_surplus: int, retained_earnings: int, treasury: int}>  $rows
     */
    public function saveManual(Company $company, FiscalYear $fiscalYear, array $rows): EquityStatementOverride
    {
        return DB::transaction(function () use ($company, $fiscalYear, $rows) {
            $override = EquityStatementOverride::query()->firstOrCreate([
                'company_id' => $company->id,
                'fiscal_year_id' => $fiscalYear->id,
            ]);

            $override->lines()->delete();

            foreach (array_values($rows) as $index => $row) {
                $label = trim((string) $row['label']);
                if ($label === '') {
                    continue;
                }

                $override->lines()->create([
                    'label' => $label,
                    'capital' => (int) $row['capital'],
                    'capital_surplus' => (int) $row['capital_surplus'],
                    'retained_earnings' => (int) $row['retained_earnings'],
                    'treasury' => (int) $row['treasury'],
                    'sort_order' => $index,
                ]);
            }

            return $override->load('lines');
        });
    }

    public function revertToAuto(Company $company, FiscalYear $fiscalYear): void
    {
        EquityStatementOverride::query()
            ->where('company_id', $company->id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->delete();
    }

    /**
     * @return array<string, Account>
     */
    private function equityAccounts(): array
    {
        return [
            'capital' => Account::findByName('資本金'),
            'capital_surplus' => Account::findByName('資本準備金'),
            'retained_earnings' => Account::findByName('繰越利益剰余金'),
            'treasury' => Account::findByName('自己株式'),
        ];
    }

    /**
     * @param  array<string, Account>  $accounts
     * @return array<string, int>
     */
    private function beginningBalances(Company $company, FiscalYear $fiscalYear, array $accounts): array
    {
        $ids = collect($accounts)->pluck('id')->all();

        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.source', JournalSource::OpeningBalance->value)
            ->whereIn('journal_lines.account_id', $ids)
            ->select('journal_lines.account_id', 'journal_lines.debit', 'journal_lines.credit')
            ->get();

        $amounts = $this->emptyAmounts();
        foreach ($accounts as $key => $account) {
            $debit = (int) $lines->where('account_id', $account->id)->sum('debit');
            $credit = (int) $lines->where('account_id', $account->id)->sum('credit');
            $amounts[$key] = $this->signedEquity($account, $debit, $credit);
        }

        return $amounts;
    }

    /**
     * @param  array<string, Account>  $accounts
     * @return array<string, int>
     */
    private function periodMovements(Company $company, FiscalYear $fiscalYear, array $accounts): array
    {
        $ids = collect($accounts)->pluck('id')->all();

        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.source', '!=', JournalSource::OpeningBalance->value)
            ->whereIn('journal_lines.account_id', $ids)
            ->select('journal_lines.account_id', 'journal_lines.debit', 'journal_lines.credit')
            ->get();

        $amounts = $this->emptyAmounts();
        foreach ($accounts as $key => $account) {
            $debit = (int) $lines->where('account_id', $account->id)->sum('debit');
            $credit = (int) $lines->where('account_id', $account->id)->sum('credit');
            $amounts[$key] = $this->signedEquity($account, $debit, $credit);
        }

        return $amounts;
    }

    private function findOverride(Company $company, FiscalYear $fiscalYear): ?EquityStatementOverride
    {
        return EquityStatementOverride::query()
            ->with('lines')
            ->where('company_id', $company->id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->first();
    }

    /**
     * @return list<array{label: string, capital: int, capital_surplus: int, retained_earnings: int, treasury: int}>
     */
    private function rowsFromOverride(EquityStatementOverride $override): array
    {
        return $override->lines
            ->map(fn ($line) => [
                'label' => $line->label,
                'capital' => (int) $line->capital,
                'capital_surplus' => (int) $line->capital_surplus,
                'retained_earnings' => (int) $line->retained_earnings,
                'treasury' => (int) $line->treasury,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $movements
     * @return list<array{label: string, capital: int, capital_surplus: int, retained_earnings: int, treasury: int}>
     */
    private function classifiedMovementRows(array $movements): array
    {
        $rows = [];
        foreach ($this->classifyMovements($movements) as $label => $amounts) {
            if ($this->rowTotal($amounts) === 0) {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'capital' => $amounts['capital'],
                'capital_surplus' => $amounts['capital_surplus'],
                'retained_earnings' => $amounts['retained_earnings'],
                'treasury' => $amounts['treasury'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $movements
     * @return array<string, array<string, int>>
     */
    private function classifyMovements(array $movements): array
    {
        $rows = [];

        $capitalIncrease = [
            'capital' => max(0, $movements['capital']),
            'capital_surplus' => max(0, $movements['capital_surplus']),
            'retained_earnings' => 0,
            'treasury' => 0,
        ];
        if ($this->rowTotal($capitalIncrease) !== 0) {
            $rows['新株の発行（増資）'] = $capitalIncrease;
        }

        $remainingSurplus = min(0, $movements['capital_surplus']);
        $remainingRetained = $movements['retained_earnings'];
        $offset = min(-$remainingSurplus, max(0, $remainingRetained));

        if ($offset > 0) {
            $rows['欠損填補等による振替'] = [
                'capital' => 0,
                'capital_surplus' => -$offset,
                'retained_earnings' => $offset,
                'treasury' => 0,
            ];
            $remainingSurplus += $offset;
            $remainingRetained -= $offset;
        }

        if ($remainingRetained < 0) {
            $rows['剰余金の配当'] = [
                'capital' => 0,
                'capital_surplus' => 0,
                'retained_earnings' => $remainingRetained,
                'treasury' => 0,
            ];
            $remainingRetained = 0;
        }

        $other = [
            'capital' => min(0, $movements['capital']),
            'capital_surplus' => $remainingSurplus,
            'retained_earnings' => $remainingRetained,
            'treasury' => $movements['treasury'],
        ];
        if ($this->rowTotal($other) !== 0) {
            $rows['その他'] = $other;
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $ending
     * @param  array<string, Account>  $accounts
     * @return list<array{account_name: string, statement_amount: int, bs_amount: int, difference: int}>
     */
    private function compareWithBalanceSheet(Company $company, FiscalYear $fiscalYear, array $ending, array $accounts): array
    {
        $bs = $this->reportService->balanceSheet($company, $fiscalYear);
        $bsByName = collect($bs['equity_rows'])->keyBy('account_name');

        $expected = [
            'capital' => (int) ($bsByName['資本金']['amount'] ?? 0),
            'capital_surplus' => (int) ($bsByName['資本準備金']['amount'] ?? 0),
            'retained_earnings' => (int) ($bsByName['繰越利益剰余金']['amount'] ?? 0) + (int) ($bsByName['当期純利益']['amount'] ?? 0),
            'treasury' => (int) ($bsByName['自己株式']['amount'] ?? 0),
        ];

        $mismatches = [];
        foreach (self::COLUMNS as $key => $label) {
            $statement = $ending[$key];
            $bsAmount = $expected[$key];
            if ($statement !== $bsAmount) {
                $mismatches[] = [
                    'account_name' => $label,
                    'statement_amount' => $statement,
                    'bs_amount' => $bsAmount,
                    'difference' => $statement - $bsAmount,
                ];
            }
        }

        return $mismatches;
    }

    /**
     * @param  array<string, int>  $amounts
     * @return array<string, int|string>
     */
    private function makeRow(string $label, array $amounts, string $kind): array
    {
        return [
            'label' => $label,
            'kind' => $kind,
            ...$this->withTotal($amounts),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyAmounts(): array
    {
        return [
            'capital' => 0,
            'capital_surplus' => 0,
            'retained_earnings' => 0,
            'treasury' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $amounts
     * @return array<string, int>
     */
    private function withTotal(array $amounts): array
    {
        return [
            ...$amounts,
            'total' => $this->rowTotal($amounts),
        ];
    }

    /**
     * @param  array<string, int>  $amounts
     */
    private function rowTotal(array $amounts): int
    {
        return ($amounts['capital'] ?? 0)
            + ($amounts['capital_surplus'] ?? 0)
            + ($amounts['retained_earnings'] ?? 0)
            + ($amounts['treasury'] ?? 0);
    }

    private function signedEquity(Account $account, int $debit, int $credit): int
    {
        return $credit - $debit;
    }
}
