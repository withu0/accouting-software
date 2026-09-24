<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class YearEndCloseService
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly OpeningBalanceService $openingBalanceService,
    ) {}

    /**
     * @return array{
     *     asset_total: int,
     *     net_income: int,
     *     next_retained_earnings: int,
     *     next_start_date: string,
     *     next_end_date: string,
     *     already_closed: bool,
     *     has_next_period_journals: bool,
     *     is_balanced: bool,
     *     next_fiscal_year_exists: bool
     * }
     */
    public function preview(Company $company, FiscalYear $fiscalYear): array
    {
        $bs = $this->reportService->balanceSheet($company, $fiscalYear);
        $pl = $this->reportService->profitAndLoss($company, $fiscalYear);
        [$nextStart, $nextEnd] = $this->nextPeriodDates($fiscalYear);
        $nextYear = $this->findNextFiscalYear($company, $fiscalYear);

        $retainedRow = collect($bs['equity_rows'])->firstWhere('account_name', '繰越利益剰余金');
        $retained = (int) ($retainedRow['amount'] ?? 0);

        return [
            'asset_total' => $bs['total_assets'],
            'net_income' => $pl['net_income'],
            'next_retained_earnings' => $retained + $pl['net_income'],
            'next_start_date' => $nextStart->toDateString(),
            'next_end_date' => $nextEnd->toDateString(),
            'already_closed' => $fiscalYear->closed_at !== null,
            'has_next_period_journals' => $nextYear !== null && $this->openingBalanceService->hasPeriodJournals($company, $nextYear),
            'is_balanced' => $bs['is_balanced'],
            'next_fiscal_year_exists' => $nextYear !== null,
        ];
    }

    public function close(Company $company, FiscalYear $fiscalYear): FiscalYear
    {
        if ($fiscalYear->company_id !== $company->id) {
            throw new InvalidArgumentException('Fiscal year does not belong to this company.');
        }

        $bs = $this->reportService->balanceSheet($company, $fiscalYear);
        $pl = $this->reportService->profitAndLoss($company, $fiscalYear);

        if (! $bs['is_balanced']) {
            throw new InvalidArgumentException('貸借対照表が一致していないため、決算確定できません。');
        }

        $amounts = $this->closingAmounts($bs, $pl['net_income']);

        return DB::transaction(function () use ($company, $fiscalYear, $amounts) {
            $nextYear = $this->resolveNextFiscalYear($company, $fiscalYear);
            $this->openingBalanceService->replaceConfirmed($company, $nextYear, $amounts);
            $fiscalYear->update(['closed_at' => now()]);

            return $nextYear->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $bs
     * @return array<int, int>
     */
    private function closingAmounts(array $bs, int $netIncome): array
    {
        $accounts = Account::balanceSheetAccounts()->keyBy('id');
        $amounts = [];

        foreach (['asset_rows', 'liability_rows', 'equity_rows'] as $group) {
            foreach ($bs[$group] as $row) {
                if ($row['account_id'] === null) {
                    continue;
                }

                $account = $accounts->get((int) $row['account_id']);
                if ($account === null) {
                    continue;
                }

                $amounts[$account->id] = $account->is_contra ? -(int) $row['amount'] : (int) $row['amount'];
            }
        }

        $retained = Account::findByName('繰越利益剰余金');
        $amounts[$retained->id] = ($amounts[$retained->id] ?? 0) + $netIncome;

        return $amounts;
    }

    private function resolveNextFiscalYear(Company $company, FiscalYear $current): FiscalYear
    {
        $existing = $this->findNextFiscalYear($company, $current);
        if ($existing !== null) {
            return $existing;
        }

        [$start, $end] = $this->nextPeriodDates($current);

        return $company->fiscalYears()->create([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'is_active' => false,
        ]);
    }

    private function findNextFiscalYear(Company $company, FiscalYear $current): ?FiscalYear
    {
        [$start] = $this->nextPeriodDates($current);

        return $company->fiscalYears()
            ->whereDate('start_date', $start->toDateString())
            ->first();
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function nextPeriodDates(FiscalYear $current): array
    {
        $start = $current->end_date->copy()->addDay();
        $end = $current->end_date->copy()->addYear();

        return [$start, $end];
    }
}
