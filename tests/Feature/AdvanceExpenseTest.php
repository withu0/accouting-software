<?php

namespace Tests\Feature;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ConsumptionTaxPayload;
use Tests\TestCase;

class AdvanceExpenseTest extends TestCase
{
    use ConsumptionTaxPayload;
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private FiscalYear $activeFiscalYear;

    private Account $expenseAccount;

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

        $this->expenseAccount = Account::where('name', '会議費')->firstOrFail();
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('advance-expenses'))->assertRedirect(route('login'));
    }

    public function test_create_posts_balanced_advance_expense_journal(): void
    {
        $officerLoanAccount = Account::where('name', '役員借入金')->firstOrFail();
        $inputTaxAccount = Account::where('name', '仮払消費税')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('advance-expenses.store'), array_merge([
                'entry_date' => '2025-05-15',
                'amount' => 5000,
                'description' => '会議費用',
                'account_id' => $this->expenseAccount->id,
            ], $this->purchaseTaxPayload()))
            ->assertRedirect();

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $this->company->id,
            'description' => '会議費用',
            'source' => JournalSource::AdvanceExpense->value,
        ]);

        $entry = JournalEntry::where('description', '会議費用')->firstOrFail();
        $this->assertCount(3, $entry->lines);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $this->expenseAccount->id,
            'debit' => 4546,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inputTaxAccount->id,
            'debit' => 454,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $officerLoanAccount->id,
            'debit' => 0,
            'credit' => 5000,
        ]);
    }

    public function test_non_expense_account_is_rejected(): void
    {
        $depositAccount = Account::where('name', '預金')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('advance-expenses.store'), array_merge([
                'entry_date' => '2025-05-15',
                'amount' => 5000,
                'description' => 'テスト',
                'account_id' => $depositAccount->id,
            ], $this->purchaseTaxPayload()))
            ->assertSessionHasErrors('account_id');
    }

    public function test_list_shows_only_active_fiscal_year_advance_expenses(): void
    {
        $officerLoanAccount = Account::where('name', '役員借入金')->firstOrFail();

        $activeEntry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->activeFiscalYear->id,
            'entry_date' => '2025-05-01',
            'description' => '当期の立替',
            'source' => JournalSource::AdvanceExpense,
        ]);
        $activeEntry->lines()->createMany([
            ['account_id' => $this->expenseAccount->id, 'debit' => 3000, 'credit' => 0],
            ['account_id' => $officerLoanAccount->id, 'debit' => 0, 'credit' => 3000],
        ]);

        $inactiveFiscalYear = FiscalYear::create([
            'company_id' => $this->company->id,
            'start_date' => '2024-04-01',
            'end_date' => '2025-03-31',
            'is_active' => false,
        ]);

        $inactiveEntry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $inactiveFiscalYear->id,
            'entry_date' => '2024-05-01',
            'description' => '前期の立替',
            'source' => JournalSource::AdvanceExpense,
        ]);
        $inactiveEntry->lines()->createMany([
            ['account_id' => $this->expenseAccount->id, 'debit' => 2000, 'credit' => 0],
            ['account_id' => $officerLoanAccount->id, 'debit' => 0, 'credit' => 2000],
        ]);

        $this->actingAs($this->user)
            ->get(route('advance-expenses'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advance-expenses/index')
                ->has('entries', 1)
                ->where('entries.0.description', '当期の立替')
            );
    }

    public function test_delete_removes_entry_and_lines(): void
    {
        $officerLoanAccount = Account::where('name', '役員借入金')->firstOrFail();

        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->activeFiscalYear->id,
            'entry_date' => '2025-05-01',
            'description' => '削除対象',
            'source' => JournalSource::AdvanceExpense,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $this->expenseAccount->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $officerLoanAccount->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->actingAs($this->user)
            ->delete(route('advance-expenses.destroy', $entry))
            ->assertRedirect();

        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
        $this->assertDatabaseCount('journal_lines', 0);
    }

    public function test_date_outside_fiscal_year_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('advance-expenses.store'), array_merge([
                'entry_date' => '2027-01-01',
                'amount' => 5000,
                'description' => 'テスト',
                'account_id' => $this->expenseAccount->id,
            ], $this->purchaseTaxPayload()))
            ->assertSessionHasErrors('entry_date');
    }

    public function test_update_changes_journal_lines(): void
    {
        $officerLoanAccount = Account::where('name', '役員借入金')->firstOrFail();
        $inputTaxAccount = Account::where('name', '仮払消費税')->firstOrFail();
        $newExpenseAccount = Account::where('name', '通信費')->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('advance-expenses.store'), array_merge([
                'entry_date' => '2025-05-15',
                'amount' => 5000,
                'description' => '会議費用',
                'account_id' => $this->expenseAccount->id,
            ], $this->purchaseTaxPayload()))
            ->assertRedirect();

        $entry = JournalEntry::where('description', '会議費用')->firstOrFail();

        $this->actingAs($this->user)
            ->patch(route('advance-expenses.update', $entry), array_merge([
                'entry_date' => '2025-06-01',
                'amount' => 11000,
                'description' => '通信費用',
                'account_id' => $newExpenseAccount->id,
            ], $this->purchaseTaxPayload()))
            ->assertRedirect();

        $entry->refresh();
        $this->assertSame('2025-06-01', $entry->entry_date->format('Y-m-d'));
        $this->assertSame('通信費用', $entry->description);
        $this->assertCount(3, $entry->lines);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $newExpenseAccount->id,
            'debit' => 10000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inputTaxAccount->id,
            'debit' => 1000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $officerLoanAccount->id,
            'debit' => 0,
            'credit' => 11000,
        ]);
    }

    public function test_update_rejects_wrong_source(): void
    {
        $officerLoanAccount = Account::where('name', '役員借入金')->firstOrFail();
        $depositAccount = Account::where('name', '預金')->firstOrFail();

        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->activeFiscalYear->id,
            'entry_date' => '2025-05-01',
            'description' => '銀行CSV取込',
            'source' => JournalSource::BankCsv,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $depositAccount->id, 'debit' => 5000, 'credit' => 0],
            ['account_id' => $officerLoanAccount->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $this->actingAs($this->user)
            ->patch(route('advance-expenses.update', $entry), array_merge([
                'entry_date' => '2025-05-15',
                'amount' => 5000,
                'description' => 'テスト',
                'account_id' => $this->expenseAccount->id,
            ], $this->purchaseTaxPayload()))
            ->assertNotFound();
    }

    public function test_update_validates_expense_account(): void
    {
        $officerLoanAccount = Account::where('name', '役員借入金')->firstOrFail();
        $depositAccount = Account::where('name', '預金')->firstOrFail();

        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->activeFiscalYear->id,
            'entry_date' => '2025-05-01',
            'description' => '更新対象',
            'source' => JournalSource::AdvanceExpense,
        ]);
        $entry->lines()->createMany([
            ['account_id' => $this->expenseAccount->id, 'debit' => 5000, 'credit' => 0],
            ['account_id' => $officerLoanAccount->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $this->actingAs($this->user)
            ->patch(route('advance-expenses.update', $entry), array_merge([
                'entry_date' => '2025-05-15',
                'amount' => 5000,
                'description' => 'テスト',
                'account_id' => $depositAccount->id,
            ], $this->purchaseTaxPayload()))
            ->assertSessionHasErrors('account_id');
    }
}
