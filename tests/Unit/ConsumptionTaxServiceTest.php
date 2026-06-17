<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Services\ConsumptionTaxService;
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

    public function test_rate_percent_reads_config(): void
    {
        $this->assertSame(10, $this->service->ratePercent());
    }

    public function test_split_inclusive_standard_amount(): void
    {
        $split = $this->service->splitInclusive(11000);

        $this->assertSame(10000, $split['net']);
        $this->assertSame(1000, $split['tax']);
    }

    public function test_split_inclusive_small_amount(): void
    {
        $split = $this->service->splitInclusive(100);

        $this->assertSame(91, $split['net']);
        $this->assertSame(9, $split['tax']);
    }

    public function test_split_inclusive_one_yen(): void
    {
        $split = $this->service->splitInclusive(1);

        $this->assertSame(1, $split['net']);
        $this->assertSame(0, $split['tax']);
    }

    public function test_build_taxable_revenue_lines(): void
    {
        $deposit = Account::findByName('預金');
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
        $this->assertSame(11000, array_sum(array_column($lines, 'debit')));
        $this->assertSame(11000, array_sum(array_column($lines, 'credit')));
    }

    public function test_build_taxable_expense_lines(): void
    {
        $expense = Account::where('name', '消耗品費')->firstOrFail();
        $deposit = Account::findByName('預金');
        $inputTax = Account::findByName('仮払消費税');

        $lines = $this->service->buildTaxableExpenseLines(11000, $expense->id, $deposit->id);

        $this->assertCount(3, $lines);
        $this->assertSame($expense->id, $lines[0]['account_id']);
        $this->assertSame(10000, $lines[0]['debit']);
        $this->assertSame($inputTax->id, $lines[1]['account_id']);
        $this->assertSame(1000, $lines[1]['debit']);
        $this->assertSame($deposit->id, $lines[2]['account_id']);
        $this->assertSame(11000, $lines[2]['credit']);
        $this->assertSame(11000, array_sum(array_column($lines, 'debit')));
        $this->assertSame(11000, array_sum(array_column($lines, 'credit')));
    }
}
