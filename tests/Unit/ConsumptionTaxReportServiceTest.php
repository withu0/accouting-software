<?php

namespace Tests\Unit;

use App\Enums\ConsumptionTaxCategory;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\ConsumptionTaxReportService;
use App\Services\JournalService;
use Carbon\Carbon;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionTaxReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConsumptionTaxReportService $reportService;

    private JournalService $journalService;

    private Company $company;

    private FiscalYear $fiscalYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);
        $this->reportService = app(ConsumptionTaxReportService::class);
        $this->journalService = app(JournalService::class);

        $user = User::factory()->create();
        $this->company = Company::create(['user_id' => $user->id, 'name' => 'テスト株式会社']);
        $this->fiscalYear = FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);
    }

    public function test_aggregate_groups_by_effective_category(): void
    {
        $deposit = Account::findByName('預金');
        $revenue = Account::findByName('売上高');
        $expense = Account::where('name', '消耗品費')->firstOrFail();
        $consumptionTaxService = app(\App\Services\ConsumptionTaxService::class);

        $this->journalService->createBalancedEntry(
            $this->company,
            Carbon::parse('2025-05-01'),
            '売上',
            JournalSource::BankCsv,
            $consumptionTaxService->buildJournalLines(
                ConsumptionTaxCategory::TaxableSales10,
                11000,
                $revenue->id,
                $deposit->id,
                true,
            ),
            null,
            ConsumptionTaxCategory::TaxableSales10,
            null,
        );

        $this->journalService->createBalancedEntry(
            $this->company,
            Carbon::parse('2025-05-02'),
            '仕入',
            JournalSource::AdvanceExpense,
            $consumptionTaxService->buildJournalLines(
                ConsumptionTaxCategory::TaxablePurchaseDeduction8010,
                11000,
                $expense->id,
                Account::findByName('役員借入金')->id,
                false,
            ),
            null,
            ConsumptionTaxCategory::TaxablePurchase10,
            false,
        );

        $report = $this->reportService->aggregate($this->company, $this->fiscalYear);

        $this->assertSame('テスト株式会社', $report['company_name']);
        $this->assertCount(2, $report['rows']);

        $salesRow = collect($report['rows'])->firstWhere('category', ConsumptionTaxCategory::TaxableSales10->value);
        $this->assertNotNull($salesRow);
        $this->assertSame(1, $salesRow['transaction_count']);
        $this->assertSame(11000, $salesRow['gross_total']);
        $this->assertSame(1000, $salesRow['tax_total']);

        $purchaseRow = collect($report['rows'])->firstWhere('category', ConsumptionTaxCategory::TaxablePurchaseDeduction8010->value);
        $this->assertNotNull($purchaseRow);
        $this->assertSame(800, $purchaseRow['deductible_tax_total']);
    }

    public function test_aggregate_supports_line_level_transfer_tax(): void
    {
        $accountsReceivable = Account::where('name', '売掛金')->firstOrFail();
        $accountsPayable = Account::where('name', '買掛金')->firstOrFail();

        $this->journalService->createBalancedEntry(
            $this->company,
            Carbon::parse('2025-05-03'),
            '振替',
            JournalSource::Transfer,
            [
                [
                    'account_id' => $accountsReceivable->id,
                    'debit' => 10000,
                    'credit' => 0,
                    'consumption_tax_category' => ConsumptionTaxCategory::OutOfScope,
                ],
                [
                    'account_id' => $accountsPayable->id,
                    'debit' => 0,
                    'credit' => 5000,
                    'consumption_tax_category' => ConsumptionTaxCategory::OutOfScope,
                ],
                [
                    'account_id' => $accountsReceivable->id,
                    'debit' => 0,
                    'credit' => 5000,
                    'consumption_tax_category' => ConsumptionTaxCategory::NonTaxable,
                ],
            ],
            null,
            null,
            null,
        );

        $report = $this->reportService->aggregate($this->company, $this->fiscalYear);

        $outOfScopeRow = collect($report['rows'])->firstWhere('category', ConsumptionTaxCategory::OutOfScope->value);
        $nonTaxableRow = collect($report['rows'])->firstWhere('category', ConsumptionTaxCategory::NonTaxable->value);

        $this->assertNotNull($outOfScopeRow);
        $this->assertSame(2, $outOfScopeRow['transaction_count']);
        $this->assertSame(15000, $outOfScopeRow['gross_total']);

        $this->assertNotNull($nonTaxableRow);
        $this->assertSame(1, $nonTaxableRow['transaction_count']);
        $this->assertSame(5000, $nonTaxableRow['gross_total']);
    }
}
