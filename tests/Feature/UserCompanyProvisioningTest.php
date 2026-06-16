<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserCompanyProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_company_can_access_feature_pages(): void
    {
        $this->seed(AccountSeeder::class);

        $user = User::factory()->create();

        $this->assertNull($user->company);

        $this->actingAs($user)
            ->get(route('bank-import'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('bank-import/index'));

        $this->assertNotNull($user->fresh()->company);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_ensure_company_is_idempotent(): void
    {
        $user = User::factory()->create();

        $first = $user->ensureCompany();
        $second = $user->fresh()->ensureCompany();

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_existing_company_is_not_recreated(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['user_id' => $user->id, 'name' => '既存会社']);

        $resolved = $user->fresh()->ensureCompany();

        $this->assertSame($company->id, $resolved->id);
        $this->assertSame('既存会社', $resolved->name);
        $this->assertDatabaseCount('companies', 1);
    }
}
