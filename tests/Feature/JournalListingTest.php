<?php

namespace Tests\Feature;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JournalListingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private FiscalYear $activeFiscalYear;

    private FiscalYear $inactiveFiscalYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);

        $this->user = User::factory()->create();
        $this->company = Company::create(['user_id' => $this->user->id]);

        $this->activeFiscalYear = FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);

        $this->inactiveFiscalYear = FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2024-04-01',
            'end_date' => '2025-03-31',
            'is_active' => false,
        ]);
    }

    public function test_journal_listing_requires_authentication(): void
    {
        $this->get(route('journals.index'))->assertRedirect(route('login'));
    }

    public function test_journal_listing_returns_empty_when_no_entries(): void
    {
        $this->actingAs($this->user)
            ->get(route('journals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('journals/index')
                ->has('entries.data', 0)
            );
    }

    public function test_journal_listing_shows_entries_for_active_fiscal_year_only(): void
    {
        $depositAccount = Account::findByName('預金');
        $revenueAccount = Account::findByName('売上高');

        $activeEntry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->activeFiscalYear->id,
            'entry_date' => '2025-05-01',
            'description' => '売上入金',
            'source' => JournalSource::Manual,
        ]);

        JournalLine::create([
            'journal_entry_id' => $activeEntry->id,
            'account_id' => $depositAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        JournalLine::create([
            'journal_entry_id' => $activeEntry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 100000,
        ]);

        $inactiveEntry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->inactiveFiscalYear->id,
            'entry_date' => '2024-05-01',
            'description' => '旧年度仕訳',
            'source' => JournalSource::Manual,
        ]);

        JournalLine::create([
            'journal_entry_id' => $inactiveEntry->id,
            'account_id' => $depositAccount->id,
            'debit' => 50000,
            'credit' => 0,
        ]);

        JournalLine::create([
            'journal_entry_id' => $inactiveEntry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 50000,
        ]);

        $this->actingAs($this->user)
            ->get(route('journals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('journals/index')
                ->has('entries.data', 1)
                ->where('entries.data.0.description', '売上入金')
                ->where('entries.data.0.total_amount', 100000)
                ->where('entries.data.0.debit_account_name', '預金')
                ->where('entries.data.0.credit_account_name', '売上高')
            );
    }
}
