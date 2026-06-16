<?php

namespace Tests\Unit;

use App\Enums\AccountType;
use App\Models\Account;
use Database\Seeders\AccountSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);
    }

    public function test_find_by_name_returns_matching_account(): void
    {
        $account = Account::findByName('預金');

        $this->assertEquals('預金', $account->name);
        $this->assertEquals(AccountType::Asset, $account->type);
    }

    public function test_find_by_name_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Account::findByName('存在しない科目');
    }

    public function test_expense_accounts_returns_only_expense_accounts(): void
    {
        $accounts = Account::expenseAccounts();

        $this->assertGreaterThan(0, $accounts->count());
        $this->assertTrue($accounts->every(fn (Account $account) => $account->type === AccountType::Expense));
    }

    public function test_expense_accounts_are_ordered_by_display_order(): void
    {
        $accounts = Account::expenseAccounts();
        $orders = $accounts->pluck('display_order')->all();

        $this->assertEquals($orders, collect($orders)->sort()->values()->all());
    }
}
