<?php

namespace Tests\Unit;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\ConsumptionTaxService;
use App\Services\ReportService;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private FiscalYear $fiscalYear;

    private ReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);
        $this->reportService = app(ReportService::class);

        $user = User::factory()->create();
        $this->company = Company::create(['user_id' => $user->id, 'name' => 'テスト株式会社']);

        $this->fiscalYear = FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);

        $this->seedSampleJournals();
    }

    public function test_profit_and_loss_totals_match_journal_sums(): void
    {
        $pl = $this->reportService->profitAndLoss($this->company, $this->fiscalYear);

        $this->assertSame(120000, $pl['total_revenue']);
        $this->assertSame(15000, $pl['total_expense']);
        $this->assertSame(105000, $pl['net_income']);
    }

    public function test_profit_and_loss_rows_include_account_code(): void
    {
        $pl = $this->reportService->profitAndLoss($this->company, $this->fiscalYear);

        $this->assertNotEmpty($pl['revenue_rows']);
        $this->assertArrayHasKey('account_code', $pl['revenue_rows'][0]);
        $this->assertNotEmpty($pl['expense_rows']);
        $this->assertArrayHasKey('account_code', $pl['expense_rows'][0]);
    }

    public function test_balance_sheet_is_balanced_with_net_income_on_equity(): void
    {
        $bs = $this->reportService->balanceSheet($this->company, $this->fiscalYear);

        $this->assertTrue($bs['is_balanced']);
        $this->assertSame($bs['total_assets'], $bs['total_liabilities_and_equity']);
        $this->assertContains('当期純利益', array_column($bs['equity_rows'], 'account_name'));
    }

    public function test_journal_book_lists_entries_in_date_order(): void
    {
        $journal = $this->reportService->journalBook($this->company, $this->fiscalYear);

        $this->assertCount(4, $journal['entries']);
        $this->assertSame('2025-04-01', $journal['entries'][0]['entry_date']);
        $this->assertSame('2025-04-15', $journal['entries'][1]['entry_date']);
        $this->assertSame('2025-05-01', $journal['entries'][2]['entry_date']);
        $this->assertSame('2025-05-10', $journal['entries'][3]['entry_date']);
        $this->assertArrayHasKey('voucher_no', $journal['entries'][0]);
        $this->assertArrayHasKey('account_code', $journal['entries'][0]['lines'][0]);
    }

    public function test_general_ledger_running_balance_for_deposit_account(): void
    {
        $ledger = $this->reportService->generalLedger($this->company, $this->fiscalYear);

        $depositAccount = collect($ledger['accounts'])->firstWhere('account_name', '預金');
        $this->assertNotNull($depositAccount);
        $this->assertSame(95000, $depositAccount['closing_balance']);
        $this->assertArrayHasKey('account_code', $depositAccount);
    }

    public function test_taxed_posting_shows_net_on_pl_and_tax_accounts_on_bs(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['user_id' => $user->id, 'name' => '税込テスト株式会社']);
        $fiscalYear = FiscalYear::create([
            'company_id' => $company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);

        $consumptionTaxService = app(ConsumptionTaxService::class);
        $deposit = Account::findByName('預金');
        $revenue = Account::findByName('売上高');
        $expense = Account::where('name', '会議費')->firstOrFail();

        $revenueEntry = JournalEntry::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => '2025-06-01',
            'description' => '税込売上',
            'source' => JournalSource::BankCsv,
        ]);
        $revenueEntry->lines()->createMany(
            $consumptionTaxService->buildTaxableRevenueLines(11000, $deposit->id, $revenue->id),
        );

        $expenseEntry = JournalEntry::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => '2025-06-02',
            'description' => '税込経費',
            'source' => JournalSource::BankCsv,
        ]);
        $expenseEntry->lines()->createMany(
            $consumptionTaxService->buildTaxableExpenseLines(11000, $expense->id, $deposit->id),
        );

        $pl = $this->reportService->profitAndLoss($company, $fiscalYear);
        $bs = $this->reportService->balanceSheet($company, $fiscalYear);

        $this->assertSame(10000, collect($pl['revenue_rows'])->firstWhere('account_name', '売上高')['amount']);
        $this->assertSame(10000, collect($pl['expense_rows'])->firstWhere('account_name', '会議費')['amount']);
        $this->assertSame(1000, collect($bs['liability_rows'])->firstWhere('account_name', '仮受消費税')['amount']);
        $this->assertSame(1000, collect($bs['asset_rows'])->firstWhere('account_name', '仮払消費税')['amount']);
    }

    private function seedSampleJournals(): void
    {
        $deposit = Account::findByName('預金');
        $revenue = Account::findByName('売上高');
        $expense = Account::where('name', '消耗品費')->firstOrFail();
        $officerLoan = Account::findByName('役員借入金');
        $receivable = Account::findByName('売掛金');

        $this->createEntry('2025-04-01', '売上入金', JournalSource::BankCsv, [
            ['account_id' => $deposit->id, 'debit' => 100000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100000],
        ]);

        $this->createEntry('2025-04-15', '消耗品購入', JournalSource::BankCsv, [
            ['account_id' => $expense->id, 'debit' => 5000, 'credit' => 0],
            ['account_id' => $deposit->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $this->createEntry('2025-05-01', '立替経費', JournalSource::AdvanceExpense, [
            ['account_id' => $expense->id, 'debit' => 10000, 'credit' => 0],
            ['account_id' => $officerLoan->id, 'debit' => 0, 'credit' => 10000],
        ]);

        $this->createEntry('2025-05-10', '売掛金計上', JournalSource::Transfer, [
            ['account_id' => $receivable->id, 'debit' => 20000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 20000],
        ]);
    }

    /**
     * @param  array<int, array{account_id: int, debit: int, credit: int}>  $lines
     */
    private function createEntry(string $date, string $description, JournalSource $source, array $lines): void
    {
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => $date,
            'description' => $description,
            'source' => $source,
        ]);

        $entry->lines()->createMany($lines);
    }
}
