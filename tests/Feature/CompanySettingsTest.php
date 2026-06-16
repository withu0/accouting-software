<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_is_auto_created_on_registration(): void
    {
        $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'yamada@example.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('companies', ['user_id' => $user->id]);
    }

    public function test_authenticated_user_can_update_company_settings(): void
    {
        $user = User::factory()->create();
        Company::create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patch(route('company.update'), [
            'name' => '株式会社テスト',
            'representative_name' => '山田太郎',
            'address' => '東京都千代田区丸の内1-1-1',
            'fiscal_year_start_month' => 4,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('companies', [
            'user_id' => $user->id,
            'name' => '株式会社テスト',
            'representative_name' => '山田太郎',
            'address' => '東京都千代田区丸の内1-1-1',
            'fiscal_year_start_month' => 4,
        ]);
    }

    public function test_user_can_create_active_fiscal_year(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('fiscal-year.store'), [
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
        ]);

        $response->assertRedirect();

        $fiscalYear = FiscalYear::where('company_id', $company->id)->first();
        $this->assertNotNull($fiscalYear);
        $this->assertEquals('2025-04-01', $fiscalYear->start_date->toDateString());
        $this->assertEquals('2026-03-31', $fiscalYear->end_date->toDateString());
        $this->assertTrue($fiscalYear->is_active);
    }

    public function test_only_one_fiscal_year_is_active_at_a_time(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['user_id' => $user->id]);

        $first = FiscalYear::create([
            'company_id' => $company->id,
            'start_date' => '2024-04-01',
            'end_date' => '2025-03-31',
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('fiscal-year.store'), [
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
        ]);

        $this->assertDatabaseHas('fiscal_years', [
            'id' => $first->id,
            'is_active' => false,
        ]);

        $this->assertEquals(1, FiscalYear::where('company_id', $company->id)->where('is_active', true)->count());
    }

    public function test_user_can_update_fiscal_year_and_activate_it(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['user_id' => $user->id]);

        $older = FiscalYear::create([
            'company_id' => $company->id,
            'start_date' => '2024-04-01',
            'end_date' => '2025-03-31',
            'is_active' => true,
        ]);

        $target = FiscalYear::create([
            'company_id' => $company->id,
            'start_date' => '2023-04-01',
            'end_date' => '2024-03-31',
            'is_active' => false,
        ]);

        $this->actingAs($user)->patch(route('fiscal-year.update', $target), [
            'start_date' => '2023-04-01',
            'end_date' => '2024-03-31',
        ]);

        $this->assertDatabaseHas('fiscal_years', [
            'id' => $target->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('fiscal_years', [
            'id' => $older->id,
            'is_active' => false,
        ]);
    }
}
