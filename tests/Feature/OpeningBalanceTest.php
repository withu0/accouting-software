<?php

namespace Tests\Feature;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\OpeningBalanceService;
use App\Services\ReportService;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
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

    public function test_unbalanced_opening_cannot_be_confirmed(): void
    {
        $deposit = Account::findByName('普通預金');
        $capital = Account::findByName('資本金');

        $this->actingAs($this->user)
            ->post(route('opening-balances.confirm'), [
                'lines' => [
                    ['account_id' => $deposit->id, 'amount' => 1000000],
                    ['account_id' => $capital->id, 'amount' => 800000],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseMissing('journal_entries', [
            'source' => JournalSource::OpeningBalance->value,
        ]);
    }

    public function test_balanced_opening_posts_journal_and_balance_sheet(): void
    {
        $payload = $this->balancedPayload(deposit: 5_000_000, capital: 1_000_000, retained: 4_000_000);

        $this->actingAs($this->user)
            ->post(route('opening-balances.confirm'), ['lines' => $payload])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'source' => JournalSource::OpeningBalance->value,
            'description' => '開始残高',
        ]);

        $bs = app(ReportService::class)->balanceSheet($this->company, $this->fiscalYear);
        $this->assertTrue($bs['is_balanced']);
        $this->assertSame(5_000_000, collect($bs['asset_rows'])->firstWhere('account_name', '普通預金')['amount']);
        $this->assertSame(1_000_000, collect($bs['equity_rows'])->firstWhere('account_name', '資本金')['amount']);
        $this->assertSame(4_000_000, collect($bs['equity_rows'])->firstWhere('account_name', '繰越利益剰余金')['amount']);
    }

    public function test_ledger_uses_opening_journal_as_carried_forward_balance(): void
    {
        $this->actingAs($this->user)
            ->post(route('opening-balances.confirm'), [
                'lines' => $this->balancedPayload(deposit: 1_000_000, capital: 1_000_000, retained: 0),
            ]);

        $revenue = Account::findByName('売上高');
        $deposit = Account::findByName('普通預金');
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => '2026-05-01',
            'description' => '売上入金',
            'source' => JournalSource::BankCsv,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $deposit->id, 'debit' => 100000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100000],
        ]);

        $ledger = app(ReportService::class)->generalLedger($this->company, $this->fiscalYear);
        $depositLedger = collect($ledger['accounts'])->firstWhere('account_name', '普通預金');

        $this->assertSame(1_000_000, $depositLedger['opening_balance']);
        $this->assertSame(1_100_000, $depositLedger['closing_balance']);
        $this->assertCount(1, $depositLedger['lines']);
        $this->assertSame('売上入金', $depositLedger['lines'][0]['description']);
    }

    public function test_draft_save_does_not_post_journal(): void
    {
        $this->actingAs($this->user)
            ->put(route('opening-balances.save'), [
                'lines' => $this->balancedPayload(deposit: 1_000_000, capital: 1_000_000, retained: 0),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('opening_balances', [
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'confirmed_at' => null,
        ]);
        $this->assertDatabaseMissing('journal_entries', [
            'source' => JournalSource::OpeningBalance->value,
        ]);
    }

    public function test_form_warns_when_period_journals_exist(): void
    {
        $deposit = Account::findByName('普通預金');
        $revenue = Account::findByName('売上高');
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => '2026-05-01',
            'description' => '売上入金',
            'source' => JournalSource::BankCsv,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $deposit->id, 'debit' => 100000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100000],
        ]);

        $state = app(OpeningBalanceService::class)->formState($this->company, $this->fiscalYear);
        $this->assertTrue($state['has_period_journals']);
    }

    /**
     * @return list<array{account_id: int, amount: int}>
     */
    private function balancedPayload(int $deposit, int $capital, int $retained): array
    {
        return [
            ['account_id' => Account::findByName('普通預金')->id, 'amount' => $deposit],
            ['account_id' => Account::findByName('資本金')->id, 'amount' => $capital],
            ['account_id' => Account::findByName('繰越利益剰余金')->id, 'amount' => $retained],
        ];
    }
}
