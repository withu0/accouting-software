<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_seeder_loads_corporate_chart(): void
    {
        $this->seed(AccountSeeder::class);

        $this->assertEquals(55, Account::count());
        $this->assertEquals(21, Account::ofType(AccountType::Asset)->count());
        $this->assertEquals(10, Account::ofType(AccountType::Liability)->count());
        $this->assertEquals(4, Account::ofType(AccountType::Equity)->count());
        $this->assertEquals(1, Account::ofType(AccountType::Revenue)->count());
        $this->assertEquals(19, Account::ofType(AccountType::Expense)->count());
    }

    public function test_account_seeder_includes_required_accounts(): void
    {
        $this->seed(AccountSeeder::class);

        $this->assertNotNull(Account::where('name', '売上高')->first());
        $this->assertNotNull(Account::where('name', '資本金')->first());
        $this->assertNotNull(Account::where('name', '繰越利益剰余金')->first());
        $this->assertNotNull(Account::where('name', '普通預金')->first());
        $this->assertNotNull(Account::where('name', '現金')->first());
        $this->assertNotNull(Account::where('name', '役員借入金')->first());
        $this->assertNotNull(Account::where('name', '減価償却累計額')->first());
        $this->assertNotNull(Account::where('name', '仮払消費税')->first());
        $this->assertNotNull(Account::where('name', '仮受消費税')->first());
        $this->assertTrue(Account::where('name', '減価償却累計額')->first()?->is_contra);
        $this->assertTrue(Account::where('name', '自己株式')->first()?->is_contra);
        $this->assertNull(Account::where('name', '元入金')->first());
        $this->assertNull(Account::where('name', '預金')->first());
    }

    public function test_account_seeder_is_idempotent(): void
    {
        $this->seed(AccountSeeder::class);
        $this->seed(AccountSeeder::class);

        $this->assertEquals(55, Account::count());
    }
}
