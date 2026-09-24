<?php

namespace Tests\Feature;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YearEndCloseTest extends TestCase
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

    public function test_close_carries_balances_and_rolls_net_income_into_retained_earnings(): void
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

        $this->actingAs($this->user)
            ->post(route('fiscal-year.close', $this->fiscalYear))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->fiscalYear->refresh();
        $this->assertNotNull($this->fiscalYear->closed_at);
        $this->assertTrue($this->fiscalYear->is_active);

        $nextYear = $this->company->fiscalYears()->where('id', '!=', $this->fiscalYear->id)->first();
        $this->assertNotNull($nextYear);
        $this->assertFalse($nextYear->is_active);
        $this->assertSame('2027-04-01', $nextYear->start_date->format('Y-m-d'));
        $this->assertSame('2028-03-31', $nextYear->end_date->format('Y-m-d'));

        $this->company->fiscalYears()->update(['is_active' => false]);
        $nextYear->update(['is_active' => true]);

        $bs = app(ReportService::class)->balanceSheet($this->company->fresh(), $nextYear->fresh());
        $this->assertTrue($bs['is_balanced']);
        $this->assertSame(5_500_000, collect($bs['asset_rows'])->firstWhere('account_name', '普通預金')['amount']);
        $this->assertSame(1_000_000, collect($bs['equity_rows'])->firstWhere('account_name', '資本金')['amount']);
        $this->assertSame(4_500_000, collect($bs['equity_rows'])->firstWhere('account_name', '繰越利益剰余金')['amount']);
        $this->assertNotContains('当期純利益', array_column($bs['equity_rows'], 'account_name'));
    }
}
