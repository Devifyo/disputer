<?php

namespace Tests\Feature;

use App\Livewire\Admin\FlightClaims\Airlines;
use App\Livewire\Admin\FlightClaims\ClaimDetail;
use App\Models\Airline;
use App\Models\Claim;
use App\Models\ClaimWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Airline directory: per-carrier contact addresses and per-stage routing of
 * outbound claim emails.
 */
class AirlineDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Role::findOrCreate('admin');
        Role::findOrCreate('user');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_airline_resolves_by_flight_number_prefix_over_name(): void
    {
        $this->assertSame('Air Canada Express', Airline::match('Air Canada', 'QK8474')?->name);
        $this->assertSame('Air Canada', Airline::match('Air Canada', 'AC1540')?->name);
        $this->assertSame('Lufthansa', Airline::match('Lufthansa', null)?->name);
        $this->assertNull(Airline::match('GlobalAir Airlines', 'GA456'));
    }

    public function test_admin_manages_airline_contacts(): void
    {
        $airline = Airline::where('iata_code', 'AC')->first();

        Livewire::actingAs($this->admin)
            ->test(Airlines::class)
            ->call('edit', $airline->id)
            ->set('form.contacts.0.email', 'claims@aircanada.ca')
            ->call('save');

        $this->assertSame('claims@aircanada.ca', $airline->fresh()->contactFor('claims')?->email);

        // Emptying the address removes the contact.
        Livewire::actingAs($this->admin)
            ->test(Airlines::class)
            ->call('edit', $airline->id)
            ->set('form.contacts.0.email', '')
            ->call('save');

        $this->assertNull($airline->fresh()->contactFor('claims'));
    }

    public function test_admin_can_add_custom_contact_types(): void
    {
        $airline = Airline::where('iata_code', 'AC')->first();

        Livewire::actingAs($this->admin)
            ->test(Airlines::class)
            ->call('edit', $airline->id)
            ->call('addContact')
            ->set('form.contacts.4.purpose', 'Refunds desk')
            ->set('form.contacts.4.email', 'refunds@aircanada.ca')
            ->call('save');

        $contact = $airline->fresh()->contactFor('refunds_desk');
        $this->assertSame('refunds@aircanada.ca', $contact?->email);
        $this->assertSame('Refunds desk', $contact?->purposeLabel());

        // The custom contact reappears on edit and can be removed.
        $component = Livewire::actingAs($this->admin)->test(Airlines::class)->call('edit', $airline->id);
        $this->assertContains('refunds_desk', collect($component->get('form.contacts'))->pluck('purpose'));

        $component->call('removeContact', 4)->call('save');
        $this->assertNull($airline->fresh()->contactFor('refunds_desk'));
    }

    public function test_composer_prefills_the_to_address_from_the_directory(): void
    {
        Airline::where('iata_code', 'AC')->first()
            ->contacts()->create(['purpose' => 'claims', 'email' => 'claims@aircanada.ca']);

        $owner = User::factory()->create();
        $owner->assignRole('user');
        $claim = Claim::create([
            'user_id' => $owner->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'ready_to_file',
            'airline' => 'Air Canada', 'flight_number' => 'AC1540',
            'departure_airport' => 'YYZ', 'arrival_airport' => 'IAD',
            'flight_date' => '2026-07-10', 'passenger_name' => 'Tenzin Hagyal',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ClaimDetail::class, ['claim' => $claim])
            ->assertSet('to', 'claims@aircanada.ca')
            ->assertSet('filing_recipient', 'claims@aircanada.ca');
    }
}
