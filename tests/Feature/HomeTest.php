<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_landing_page_at_home(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_authenticated_users_see_home_page(): void
    {
        $user = User::factory()->create();
        Company::create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('home'));
    }

    public function test_placeholder_routes_require_authentication(): void
    {
        $this->get(route('bank-import'))->assertRedirect(route('login'));
        $this->get(route('advance-expenses'))->assertRedirect(route('login'));
        $this->get(route('reports'))->assertRedirect(route('login'));
        $this->get(route('other'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_placeholder_routes(): void
    {
        $user = User::factory()->create();
        Company::create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('bank-import'))->assertOk();
        $this->actingAs($user)->get(route('advance-expenses'))->assertOk();
        $this->actingAs($user)->get(route('reports'))->assertOk();
        $this->actingAs($user)->get(route('other'))->assertOk();
    }
}
