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

    public function test_account_seeder_loads_thirty_eight_accounts(): void
    {
        $this->seed(AccountSeeder::class);

        $this->assertEquals(38, Account::count());
        $this->assertEquals(11, Account::ofType(AccountType::Asset)->count());
        $this->assertEquals(6, Account::ofType(AccountType::Liability)->count());
        $this->assertEquals(1, Account::ofType(AccountType::Equity)->count());
        $this->assertEquals(1, Account::ofType(AccountType::Revenue)->count());
        $this->assertEquals(19, Account::ofType(AccountType::Expense)->count());
    }

    public function test_account_seeder_includes_required_accounts(): void
    {
        $this->seed(AccountSeeder::class);

        $this->assertNotNull(Account::where('name', '売上高')->first());
        $this->assertNotNull(Account::where('name', '元入金')->first());
        $this->assertNotNull(Account::where('name', '預金')->first());
        $this->assertNotNull(Account::where('name', '役員借入金')->first());
    }

    public function test_account_seeder_is_idempotent(): void
    {
        $this->seed(AccountSeeder::class);
        $this->seed(AccountSeeder::class);

        $this->assertEquals(38, Account::count());
    }
}
