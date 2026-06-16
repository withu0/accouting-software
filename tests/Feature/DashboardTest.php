<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_are_redirected_to_home()
    {
        $user = User::factory()->create();
        Company::create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('home'));
    }

    public function test_guests_can_visit_landing_page()
    {
        $this->get('/')->assertOk();
    }

    public function test_authenticated_users_can_visit_home()
    {
        $user = User::factory()->create();
        Company::create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/')->assertOk();
    }
}
