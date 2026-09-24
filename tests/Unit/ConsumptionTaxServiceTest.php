<?php

namespace Tests\Unit;

use App\Enums\ConsumptionTaxCategory;
use App\Models\Account;
use App\Services\ConsumptionTaxService;
use Carbon\Carbon;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionTaxServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConsumptionTaxService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);
        $this->service = app(ConsumptionTaxService::class);
    }

    public function test_split_inclusive_standard_amount_10_percent(): void
    {
        $split = $this->service->splitInclusive(11000, ConsumptionTaxCategory::TaxablePurchase10);

        $this->assertSame(10000, $split['net']);
        $this->assertSame(1000, $split['tax']);
        $this->assertSame(1000, $split['deductible_tax']);
        $this->assertSame(0, $split['non_deductible_tax']);
    }

    public function test_split_inclusive_8_percent_reduced_rate(): void
    {
        $split = $this->service->splitInclusive(10800, ConsumptionTaxCategory::TaxablePurchase8Reduced);

        $this->assertSame(10000, $split['net']);
        $this->assertSame(800, $split['tax']);
    }

    public function test_split_inclusive_deduction_80_percent(): void
    {
        $split = $this->service->splitInclusive(11000, ConsumptionTaxCategory::TaxablePurchaseDeduction8010);

        $this->assertSame(1000, $split['tax']);
        $this->assertSame(800, $split['deductible_tax']);
        $this->assertSame(200, $split['non_deductible_tax']);
    }

    public function test_resolve_effective_category_applies_80_percent_before_2026_10(): void
    {
        $effective = $this->service->resolveEffectiveCategory(
            ConsumptionTaxCategory::TaxablePurchase10,
            false,
            Carbon::parse('2026-09-30'),
        );

        $this->assertSame(ConsumptionTaxCategory::TaxablePurchaseDeduction8010, $effective);
    }

    public function test_resolve_effective_category_applies_50_percent_from_2026_10(): void
    {
        $effective = $this->service->resolveEffectiveCategory(
            ConsumptionTaxCategory::TaxablePurchase10,
            false,
            Carbon::parse('2026-10-01'),
        );

        $this->assertSame(ConsumptionTaxCategory::TaxablePurchaseDeduction5010, $effective);
    }

    public function test_build_taxable_revenue_lines(): void
    {
        $deposit = Account::findByName('普通預金');
        $revenue = Account::findByName('売上高');
        $outputTax = Account::findByName('仮受消費税');

        $lines = $this->service->buildTaxableRevenueLines(11000, $deposit->id, $revenue->id);

        $this->assertCount(3, $lines);
        $this->assertSame($deposit->id, $lines[0]['account_id']);
        $this->assertSame(11000, $lines[0]['debit']);
        $this->assertSame($revenue->id, $lines[1]['account_id']);
        $this->assertSame(10000, $lines[1]['credit']);
        $this->assertSame($outputTax->id, $lines[2]['account_id']);
        $this->assertSame(1000, $lines[2]['credit']);
    }

    public function test_build_export_exempt_revenue_lines(): void
    {
        $deposit = Account::findByName('普通預金');
        $revenue = Account::findByName('売上高');

        $lines = $this->service->buildJournalLines(
            ConsumptionTaxCategory::ExportExempt,
            50000,
            $revenue->id,
            $deposit->id,
            true,
        );

        $this->assertCount(2, $lines);
        $this->assertSame(50000, $lines[0]['debit']);
        $this->assertSame(50000, $lines[1]['credit']);
    }

    public function test_build_taxable_expense_lines_with_partial_deduction(): void
    {
        $expense = Account::where('name', '消耗品費')->firstOrFail();
        $deposit = Account::findByName('普通預金');
        $inputTax = Account::findByName('仮払消費税');

        $lines = $this->service->buildJournalLines(
            ConsumptionTaxCategory::TaxablePurchaseDeduction8010,
            11000,
            $expense->id,
            $deposit->id,
            false,
        );

        $this->assertSame(10200, $lines[0]['debit']);
        $this->assertSame($inputTax->id, $lines[1]['account_id']);
        $this->assertSame(800, $lines[1]['debit']);
        $this->assertSame(11000, $lines[2]['credit']);
    }

    public function test_build_non_taxable_expense_lines(): void
    {
        $expense = Account::where('name', '地代家賃')->firstOrFail();
        $deposit = Account::findByName('普通預金');

        $lines = $this->service->buildJournalLines(
            ConsumptionTaxCategory::NonTaxable,
            100000,
            $expense->id,
            $deposit->id,
            false,
        );

        $this->assertCount(2, $lines);
        $this->assertSame(100000, $lines[0]['debit']);
        $this->assertSame(100000, $lines[1]['credit']);
    }

    public function test_build_journal_lines_skips_tax_split_when_tax_exempt(): void
    {
        $expense = Account::where('name', '消耗品費')->firstOrFail();
        $deposit = Account::findByName('普通預金');

        $lines = $this->service->buildJournalLines(
            ConsumptionTaxCategory::TaxablePurchase10,
            11000,
            $expense->id,
            $deposit->id,
            false,
            true,
        );

        $this->assertCount(2, $lines);
        $this->assertSame(11000, $lines[0]['debit']);
        $this->assertSame(11000, $lines[1]['credit']);
    }

    public function test_expand_transfer_lines_splits_taxable_sales(): void
    {
        $revenue = Account::findByName('売上高');
        $receivable = Account::where('name', '売掛金')->firstOrFail();
        $outputTax = Account::findByName('仮受消費税');

        $expanded = $this->service->expandTransferLines([
            [
                'account_id' => $receivable->id,
                'debit' => 11000,
                'credit' => 0,
                'consumption_tax_category' => ConsumptionTaxCategory::OutOfScope,
            ],
            [
                'account_id' => $revenue->id,
                'debit' => 0,
                'credit' => 11000,
                'consumption_tax_category' => ConsumptionTaxCategory::TaxableSales10,
            ],
        ], Carbon::parse('2025-05-15'));

        $this->assertCount(3, $expanded);
        $this->assertSame(11000, $expanded[0]['debit']);
        $this->assertSame(10000, $expanded[1]['credit']);
        $this->assertSame($outputTax->id, $expanded[2]['account_id']);
        $this->assertSame(1000, $expanded[2]['credit']);
        $this->assertSame(11000, collect($expanded)->sum('debit'));
        $this->assertSame(11000, collect($expanded)->sum('credit'));
    }

    public function test_expand_transfer_lines_skips_split_when_tax_exempt(): void
    {
        $revenue = Account::findByName('売上高');
        $receivable = Account::where('name', '売掛金')->firstOrFail();

        $expanded = $this->service->expandTransferLines([
            [
                'account_id' => $receivable->id,
                'debit' => 11000,
                'credit' => 0,
                'consumption_tax_category' => ConsumptionTaxCategory::TaxableSales10,
            ],
            [
                'account_id' => $revenue->id,
                'debit' => 0,
                'credit' => 11000,
                'consumption_tax_category' => ConsumptionTaxCategory::TaxableSales10,
            ],
        ], Carbon::parse('2025-05-15'), true);

        $this->assertCount(2, $expanded);
        $this->assertSame(11000, $expanded[0]['debit']);
        $this->assertSame(11000, $expanded[1]['credit']);
        $this->assertSame(ConsumptionTaxCategory::OutOfScope, $expanded[0]['consumption_tax_category']);
    }
}
