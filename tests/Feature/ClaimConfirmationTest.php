<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\ClaimSigner;
use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Claim Confirmation screen + e-signature workflow: master claim per
 * booking, consent gating, per-passenger POA generation, guardian signing
 * for minors, individual signing requests, and the filing unlock.
 */
class ClaimConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Mail::fake();
        config([
            'services.flightaware.api_key'    => 'test-key',
            // Pin the built-in signature pad - provider behavior is deterministic.
            'services.dropbox_sign.api_key'   => null,
            'services.dropbox_sign.client_id' => null,
        ]);
        Http::fake(fn () => Http::response(['country_code' => 'DE', 'name' => 'Test Airport', 'timezone' => 'UTC'], 200));

        Role::findOrCreate('user');
        $this->user = User::factory()->create(['email' => 'holder@example.com']);
        $this->user->assignRole('user');
    }

    private function itineraryWithPassengers(): Itinerary
    {
        $itinerary = Itinerary::create([
            'user_id'           => $this->user->id,
            'original_filename' => 'ticket.pdf',
            'file_path'         => 'itineraries/test.pdf',
            'status'            => 'parsed',
            'booking_reference' => 'ABC123',
            'primary_airline'   => 'Lufthansa',
        ]);

        $itinerary->passengers()->createMany([
            ['full_name' => 'Alice Holder', 'type' => 'MRS'],
            ['full_name' => 'Bob Adult', 'type' => 'MR'],
            ['full_name' => 'Cara Child', 'type' => 'CHD'],
        ]);

        $itinerary->flights()->create([
            'airline'           => 'Lufthansa',
            'flight_number'     => 'LH123',
            'departure_airport' => 'FRA',
            'arrival_airport'   => 'JFK',
            'departure_at'      => now()->subDays(10),
        ]);

        return $itinerary->fresh(['passengers', 'flights']);
    }

    private function eligibleClaim(): Claim
    {
        $itinerary = $this->itineraryWithPassengers();
        Claim::ensureForItinerary($itinerary);

        $claim = Claim::where('itinerary_id', $itinerary->id)->firstOrFail();
        $claim->forceFill([
            'status'                 => Claim::STATUS_ELIGIBLE,
            'eligibility_status'     => 'eligible',
            'eligibility_regulation' => 'EU261',
            'eligibility_article'    => 'Article 7(1)',
            'eligibility_confidence' => 90,
            'eligibility_reason'     => 'Arrival delay over 3 hours.',
            'eligibility_evaluated_at' => now(),
            'compensation_amount'    => 600,
            'compensation_currency'  => 'EUR',
            'contact_email'          => $this->user->email,
        ])->save();

        return $claim;
    }

    private function consents(): array
    {
        return ['consents' => ['accuracy' => true, 'authorization' => true, 'terms' => true, 'privacy' => true]];
    }

    public function test_one_master_claim_is_created_per_booking(): void
    {
        $itinerary = $this->itineraryWithPassengers();

        Claim::ensureForItinerary($itinerary);
        Claim::ensureForItinerary($itinerary->fresh(['passengers', 'flights'])); // idempotent

        $this->assertSame(1, Claim::where('itinerary_id', $itinerary->id)->count());
        $this->assertSame(['Alice Holder', 'Bob Adult', 'Cara Child'], Claim::first()->passengerNames());
    }

    public function test_confirmation_payload_totals_cover_every_passenger(): void
    {
        $claim = $this->eligibleClaim();

        $data = $this->actingAs($this->user)
            ->getJson(route('user.itineraries.api.claims.confirmation', encrypt_id($claim->id)))
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $data['passengers']);
        $this->assertTrue(collect($data['passengers'])->firstWhere('name', 'Cara Child')['minor']);
        $this->assertSame('600.00', $data['payout']['per_passenger']);
        $this->assertSame('1800.00', $data['payout']['gross']);
        $this->assertSame('450.00', $data['payout']['fee']);
        $this->assertSame('1350.00', $data['payout']['net']);
        $this->assertSame('European Union', $data['eligibility']['jurisdiction']);
    }

    public function test_passenger_names_can_be_corrected_until_confirmation_locks_them(): void
    {
        $claim = $this->eligibleClaim();

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.claims.passengers', encrypt_id($claim->id)), [
                'passengers' => ['Alice Corrected', 'Bob Corrected', 'Cara Corrected'],
            ])
            ->assertOk();

        $this->assertSame(['Alice Corrected', 'Bob Corrected', 'Cara Corrected'], $claim->fresh()->passengerNames());
        $this->assertSame('Alice Corrected', $claim->fresh()->passenger_name);

        // Once confirmed, names are locked - they are on the legal documents.
        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($claim->id)), $this->consents())
            ->assertOk();

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.claims.passengers', encrypt_id($claim->id)), [
                'passengers' => ['Someone Else', 'Bob Corrected', 'Cara Corrected'],
            ])
            ->assertStatus(422);
    }

    public function test_confirmation_requires_all_consents(): void
    {
        $claim = $this->eligibleClaim();

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($claim->id)), [
                'consents' => ['accuracy' => true, 'authorization' => true, 'terms' => false, 'privacy' => true],
            ])
            ->assertStatus(422);

        $this->assertNull($claim->fresh()->confirmed_at);
    }

    public function test_confirming_builds_signer_roster_with_guardian_for_the_minor_and_generates_documents(): void
    {
        $claim = $this->eligibleClaim();

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($claim->id)), $this->consents() + ['plus' => true])
            ->assertOk();

        $claim->refresh()->load('signers');

        $this->assertNotNull($claim->confirmed_at);
        $this->assertTrue($claim->plus_selected);
        $this->assertCount(3, $claim->signers); // 2 adults + 1 guardian signature

        $guardian = $claim->signers->firstWhere('role', ClaimSigner::ROLE_GUARDIAN);
        $this->assertSame('Cara Child', $guardian->signs_for);
        $this->assertSame('Alice Holder', $guardian->name);

        foreach ($claim->signers as $signer) {
            $this->assertNotNull($signer->poa_path);
            Storage::disk('local')->assertExists($signer->poa_path);
        }
        Storage::disk('local')->assertExists($claim->assignment_path);
    }

    public function test_signing_everyone_unlocks_the_claim_for_filing(): void
    {
        $claim = $this->eligibleClaim();
        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($claim->id)), $this->consents())
            ->assertOk();

        $png = 'data:image/png;base64,' . base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        foreach ($claim->fresh()->signers as $signer) {
            $this->actingAs($this->user)
                ->postJson(route('user.itineraries.api.claims.sign', ['claim' => encrypt_id($claim->id), 'signer' => $signer->id]), ['signature' => $png])
                ->assertOk();
        }

        $claim->refresh();
        $this->assertNotNull($claim->signed_at);
        $this->assertTrue($claim->signaturesComplete());
        $this->assertTrue($claim->events()->where('label', 'like', '%unlocked for filing%')->exists());
        $this->assertTrue($claim->events()->where('label', 'like', 'Claim submitted%')->where('status', 'pending')->exists());
    }

    public function test_additional_adult_gets_an_individual_signing_request_and_public_page(): void
    {
        $claim = $this->eligibleClaim();
        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($claim->id)), $this->consents())
            ->assertOk();

        $bob = $claim->fresh()->signers->firstWhere('name', 'Bob Adult');
        $this->assertNull($bob->email);

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.claims.invite', ['claim' => encrypt_id($claim->id), 'signer' => $bob->id]), ['email' => 'bob@example.com'])
            ->assertOk();

        $bob->refresh();
        $this->assertSame('bob@example.com', $bob->email);
        $this->assertNotNull($bob->invited_at);

        // Bob signs on the tokenised public page - no account needed.
        $this->get(route('claim-signature.show', $bob->sign_token))->assertOk()->assertSee('Bob Adult');

        $png = 'data:image/png;base64,' . base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $this->postJson(route('claim-signature.store', $bob->sign_token), ['signature' => $png])->assertOk();

        $this->assertSame(ClaimSigner::STATUS_SIGNED, $bob->fresh()->status);
    }
}
