<?php

namespace Tests\Feature;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\EquityStatementService;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquityStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private FiscalYear $fiscalYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);
        $this->user = User::factory()->create();
        $this->company = Company::create(['user_id' => $this->user->id, 'name' => 'テスト株式会社']);
        $this->fiscalYear = FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_active' => true,
        ]);
    }

    public function test_statement_starts_from_opening_equity_and_adds_net_income(): void
    {
        $this->actingAs($this->user)->post(route('opening-balances.confirm'), [
            'lines' => [
                ['account_id' => Account::findByName('普通預金')->id, 'amount' => 4_000_000],
                ['account_id' => Account::findByName('資本金')->id, 'amount' => 1_000_000],
                ['account_id' => Account::findByName('繰越利益剰余金')->id, 'amount' => 3_000_000],
            ],
        ]);

        $deposit = Account::findByName('普通預金');
        $revenue = Account::findByName('売上高');
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => '2026-06-01',
            'description' => '売上',
            'source' => JournalSource::BankCsv,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $deposit->id, 'debit' => 1_500_000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1_500_000],
        ]);

        $statement = app(EquityStatementService::class)->build($this->company, $this->fiscalYear);

        $this->assertTrue($statement['is_consistent']);
        $beginning = collect($statement['rows'])->firstWhere('kind', 'beginning');
        $this->assertSame(1_000_000, $beginning['capital']);
        $this->assertSame(3_000_000, $beginning['retained_earnings']);

        $netIncomeRow = collect($statement['rows'])->firstWhere('kind', 'net_income');
        $this->assertSame(1_500_000, $netIncomeRow['retained_earnings']);

        $ending = collect($statement['rows'])->firstWhere('kind', 'ending');
        $this->assertSame(4_500_000, $ending['retained_earnings']);
        $this->assertSame(5_500_000, $ending['total']);
    }

    public function test_capital_increase_journal_appears_as_issuance_row(): void
    {
        $this->actingAs($this->user)->post(route('opening-balances.confirm'), [
            'lines' => [
                ['account_id' => Account::findByName('普通預金')->id, 'amount' => 1_000_000],
                ['account_id' => Account::findByName('資本金')->id, 'amount' => 1_000_000],
                ['account_id' => Account::findByName('繰越利益剰余金')->id, 'amount' => 0],
            ],
        ]);

        $deposit = Account::findByName('普通預金');
        $capital = Account::findByName('資本金');
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => '2026-07-01',
            'description' => '増資',
            'source' => JournalSource::Transfer,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $deposit->id, 'debit' => 1_000_000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1_000_000],
        ]);

        $statement = app(EquityStatementService::class)->build($this->company, $this->fiscalYear);
        $issuance = collect($statement['rows'])->firstWhere('label', '新株の発行（増資）');

        $this->assertNotNull($issuance);
        $this->assertSame(1_000_000, $issuance['capital']);
        $this->assertTrue($statement['is_consistent']);
        $this->assertFalse($statement['is_manual']);
    }

    public function test_manual_rows_replace_auto_classification_and_keep_net_income(): void
    {
        $this->actingAs($this->user)->post(route('opening-balances.confirm'), [
            'lines' => [
                ['account_id' => Account::findByName('普通預金')->id, 'amount' => 1_000_000],
                ['account_id' => Account::findByName('資本金')->id, 'amount' => 1_000_000],
                ['account_id' => Account::findByName('繰越利益剰余金')->id, 'amount' => 0],
            ],
        ]);

        $deposit = Account::findByName('普通預金');
        $capital = Account::findByName('資本金');
        $revenue = Account::findByName('売上高');

        $increase = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => '2026-07-01',
            'description' => '増資',
            'source' => JournalSource::Transfer,
        ]);
        $increase->lines()->createMany([
            ['account_id' => $deposit->id, 'debit' => 1_000_000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1_000_000],
        ]);

        $sale = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => '2026-08-01',
            'description' => '売上',
            'source' => JournalSource::BankCsv,
        ]);
        $sale->lines()->createMany([
            ['account_id' => $deposit->id, 'debit' => 200_000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 200_000],
        ]);

        $service = app(EquityStatementService::class);
        $service->saveManual($this->company, $this->fiscalYear, [
            [
                'label' => 'その他',
                'capital' => 1_000_000,
                'capital_surplus' => 0,
                'retained_earnings' => 0,
                'treasury' => 0,
            ],
        ]);

        $statement = $service->build($this->company, $this->fiscalYear);

        $this->assertTrue($statement['is_manual']);
        $this->assertNull(collect($statement['rows'])->firstWhere('label', '新株の発行（増資）'));
        $this->assertSame(1_000_000, collect($statement['rows'])->firstWhere('label', 'その他')['capital']);
        $this->assertSame(200_000, collect($statement['rows'])->firstWhere('kind', 'net_income')['retained_earnings']);
        $this->assertTrue($statement['is_consistent']);
    }

    public function test_revert_restores_auto_classification(): void
    {
        $this->actingAs($this->user)->post(route('opening-balances.confirm'), [
            'lines' => [
                ['account_id' => Account::findByName('普通預金')->id, 'amount' => 1_000_000],
                ['account_id' => Account::findByName('資本金')->id, 'amount' => 1_000_000],
            ],
        ]);

        $deposit = Account::findByName('普通預金');
        $capital = Account::findByName('資本金');
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => '2026-07-01',
            'description' => '増資',
            'source' => JournalSource::Transfer,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $deposit->id, 'debit' => 1_000_000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1_000_000],
        ]);

        $service = app(EquityStatementService::class);
        $service->saveManual($this->company, $this->fiscalYear, [
            [
                'label' => '手動調整',
                'capital' => 1_000_000,
                'capital_surplus' => 0,
                'retained_earnings' => 0,
                'treasury' => 0,
            ],
        ]);
        $service->revertToAuto($this->company, $this->fiscalYear);

        $statement = $service->build($this->company, $this->fiscalYear);

        $this->assertFalse($statement['is_manual']);
        $this->assertNotNull(collect($statement['rows'])->firstWhere('label', '新株の発行（増資）'));
        $this->assertNull(collect($statement['rows'])->firstWhere('label', '手動調整'));
    }
}
