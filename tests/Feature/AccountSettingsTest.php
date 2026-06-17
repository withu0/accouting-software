<?php

namespace Tests\Feature;

use App\Enums\AccountType;
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

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);
        $this->user = User::factory()->create();
        Company::create(['user_id' => $this->user->id]);
    }

    public function test_accounts_page_loads_with_seeded_accounts(): void
    {
        $this->actingAs($this->user)
            ->get(route('accounts.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/accounts')
                ->has('accountGroups')
                ->has('accountTypes', 5)
            );
    }

    public function test_user_can_create_expense_account(): void
    {
        $response = $this->actingAs($this->user)->post(route('accounts.store'), [
            'code' => '5120',
            'name' => 'テスト経費科目',
            'type' => AccountType::Expense->value,
        ]);

        $response->assertRedirect(route('accounts.edit'));
        $this->assertDatabaseHas('accounts', [
            'code' => '5120',
            'name' => 'テスト経費科目',
            'type' => AccountType::Expense->value,
        ]);
    }

    public function test_user_can_update_account_name(): void
    {
        $account = Account::where('name', '雑費')->firstOrFail();

        $response = $this->actingAs($this->user)->patch(route('accounts.update', $account), [
            'code' => $account->code,
            'name' => '雑費（更新）',
            'type' => $account->type->value,
            'display_order' => $account->display_order,
        ]);

        $response->assertRedirect(route('accounts.edit'));
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => '雑費（更新）',
        ]);
    }

    public function test_user_can_delete_unused_account(): void
    {
        $account = Account::create([
            'code' => '5999',
            'name' => '削除テスト科目',
            'type' => AccountType::Expense,
            'display_order' => 99,
        ]);

        $response = $this->actingAs($this->user)->delete(route('accounts.destroy', $account));

        $response->assertRedirect(route('accounts.edit'));
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    public function test_user_cannot_delete_account_used_in_journal(): void
    {
        $company = Company::where('user_id', $this->user->id)->firstOrFail();
        $fiscalYear = FiscalYear::create([
            'company_id' => $company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);

        $expenseAccount = Account::where('name', '消耗品費')->firstOrFail();
        $depositAccount = Account::where('name', '預金')->firstOrFail();

        $entry = JournalEntry::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYear->id,
            'entry_date' => '2025-04-01',
            'description' => 'テスト仕訳',
            'source' => JournalSource::BankCsv,
        ]);

        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $expenseAccount->id,
            'debit' => 1000,
            'credit' => 0,
        ]);
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $depositAccount->id,
            'debit' => 0,
            'credit' => 1000,
        ]);

        $response = $this->actingAs($this->user)->delete(route('accounts.destroy', $expenseAccount));

        $response->assertRedirect(route('accounts.edit'));
        $response->assertSessionHasErrors('account');
        $this->assertDatabaseHas('accounts', ['id' => $expenseAccount->id]);
    }
}
