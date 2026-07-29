<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The retired case-management pages are closed to customers. Hiding the nav
 * link is not enough - a bookmark or a typed URL must not get in either.
 */
class RetiredModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('user');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('user');
    }

    public static function retiredRoutes(): array
    {
        return [
            'documents'  => ['/documents'],
            'cases list' => ['/cases'],
            'case create'=> ['/cases/create'],
            'letter templates' => ['/templates'],
        ];
    }

    /** @dataProvider retiredRoutes */
    public function test_retired_pages_send_customers_back_to_the_dashboard(string $path): void
    {
        $this->actingAs($this->customer)
            ->get($path)
            ->assertRedirect(route('user.dashboard'));
    }

    public function test_an_api_client_gets_a_plain_404_rather_than_a_redirect(): void
    {
        $this->actingAs($this->customer)
            ->getJson('/documents')
            ->assertNotFound();
    }

    public function test_the_sidebar_no_longer_offers_the_retired_pages(): void
    {
        $html = $this->actingAs($this->customer)->get(route('user.dashboard'))->getContent();

        $this->assertStringNotContainsString('>Documents<', $html);
        $this->assertStringNotContainsString('folder-kanban', $html, 'The My Cases link must be gone from the sidebar.');
        $this->assertStringNotContainsString('Cases Email Templates', $html);
    }
}
