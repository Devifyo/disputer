<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_the_flight_product_not_the_retired_cases_module(): void
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('user');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Total Claims')
            ->assertSee('Protected Trips')
            ->assertSee('Fees Earned')
            ->assertSee('Recent Claims')
            // Empty DB: the friendly empty state, not headers over a void.
            ->assertSee('No claims yet')
            ->assertDontSee('Total Cases')
            ->assertDontSee('Escalated')
            ->assertDontSee('Recent Cases');
    }
}
