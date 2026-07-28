<?php

namespace Tests\Feature;

use App\Livewire\Admin\FlightClaims\Passengers as AdminPassengers;
use App\Mail\GenericEmail;
use App\Models\Claim;
use App\Models\ClaimSigner;
use App\Models\User;
use App\Services\Claims\PassengerDirectory;
use App\Support\Passengers\PassengerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Passengers are not a table - they are people spread across signature
 * rosters, tickets, claims and monitored trips. The directory merges those
 * records into one profile per human, and the admin can unstick a signature
 * from there without hunting through claims.
 */
class PassengerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Role::findOrCreate('admin');
        Role::findOrCreate('user');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('user');
    }

    private function claim(array $overrides = []): Claim
    {
        return Claim::create(array_merge([
            'user_id' => $this->customer->id, 'status' => Claim::STATUS_ELIGIBLE,
            'workflow_state' => 'awaiting_signature', 'airline' => 'Air Canada', 'flight_number' => 'AC1540',
            'departure_airport' => 'YYZ', 'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10',
            'passenger_name' => 'Tenzin Hagyal', 'compensation_amount' => '400.00', 'compensation_currency' => 'CAD',
        ], $overrides));
    }

    public function test_one_person_on_two_claims_is_one_profile(): void
    {
        $first  = $this->claim();
        $second = $this->claim(['flight_number' => 'AC9', 'flight_date' => '2026-06-01']);

        foreach ([$first, $second] as $claim) {
            $claim->signers()->create([
                'name' => 'Tenzin Hagyal', 'email' => 'tenzin@example.com',
                'role' => ClaimSigner::ROLE_PASSENGER, 'status' => ClaimSigner::STATUS_PENDING,
            ]);
        }

        $people = app(PassengerDirectory::class)->all();
        $person = $people->firstWhere('name', 'Tenzin Hagyal');

        $this->assertNotNull($person);
        $this->assertSame(1, $people->filter(fn ($p) => $p->name === 'Tenzin Hagyal')->count(), 'The same person must not appear twice.');
        $this->assertSame(2, $person->claims->count());
        $this->assertSame(2, $person->pendingSignatures()->count());
        $this->assertSame(['CAD' => 800.0], $person->compensation());
    }

    public function test_a_name_variant_merges_onto_the_shared_email_address(): void
    {
        // Same human, two spellings, one address - one profile.
        $claim = $this->claim();
        $claim->signers()->create([
            'name' => 'Tenzin Hagyal', 'email' => 'tenzin@example.com',
            'role' => ClaimSigner::ROLE_PASSENGER, 'status' => ClaimSigner::STATUS_SIGNED, 'signed_at' => now(),
        ]);
        $other = $this->claim(['passenger_name' => 'T. Hagyal', 'contact_email' => 'tenzin@example.com', 'flight_number' => 'AC77']);

        $people = app(PassengerDirectory::class)->all();

        $this->assertSame(0, $people->filter(fn ($p) => $p->name === 'T. Hagyal')->count());
        $this->assertSame(2, $people->firstWhere('name', 'Tenzin Hagyal')->claims->count());
    }

    public function test_a_minor_gets_their_own_profile_linked_to_the_guardian(): void
    {
        $claim = $this->claim();
        $claim->signers()->create([
            'name' => 'Tenzin Hagyal', 'email' => 'tenzin@example.com', 'signs_for' => 'Sonam Hagyal',
            'role' => ClaimSigner::ROLE_GUARDIAN, 'status' => ClaimSigner::STATUS_PENDING,
        ]);

        $people   = app(PassengerDirectory::class)->all();
        $minor    = $people->firstWhere('name', 'Sonam Hagyal');
        $guardian = $people->firstWhere('name', 'Tenzin Hagyal');

        $this->assertTrue($minor->is(PassengerProfile::ROLE_MINOR));
        $this->assertSame('Tenzin Hagyal', $minor->guardian);
        $this->assertSame(1, $minor->claims->count());
        // The minor never signs personally - the guardian carries the duty.
        $this->assertTrue($minor->signers->isEmpty());
        $this->assertTrue($guardian->is(PassengerProfile::ROLE_GUARDIAN));
        $this->assertSame(['Sonam Hagyal'], $guardian->signsFor);
    }

    public function test_admin_fixes_a_typo_and_the_signature_request_goes_out(): void
    {
        $claim  = $this->claim();
        $signer = $claim->signers()->create([
            'name' => 'Tenzin Hagyal', 'email' => 'wrong@typo',
            'role' => ClaimSigner::ROLE_PASSENGER, 'status' => ClaimSigner::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->admin)->test(AdminPassengers::class)
            ->call('editEmail', $signer->id)
            ->set('signerEmail', 'tenzin@example.com')
            ->call('sendSignatureRequest', $signer->id)
            ->assertHasNoErrors();

        $signer->refresh();
        $this->assertSame('tenzin@example.com', $signer->email);
        $this->assertNotNull($signer->invited_at);
        Mail::assertSent(GenericEmail::class, fn ($mail) => $mail->hasTo('tenzin@example.com'));

        // Correcting someone's address is a money-adjacent act - it is audited.
        $this->assertTrue($claim->auditLogs()
            ->where('action', 'Signer email corrected and request sent')
            ->where('notes', 'wrong@typo -> tenzin@example.com')
            ->exists());
    }

    public function test_a_signed_passenger_cannot_be_chased_again(): void
    {
        $claim  = $this->claim();
        $signer = $claim->signers()->create([
            'name' => 'Tenzin Hagyal', 'email' => 'tenzin@example.com',
            'role' => ClaimSigner::ROLE_PASSENGER, 'status' => ClaimSigner::STATUS_SIGNED, 'signed_at' => now(),
        ]);

        Livewire::actingAs($this->admin)->test(AdminPassengers::class)
            ->call('sendSignatureRequest', $signer->id);

        Mail::assertNothingSent();
    }

    public function test_filters_surface_the_people_who_need_attention(): void
    {
        $stuck = $this->claim(['passenger_name' => 'No Email Person']);
        $stuck->signers()->create([
            'name' => 'No Email Person', 'email' => null,
            'role' => ClaimSigner::ROLE_PASSENGER, 'status' => ClaimSigner::STATUS_PENDING,
        ]);
        $done = $this->claim(['passenger_name' => 'All Done Person', 'flight_number' => 'AC5']);
        $done->signers()->create([
            'name' => 'All Done Person', 'email' => 'done@example.com',
            'role' => ClaimSigner::ROLE_PASSENGER, 'status' => ClaimSigner::STATUS_SIGNED, 'signed_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)->test(AdminPassengers::class);

        // "No email on file" is the queue that actually blocks filing.
        $component->call('setFilter', 'stuck')
            ->assertSee('No Email Person')
            ->assertDontSee('All Done Person');

        // Search reaches people through their claim and flight too.
        $component->call('setFilter', 'all')->set('search', 'AC5')
            ->assertSee('All Done Person')
            ->assertDontSee('No Email Person');
    }
}
