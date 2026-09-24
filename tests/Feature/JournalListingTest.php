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
        $depositAccount = Account::findByName('普通預金');
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
                ->where('entries.data.0.debit_account_name', '普通預金')
                ->where('entries.data.0.credit_account_name', '売上高')
                ->has('entries.data.0.lines', 2)
                ->where('entries.data.0.lines.0.account_name', '普通預金')
                ->where('entries.data.0.lines.0.debit', 100000)
                ->where('entries.data.0.lines.1.account_name', '売上高')
                ->where('entries.data.0.lines.1.credit', 100000)
            );
    }

    public function test_journal_listing_includes_all_split_tax_lines(): void
    {
        $expenseAccount = Account::where('name', '水道光熱費')->firstOrFail();
        $inputTaxAccount = Account::where('name', '仮払消費税')->firstOrFail();
        $officerLoanAccount = Account::where('name', '役員借入金')->firstOrFail();

        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->activeFiscalYear->id,
            'entry_date' => '2025-05-15',
            'description' => '立替の税分解',
            'source' => JournalSource::AdvanceExpense,
        ]);

        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $expenseAccount->id,
            'debit' => 1000,
            'credit' => 0,
        ]);
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $inputTaxAccount->id,
            'debit' => 100,
            'credit' => 0,
        ]);
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $officerLoanAccount->id,
            'debit' => 0,
            'credit' => 1100,
        ]);

        $this->actingAs($this->user)
            ->get(route('journals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('journals/index')
                ->has('entries.data', 1)
                ->has('entries.data.0.lines', 3)
                ->where('entries.data.0.lines.0.account_name', '水道光熱費')
                ->where('entries.data.0.lines.0.debit', 1000)
                ->where('entries.data.0.lines.1.account_name', '仮払消費税')
                ->where('entries.data.0.lines.1.debit', 100)
                ->where('entries.data.0.lines.2.account_name', '役員借入金')
                ->where('entries.data.0.lines.2.credit', 1100)
            );
    }
}
