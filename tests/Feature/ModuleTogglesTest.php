<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Module switches: off means gone from BOTH portals - the nav link hides
 * and the routes refuse - while the data stays untouched for switch-back.
 */
class ModuleTogglesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin')->givePermissionTo(
            \Spatie\Permission\Models\Permission::whereIn('name', ['payments.view', 'payments.manage'])->get()
        );
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('user');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('user');
    }

    public function test_defaults_match_each_modules_definition(): void
    {
        foreach (Modules::ALL as $module => $definition) {
            $this->assertSame(
                $definition['default'] ?? true,
                Modules::enabled($module),
                "{$module} default is wrong"
            );
        }

        $this->actingAs($this->admin)->get(route('admin.flight-claims.trips'))->assertOk();
        $this->actingAs($this->customer)->getJson(route('user.itineraries.api.trips.index'))->assertOk();
    }

    public function test_retired_case_pages_stay_closed_until_switched_on(): void
    {
        // Off by default: no sidebar link, and the route sends people home.
        $html = $this->actingAs($this->customer)->get(route('user.dashboard'))->getContent();
        $this->assertStringNotContainsString('user/cases', $html);

        $this->actingAs($this->customer)
            ->get(route('user.cases.index'))
            ->assertRedirect(route('user.dashboard'));

        // An admin can reopen them from Settings without a code change.
        Modules::set(Modules::CASES, true);
        $this->actingAs($this->customer)->get(route('user.cases.index'))->assertOk();
    }

    public function test_a_switched_off_module_refuses_on_both_portals(): void
    {
        Modules::set(Modules::TRIPS, false);

        // Admin page: sent home with an explanation, not a broken page.
        $this->actingAs($this->admin)
            ->get(route('admin.flight-claims.trips'))
            ->assertRedirect(route('admin.dashboard'));

        // Customer API: a plain 404, as if the feature did not exist.
        $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.trips.index'))
            ->assertNotFound();

        // Unrelated modules are untouched.
        $this->actingAs($this->admin)->get(route('admin.flight-claims.claims'))->assertOk();

        // And the nav link disappears from the customer sidebar.
        $html = $this->actingAs($this->customer)->get(route('user.dashboard'))->getContent();
        $this->assertStringNotContainsString('Protect Your Trip', $html);
        $this->assertStringContainsString('Flight Disputes', $html);
    }

    public function test_switching_back_on_restores_access(): void
    {
        Modules::set(Modules::PAYMENTS, false);
        $this->actingAs($this->admin)->get(route('admin.flight-claims.payments'))->assertRedirect();

        Modules::set(Modules::PAYMENTS, true);
        $this->actingAs($this->admin)->get(route('admin.flight-claims.payments'))->assertOk();
    }

    public function test_the_settings_screen_saves_the_switches(): void
    {
        \Livewire\Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Admin\Settings\Index::class)
            ->set('modules.trips', false)
            ->call('updateWebsite');

        $this->assertFalse(Modules::enabled(Modules::TRIPS));
        $this->assertTrue(Modules::enabled(Modules::CLAIMS));
    }
}
