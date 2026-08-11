<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\ClaimSigner;
use App\Models\Itinerary;
use App\Models\User;
use App\Services\Claims\ClaimSignatureService;
use App\Services\Claims\Signing\DropboxSignProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dropbox Sign integration validation.
 *
 * The existing suite covers the built-in signature pad end to end; this one
 * covers the provider path nothing else touched - request creation, embedded
 * signing, the webhook (security, events, idempotency), reconciliation,
 * API failures, and the isolation rules that keep one passenger's
 * authorisation from ever satisfying another's.
 *
 * ALL Dropbox Sign HTTP traffic is faked. No live or sandbox provider calls.
 */
class DropboxSignValidationTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'dropbox-secret-key';

    private User $customer;

    /** Mutable provider behaviour - the fake is registered once in setUp. */
    private bool $downloadReady = true;
    private bool $alreadySigned = false;
    private ?int $failStatus    = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();

        config([
            'services.dropbox_sign.api_key'   => self::API_KEY,
            'services.dropbox_sign.client_id' => 'client-abc',
            'services.dropbox_sign.test_mode' => true,
            'services.flightaware.api_key'    => 'test-key',
        ]);

        Role::findOrCreate('user');
        Role::findOrCreate('admin');

        $this->customer = User::factory()->create(['email' => 'holder@example.com']);
        $this->customer->assignRole('user');

        $this->fakeDropbox();
    }

    // ── 2 + 3. The documents actually say the right things ──

    public function test_each_poa_names_its_own_passenger_claim_and_flight(): void
    {
        $claim = $this->confirmedClaim();

        foreach ($claim->fresh()->signers as $signer) {
            $text = $this->pdfText($signer->poa_path);

            // Identity: whose authority this is, and who it covers.
            $this->assertStringContainsString($signer->name, $text);
            if ($signer->role === ClaimSigner::ROLE_GUARDIAN) {
                $this->assertStringContainsString($signer->signs_for, $text,
                    'A guardian POA must name the minor it covers');
                $this->assertStringContainsString('guardian', strtolower($text));
            }

            // The claim and flight it is tied to.
            $this->assertStringContainsString($claim->reference, $text);
            $this->assertStringContainsString((string) $claim->number, $text);
            $this->assertStringContainsString('Lufthansa', $text);
            $this->assertStringContainsString('LH123', $text);
            $this->assertStringContainsString('ABC123', $text);       // booking reference
            $this->assertStringContainsString('EU', $text);           // jurisdiction from EU261

            // And nobody else's name is on it.
            foreach ($claim->signers->where('id', '!=', $signer->id) as $other) {
                if ($other->name !== $signer->name && $other->name !== $signer->signs_for) {
                    $this->assertStringNotContainsString($other->name, $text,
                        "{$other->name} must not appear on {$signer->name}'s authorisation");
                }
            }
        }
    }

    public function test_the_assignment_is_one_per_booking_and_names_the_lead_signer(): void
    {
        $claim = $this->confirmedClaim();
        $text  = $this->pdfText($claim->fresh()->assignment_path);

        $this->assertStringContainsString($claim->reference, $text);
        $this->assertStringContainsString('Lufthansa', $text);
        $this->assertStringContainsString('LH123', $text);
        $this->assertStringContainsString('Alice Holder', $text, 'The lead signer assigns the booking-level claim');

        // A second claim's assignment is a different document entirely -
        // documents can never be shared across claims.
        $other = $this->confirmedClaim(email: 'second@example.com');
        $this->assertNotSame($claim->fresh()->assignment_path, $other->fresh()->assignment_path);
        $this->assertStringNotContainsString($claim->reference, $this->pdfText($other->fresh()->assignment_path));
    }

    // ── 14. Expired / cancelled requests ────────────────────

    public function test_an_expired_or_cancelled_request_never_unlocks_filing(): void
    {
        $signer = $this->providerSigner();
        $claim  = $signer->claim;

        // These events carry no completion, so the implementation leaves the
        // signer pending - there is no distinct "expired" status today.
        foreach (['signature_request_expired', 'signature_request_canceled'] as $event) {
            $this->postJson('/api/webhooks/dropbox-sign', $this->event($event, $signer->provider_request_id))->assertOk();
        }

        $signer->refresh();
        $claim->refresh();

        $this->assertSame(ClaimSigner::STATUS_PENDING, $signer->status);
        $this->assertNull($signer->signed_at);
        $this->assertFalse($claim->signaturesComplete());
        $this->assertNull($claim->signed_at);
        $this->assertNotSame('ready_to_file', $claim->workflow_state);
        $this->assertFalse($claim->canContactAirline()[0]);

        // The signer stays visible as outstanding, so it can be re-invited.
        $this->assertTrue($claim->signers()->where('status', ClaimSigner::STATUS_PENDING)->exists());
    }

    // ── 8 + 20. Request creation is per-signer and idempotent ──

    public function test_each_signer_gets_their_own_request_and_never_a_duplicate(): void
    {
        $claim = $this->confirmedClaim(); // lead adult + second adult + minor

        app(DropboxSignProvider::class)->createRequests($claim->fresh(['signers']));

        $signers = $claim->fresh()->signers;
        $withEmail = $signers->whereNotNull('email');

        // Every invitable signer is bound to their own Dropbox request.
        $this->assertGreaterThanOrEqual(2, $withEmail->count());
        foreach ($withEmail as $signer) {
            $this->assertNotNull($signer->provider_request_id, "{$signer->name} must have a request");
            $this->assertSame('dropbox_sign', $signer->provider);
        }
        $this->assertSame(
            $withEmail->count(),
            $withEmail->pluck('provider_request_id')->unique()->count(),
            'Two signers must never share one signature request'
        );

        // Only the lead signer's bundle carries the booking-level Assignment.
        $requests = $this->dropboxCalls('signature_request/create_embedded');
        $this->assertSame($withEmail->count(), count($requests));

        // Re-running is a no-op: existing requests are left alone.
        $before = $signers->pluck('provider_request_id', 'id');
        app(DropboxSignProvider::class)->createRequests($claim->fresh(['signers']));

        $this->assertSame(
            $before->toArray(),
            $claim->fresh()->signers->pluck('provider_request_id', 'id')->toArray(),
            'A second createRequests pass must not mint new provider requests'
        );
        $this->assertSame($withEmail->count(), count($this->dropboxCalls('signature_request/create_embedded')));
    }

    public function test_the_embedded_url_belongs_to_the_signer_who_asked_for_it(): void
    {
        $claim = $this->confirmedClaim();
        app(DropboxSignProvider::class)->createRequests($claim->fresh(['signers']));

        $lead = $claim->fresh()->signers->firstWhere('role', ClaimSigner::ROLE_PASSENGER);

        $url = $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.claims.sign-url', ['claim' => encrypt_id($claim->id), 'signer' => $lead->id]))
            ->assertOk()->json('data');

        // The URL was fetched for this signer's own signature id.
        $this->assertStringContainsString($lead->provider_signature_id, $url['sign_url']);
        $this->assertSame('client-abc', $url['client_id']);

        // A stranger cannot open anyone's signing session.
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $this->actingAs($stranger)
            ->getJson(route('user.itineraries.api.claims.sign-url', ['claim' => encrypt_id($claim->id), 'signer' => $lead->id]))
            ->assertForbidden();
    }

    // ── 10. Webhook security ────────────────────────────────

    public function test_only_correctly_signed_webhook_events_are_accepted(): void
    {
        $signer = $this->providerSigner();

        // Missing signature entirely.
        $this->postJson('/api/webhooks/dropbox-sign', ['json' => json_encode([
            'event'             => ['event_type' => 'signature_request_signed', 'event_time' => '1700000000'],
            'signature_request' => ['signature_request_id' => $signer->provider_request_id],
        ])])->assertStatus(400);

        // Wrong hash.
        $this->postJson('/api/webhooks/dropbox-sign', ['json' => json_encode([
            'event'             => ['event_type' => 'signature_request_signed', 'event_time' => '1700000000', 'event_hash' => 'deadbeef'],
            'signature_request' => ['signature_request_id' => $signer->provider_request_id],
        ])])->assertStatus(400);

        // Tampered payload: hash computed for a different event type.
        $tampered = $this->event('signature_request_signed', $signer->provider_request_id);
        $decoded  = json_decode($tampered['json'], true);
        $decoded['event']['event_type'] = 'signature_request_all_signed';
        $this->postJson('/api/webhooks/dropbox-sign', ['json' => json_encode($decoded)])->assertStatus(400);

        // Garbage body.
        $this->postJson('/api/webhooks/dropbox-sign', ['json' => 'not-json'])->assertStatus(400);

        // Nothing above touched the signer.
        $this->assertSame(ClaimSigner::STATUS_PENDING, $signer->fresh()->status);

        // A correctly signed event is accepted with Dropbox's required body.
        $this->postJson('/api/webhooks/dropbox-sign', $this->event('signature_request_signed', $signer->provider_request_id))
            ->assertOk()->assertSee('Hello API Event Received');

        $this->assertSame(ClaimSigner::STATUS_SIGNED, $signer->fresh()->status);
    }

    // ── 9 + 11. Events and idempotency ──────────────────────

    public function test_replayed_completion_events_never_double_apply(): void
    {
        $signer = $this->providerSigner();
        $claim  = $signer->claim;

        // The same completion arrives four times, in the orders Dropbox
        // actually delivers them.
        foreach (['signature_request_signed', 'signature_request_signed', 'signature_request_all_signed', 'signature_request_downloadable'] as $event) {
            $this->postJson('/api/webhooks/dropbox-sign', $this->event($event, $signer->provider_request_id))->assertOk();
        }

        $signer->refresh();
        $claim->refresh();

        $this->assertSame(ClaimSigner::STATUS_SIGNED, $signer->status);
        $this->assertSame(1, $claim->signers()->count(), 'No duplicate signer rows');

        // Exactly one "signed" timeline event and one audit entry.
        $this->assertSame(1, $claim->events()->where('label', 'like', 'Authorisation signed by%')->count());
        $this->assertSame(1, $claim->auditLogs()->where('action', 'like', 'Authorisation signed:%')->count());
        $this->assertSame(1, $claim->events()->where('label', 'like', '%unlocked for filing%')->count());

        // One lifecycle transition, not four.
        $this->assertSame('ready_to_file', $claim->workflow_state);
        $this->assertSame(1, $claim->auditLogs()->where('to_state', 'ready_to_file')->count());

        $firstSignedAt = $signer->signed_at;
        $this->postJson('/api/webhooks/dropbox-sign', $this->event('signature_request_signed', $signer->provider_request_id))->assertOk();
        $this->assertTrue($firstSignedAt->equalTo($signer->fresh()->signed_at), 'A replay must not restamp signed_at');
    }

    public function test_a_downloadable_event_backfills_the_signed_pdf_without_resigning(): void
    {
        // The signed PDF is not ready when "signed" fires - the later
        // "downloadable" event must attach it to the already-signed record.
        $this->downloadReady = false;
        $signer = $this->providerSigner();

        $this->postJson('/api/webhooks/dropbox-sign', $this->event('signature_request_signed', $signer->provider_request_id))->assertOk();
        $signer->refresh();
        $this->assertSame(ClaimSigner::STATUS_SIGNED, $signer->status);
        $this->assertNull($signer->signature_path);

        $signedAt = $signer->signed_at;

        $this->downloadReady = true;
        $this->postJson('/api/webhooks/dropbox-sign', $this->event('signature_request_downloadable', $signer->provider_request_id))->assertOk();

        $signer->refresh();
        $this->assertNotNull($signer->signature_path, 'The executed PDF must be stored');
        $this->assertSame($signer->signature_path, $signer->poa_path);
        $this->assertTrue(Storage::disk('local')->exists($signer->signature_path));
        $this->assertTrue($signedAt->equalTo($signer->signed_at), 'Backfilling must not re-sign');
    }

    // ── 13. Declined ────────────────────────────────────────

    public function test_a_declined_signature_stops_the_claim_advancing(): void
    {
        $signer = $this->providerSigner();
        $claim  = $signer->claim;

        $this->postJson('/api/webhooks/dropbox-sign', $this->event('signature_request_declined', $signer->provider_request_id))->assertOk();

        $signer->refresh();
        $claim->refresh();

        $this->assertSame(ClaimSigner::STATUS_DECLINED, $signer->status);
        $this->assertFalse($claim->signaturesComplete());
        $this->assertNull($claim->signed_at);
        $this->assertNotSame('ready_to_file', $claim->workflow_state);
        // Nor can the claim be written to the airline on a declined authority.
        $this->assertFalse($claim->canContactAirline()[0]);
    }

    // ── 12 + 19. Partial signatures gate the lifecycle ──────

    public function test_a_five_passenger_claim_waits_for_the_last_signature(): void
    {
        $this->fakeDropbox();
        $claim = $this->confirmedClaim(passengers: [
            ['full_name' => 'Alpha Lead', 'type' => 'MR'],
            ['full_name' => 'Bravo Adult', 'type' => 'MRS'],
            ['full_name' => 'Charlie Adult', 'type' => 'MR'],
            ['full_name' => 'Delta Adult', 'type' => 'MS'],
            ['full_name' => 'Echo Child', 'type' => 'CHD'],
        ]);

        $signers = $claim->fresh()->signers;
        $this->assertCount(5, $signers, 'Five passengers require five authorisations');

        // Bind them all to distinct provider requests.
        foreach ($signers as $i => $signer) {
            $signer->forceFill([
                'provider'              => 'dropbox_sign',
                'provider_request_id'   => "req-{$i}",
                'provider_signature_id' => "sig-{$i}",
            ])->save();
        }

        // Three sign.
        foreach ($signers->take(3) as $signer) {
            $this->postJson('/api/webhooks/dropbox-sign', $this->event('signature_request_signed', $signer->provider_request_id))->assertOk();
        }

        $claim->refresh();
        $this->assertSame(3, $claim->signers()->where('status', ClaimSigner::STATUS_SIGNED)->count());
        $this->assertSame(2, $claim->signers()->where('status', ClaimSigner::STATUS_PENDING)->count());
        $this->assertFalse($claim->signaturesComplete(), 'Three of five is not complete');
        $this->assertNull($claim->signed_at);
        $this->assertNotSame('ready_to_file', $claim->workflow_state);

        // The remaining two sign - only now does filing unlock.
        foreach ($signers->slice(3) as $signer) {
            $this->postJson('/api/webhooks/dropbox-sign', $this->event('signature_request_signed', $signer->provider_request_id))->assertOk();
        }

        $claim->refresh();
        $this->assertTrue($claim->signaturesComplete());
        $this->assertNotNull($claim->signed_at);
        $this->assertSame('ready_to_file', $claim->workflow_state);
    }

    // ── 22. One signature never satisfies another ───────────

    public function test_a_signature_can_never_be_credited_to_the_wrong_signer_or_claim(): void
    {
        $claimA = $this->confirmedClaim();
        $claimB = $this->confirmedClaim(email: 'other@example.com');

        $a = $claimA->fresh()->signers->first();
        $b = $claimB->fresh()->signers->first();

        $a->forceFill(['provider' => 'dropbox_sign', 'provider_request_id' => 'req-A', 'provider_signature_id' => 'sig-A'])->save();
        $b->forceFill(['provider' => 'dropbox_sign', 'provider_request_id' => 'req-B', 'provider_signature_id' => 'sig-B'])->save();

        // Claim A's signer signs.
        $this->postJson('/api/webhooks/dropbox-sign', $this->event('signature_request_signed', 'req-A'))->assertOk();

        $this->assertSame(ClaimSigner::STATUS_SIGNED, $a->fresh()->status);
        $this->assertSame(ClaimSigner::STATUS_PENDING, $b->fresh()->status, "Claim B's signer must be untouched");
        $this->assertNull($claimB->fresh()->signed_at);

        // An event for a request nobody owns changes nothing at all.
        $this->postJson('/api/webhooks/dropbox-sign', $this->event('signature_request_signed', 'req-does-not-exist'))->assertOk();
        $this->assertSame(ClaimSigner::STATUS_PENDING, $b->fresh()->status);
    }

    // ── 17. Signed documents are private ────────────────────

    public function test_one_customer_can_never_read_anothers_authorisation(): void
    {
        $claim  = $this->confirmedClaim();
        $signer = $claim->fresh()->signers->first();

        // The owner can read their own POA and the Assignment.
        $this->actingAs($this->customer)
            ->get(route('user.itineraries.api.claims.legal', ['claim' => encrypt_id($claim->id), 'doc' => 'poa-' . $signer->id]))
            ->assertOk();

        $stranger = User::factory()->create();
        $stranger->assignRole('user');

        foreach (['poa-' . $signer->id, 'assignment'] as $doc) {
            $this->actingAs($stranger)
                ->get(route('user.itineraries.api.claims.legal', ['claim' => encrypt_id($claim->id), 'doc' => $doc]))
                ->assertForbidden();
        }

        $this->actingAs($stranger)
            ->getJson(route('user.itineraries.api.claims.signers', encrypt_id($claim->id)))
            ->assertForbidden();

        // Guests get nowhere near any of it.
        $this->getJson(route('user.itineraries.api.claims.signers', encrypt_id($claim->id)))
            ->assertStatus(403);
    }

    // ── 24. The customer payload stays clean ────────────────

    public function test_the_signing_payload_exposes_status_but_no_provider_internals(): void
    {
        $claim = $this->confirmedClaim();
        app(DropboxSignProvider::class)->createRequests($claim->fresh(['signers']));

        $data = $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.claims.signers', encrypt_id($claim->id)))
            ->assertOk()->json('data');

        // What a customer legitimately needs.
        $this->assertSame('dropbox_sign', $data['mode']);
        $this->assertFalse($data['all_signed']);
        $this->assertNotEmpty($data['signers']);
        $this->assertArrayHasKey('status', $data['signers'][0]);

        // What must never leak.
        $serialised = json_encode($data);
        $this->assertStringNotContainsString(self::API_KEY, $serialised);
        $this->assertStringNotContainsString('provider_request_id', $serialised);
    }

    // ── 21. Provider failures never corrupt local state ─────

    public function test_every_api_failure_leaves_the_claim_exactly_as_it_was(): void
    {
        foreach ([400, 401, 403, 429, 500] as $status) {
            // The provider is already down when the customer confirms:
            // setup() must still build the roster and documents, leaving the
            // built-in pad usable rather than blocking confirmation.
            $this->failStatus = $status;
            $claim  = $this->confirmedClaim(email: "fail{$status}@example.com");
            $before = $claim->fresh()->signers->pluck('status', 'id')->toArray();

            $this->assertNotEmpty($before, "HTTP {$status} must not stop the roster being built");

            try {
                app(DropboxSignProvider::class)->createRequests($claim->fresh(['signers']));
                $this->fail("HTTP {$status} must surface as an error, not pass silently");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString((string) $status, $e->getMessage());
            }

            $claim->refresh();
            $this->assertSame($before, $claim->signers->pluck('status', 'id')->toArray(), "HTTP {$status} must not change signer state");
            $this->assertNull($claim->signed_at);
            $this->assertNotSame('ready_to_file', $claim->workflow_state);
            $this->assertNull($claim->signers->first()->provider_request_id, 'A failed request must not be recorded');
        }

        $this->failStatus = null;
    }

    public function test_a_missing_signing_url_and_a_failed_download_both_degrade_safely(): void
    {
        $signer = $this->providerSigner();

        // Embedded URL endpoint fails: null, not an exception or a bad link.
        $this->failStatus = 500;
        $this->assertNull(app(DropboxSignProvider::class)->embeddedSignUrl($signer));
        $this->assertNull(app(DropboxSignProvider::class)->downloadSigned($signer));

        // A signer with no provider request asks for nothing at all.
        $orphan = $signer->claim->signers()->create([
            'name' => 'No Provider', 'role' => ClaimSigner::ROLE_PASSENGER, 'provider' => 'dropbox_sign',
        ]);
        $this->assertNull(app(DropboxSignProvider::class)->embeddedSignUrl($orphan));
        $this->assertFalse(app(DropboxSignProvider::class)->isSigned($orphan));

        // The signer is still pending and the claim has not moved.
        $this->assertSame(ClaimSigner::STATUS_PENDING, $signer->fresh()->status);
        $this->assertNotSame('ready_to_file', $signer->claim->fresh()->workflow_state);
    }

    public function test_reconciliation_recovers_a_signature_the_webhook_never_delivered(): void
    {
        // Dropbox says signed; no webhook ever arrived.
        $this->alreadySigned = true;
        $signer = $this->providerSigner();

        app(ClaimSignatureService::class)->reconcile($signer);

        $signer->refresh();
        $this->assertSame(ClaimSigner::STATUS_SIGNED, $signer->status);
        $this->assertNotNull($signer->signature_path);
        $this->assertSame('ready_to_file', $signer->claim->fresh()->workflow_state);

        // Reconciling again is inert.
        $signedAt = $signer->signed_at;
        app(ClaimSignatureService::class)->reconcile($signer->fresh());
        $this->assertTrue($signedAt->equalTo($signer->fresh()->signed_at));
        $this->assertSame(1, $signer->claim->auditLogs()->where('to_state', 'ready_to_file')->count());
    }

    // ── 15. Reminders ───────────────────────────────────────

    public function test_reminders_chase_only_stale_pending_signers_and_stamp_them(): void
    {
        $claim = $this->confirmedClaim();

        $signers = $claim->fresh()->signers->values();
        $stale   = $signers[0];
        $fresh   = $signers[1];

        $stale->forceFill(['provider_request_id' => 'req-stale', 'email' => 'stale@example.com', 'invited_at' => now()->subDays(3)])->save();
        $fresh->forceFill(['provider_request_id' => 'req-fresh', 'email' => 'fresh@example.com', 'invited_at' => now()->subHour()])->save();

        $count = app(ClaimSignatureService::class)->sendReminders();

        $this->assertSame(1, $count, 'Only the 48h-stale signer is chased');
        $this->assertNotNull($stale->fresh()->reminded_at);
        $this->assertNull($fresh->fresh()->reminded_at);
        $this->assertCount(1, $this->dropboxCalls('signature_request/remind'));

        // Running again immediately does not re-chase the same person.
        $this->assertSame(0, app(ClaimSignatureService::class)->sendReminders());

        // A signer who has already signed is never chased.
        $stale->forceFill(['status' => ClaimSigner::STATUS_SIGNED, 'reminded_at' => null])->save();
        $this->assertSame(0, app(ClaimSignatureService::class)->sendReminders());
    }

    // ── 21. Credentials never leak ──────────────────────────

    public function test_the_api_key_never_appears_in_a_log_or_a_response(): void
    {
        Log::spy();
        $signer = $this->providerSigner();

        $this->failStatus = 401;
        app(ClaimSignatureService::class)->reconcile($signer);
        $this->failStatus = null;

        Log::shouldNotHaveReceived('warning', [\Mockery::any(), \Mockery::on(
            fn ($ctx) => str_contains(json_encode($ctx), self::API_KEY)
        )]);

        // The webhook logs the event, never the credential.
        $response = $this->postJson('/api/webhooks/dropbox-sign', $this->event('signature_request_signed', $signer->provider_request_id));
        $this->assertStringNotContainsString(self::API_KEY, $response->getContent());
    }

    // ── Fixtures ────────────────────────────────────────────

    /** A correctly HMAC-signed Dropbox Sign callback body. */
    private function event(string $type, ?string $requestId): array
    {
        $time = '1700000000';

        return ['json' => json_encode([
            'event' => [
                'event_type' => $type,
                'event_time' => $time,
                'event_hash' => hash_hmac('sha256', $time . $type, self::API_KEY),
            ],
            'signature_request' => ['signature_request_id' => $requestId],
        ])];
    }

    /** Fake the whole Dropbox Sign API surface, recording every call. */
    private function fakeDropbox(): void
    {
        Http::fake(function (ClientRequest $request) {
            $url = $request->url();

            if (!str_contains($url, 'hellosign.com')) {
                return Http::response([], 200);
            }

            if ($this->failStatus !== null) {
                return Http::response(['error' => 'nope'], $this->failStatus);
            }

            if (str_contains($url, 'create_embedded')) {
                // A distinct request/signature id per call.
                $n = count($this->dropboxCalls('create_embedded'));

                return Http::response(['signature_request' => [
                    'signature_request_id' => "req-{$n}",
                    'signatures'           => [['signature_id' => "sig-{$n}", 'status_code' => 'awaiting_signature']],
                ]], 200);
            }

            if (str_contains($url, 'embedded/sign_url')) {
                preg_match('#sign_url/([^/?]+)#', $url, $m);

                return Http::response(['embedded' => ['sign_url' => 'https://app.hellosign.com/editor/embedded?sig=' . ($m[1] ?? 'x')]], 200);
            }

            if (str_contains($url, 'signature_request/remind')) {
                return Http::response(['signature_request' => []], 200);
            }

            if (str_contains($url, 'signature_request/files')) {
                return $this->downloadReady
                    ? Http::response('%PDF-1.4 signed', 200, ['Content-Type' => 'application/pdf'])
                    : Http::response(['error' => 'not ready'], 409);
            }

            // GET /signature_request/{id}
            return Http::response(['signature_request' => [
                'is_complete' => $this->alreadySigned,
                'signatures'  => [['signature_id' => 'sig-0', 'status_code' => $this->alreadySigned ? 'signed' : 'awaiting_signature']],
            ]], 200);
        });
    }

    /** Readable text of a generated PDF - the same parser the app uses. */
    private function pdfText(string $path): string
    {
        $this->assertTrue(Storage::disk('local')->exists($path), "Missing document: {$path}");

        $text = (new \Smalot\PdfParser\Parser())
            ->parseContent(Storage::disk('local')->get($path))
            ->getText();

        // dompdf can break words across text runs - collapse whitespace so
        // assertions read naturally without depending on PDF internals.
        return preg_replace('/\s+/', ' ', $text);
    }

    /** Recorded Dropbox calls whose URL contains $fragment. */
    private function dropboxCalls(string $fragment): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), $fragment))
            ->values()
            ->all();
    }

    /** A confirmed claim with a generated roster and legal documents. */
    private function confirmedClaim(?array $passengers = null, string $email = 'holder@example.com'): Claim
    {
        $user = $email === 'holder@example.com'
            ? $this->customer
            : tap(User::factory()->create(['email' => $email]), fn ($u) => $u->assignRole('user'));

        $itinerary = Itinerary::create([
            'user_id'           => $user->id,
            'original_filename' => 'ticket.pdf',
            'file_path'         => 'itineraries/ticket.pdf',
            'status'            => 'parsed',
            'booking_reference' => 'ABC123',
            'primary_airline'   => 'Lufthansa',
        ]);

        $itinerary->passengers()->createMany($passengers ?? [
            ['full_name' => 'Alice Holder', 'type' => 'MRS'],
            ['full_name' => 'Bob Adult', 'type' => 'MR'],
            ['full_name' => 'Cara Child', 'type' => 'CHD'],
        ]);

        $itinerary->flights()->create([
            'airline' => 'Lufthansa', 'flight_number' => 'LH123',
            'departure_airport' => 'FRA', 'arrival_airport' => 'JFK',
            'departure_at' => now()->subDays(10),
        ]);

        $claim = Claim::create([
            'user_id'                => $user->id,
            'itinerary_id'           => $itinerary->id,
            'status'                 => Claim::STATUS_ELIGIBLE,
            'workflow_state'         => 'awaiting_signature',
            'airline'                => 'Lufthansa',
            'flight_number'          => 'LH123',
            'booking_reference'      => 'ABC123',
            'departure_airport'      => 'FRA',
            'arrival_airport'        => 'JFK',
            'flight_date'            => now()->subDays(10)->toDateString(),
            'passenger_name'         => 'Alice Holder',
            'contact_email'          => $user->email,
            'confirmed_at'           => now(),
            'eligibility_regulation' => 'EU261',
            'eligibility_article'    => 'Article 7',
            'compensation_amount'    => '600.00',
            'compensation_currency'  => 'EUR',
        ]);

        app(ClaimSignatureService::class)->setup($claim->fresh(['itinerary.passengers', 'user']));

        return $claim->fresh(['signers']);
    }

    /** A single-signer claim already bound to a Dropbox Sign request. */
    private function providerSigner(): ClaimSigner
    {
        $claim  = $this->confirmedClaim(passengers: [['full_name' => 'Solo Traveller', 'type' => 'MR']]);
        $signer = $claim->fresh()->signers->first();

        $signer->forceFill([
            'provider'              => 'dropbox_sign',
            'provider_request_id'   => 'req-0',
            'provider_signature_id' => 'sig-0',
            'invited_at'            => now()->subDays(3),
        ])->save();

        return $signer->fresh();
    }
}
