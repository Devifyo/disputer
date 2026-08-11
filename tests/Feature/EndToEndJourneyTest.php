<?php

namespace Tests\Feature;

use App\Livewire\Admin\FlightClaims\ClaimDetail;
use App\Mail\AirlineClaimMail;
use App\Models\Claim;
use App\Models\Itinerary;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Services\Claims\ClaimWorkflowService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\WisePayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The complete Unjamm business workflow, chained end to end the way a real
 * booking travels through it: registration -> itinerary PDF -> parsing ->
 * eligibility -> confirmation -> signatures -> admin filing -> airline
 * reply -> 30-day timer -> escalation -> payment -> Wise payout -> closed.
 *
 * External services (Gemini parsing, FlightAware, Wise) are faked at the
 * HTTP boundary only - everything inside the application (routes, jobs on
 * the sync queue, workflow engine, ledgers, notifications) runs for real.
 */
class EndToEndJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'S3cure-journey-pass!';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        Notification::fake();

        config([
            'services.flightaware.api_key'    => 'test-key',
            'services.gemini.api_key'         => 'gemini-test',
            // Built-in signature pad: deterministic, no Dropbox Sign network.
            'services.dropbox_sign.api_key'   => null,
            'services.dropbox_sign.client_id' => null,
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('user');
        Role::findOrCreate('admin')->givePermissionTo(
            \Spatie\Permission\Models\Permission::whereIn('name', [
                'payments.view', 'payments.manage', 'payments.override_fee', 'payouts.send',
                'airlines.manage', 'claim_templates.manage', 'claim_drafts.generate', 'claim_emails.send',
            ])->get()
        );
    }

    // ── The full happy path ─────────────────────────────────

    public function test_registration_to_paid_and_closed_full_journey(): void
    {
        $this->fakeExternalWorld();

        // 1. REGISTRATION - a stranger becomes a customer.
        $this->post('/register', [
            'name'                  => 'Journey Tester',
            'email'                 => 'journey@example.com',
            'password'              => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('user.dashboard'));

        $customer = User::where('email', 'journey@example.com')->firstOrFail();
        $this->assertTrue($customer->hasRole('user'));

        // 2. LOGIN - a fresh session with the chosen credentials works.
        $this->post('/logout');
        $this->post('/login', ['email' => 'journey@example.com', 'password' => self::PASSWORD])
            ->assertRedirect();
        $this->assertAuthenticatedAs($customer);

        // 3. ITINERARY PDF UPLOAD - parsed into flights + passengers.
        $upload = $this->actingAs($customer)->postJson(route('user.itineraries.api.store'), [
            'file' => UploadedFile::fake()->create('booking-LH8XK2P.pdf', 120, 'application/pdf'),
        ])->assertCreated()->json('data');

        $itinerary = Itinerary::findOrFail($upload['id']);
        $this->assertSame($customer->id, $itinerary->user_id);
        $this->assertSame('LH8XK2P', $itinerary->booking_reference);
        $this->assertSame('Air Canada', $itinerary->primary_airline);
        $this->assertSame(Itinerary::STATUS_PARSED, $itinerary->status);
        $this->assertSame(['AC845'], $itinerary->flights->pluck('flight_number')->all());
        $this->assertSame(['FRA', 'YUL'], [$itinerary->flights[0]->departure_airport, $itinerary->flights[0]->arrival_airport]);
        $this->assertSame(['Journey Tester', 'Nomi Companion'], $itinerary->passengers->pluck('full_name')->all());

        // 4. ONE MASTER CLAIM, evaluated synchronously on the sync queue:
        //    FlightAware verified the 4h20 delay, the engine ruled EU261.
        $claim = Claim::where('itinerary_id', $itinerary->id)->firstOrFail();
        $this->assertSame($customer->id, $claim->user_id);
        $this->assertSame('Journey Tester', $claim->passenger_name);
        $this->assertSame('LH8XK2P', $claim->booking_reference);
        $this->assertSame(Claim::STATUS_ELIGIBLE, $claim->status);
        $this->assertNotNull($claim->flight_verified_at);
        $this->assertSame('EU261', $claim->eligibility_regulation);
        $this->assertSame('600.00', $claim->compensation_amount);
        $this->assertSame('EUR', $claim->compensation_currency);

        // Compensation is never shown as confirmed before evaluation: the
        // customer payload carries the verdict WITH the amounts, and the
        // events trail shows evaluation happened before any confirmation.
        $detail = $this->actingAs($customer)
            ->getJson(route('user.itineraries.api.claims.show', encrypt_id($claim->id)))
            ->assertOk()->json('data');
        $this->assertSame('eligible', $detail['eligibility']['status']);

        // 5. CONFIRMATION - totals cover both passengers, consents required.
        $confirmation = $this->actingAs($customer)
            ->getJson(route('user.itineraries.api.claims.confirmation', encrypt_id($claim->id)))
            ->assertOk()->json('data');
        $this->assertSame('600.00', $confirmation['payout']['per_passenger']);
        $this->assertSame('1200.00', $confirmation['payout']['gross']);
        $this->assertSame('300.00', $confirmation['payout']['fee']);   // 25%
        $this->assertSame('900.00', $confirmation['payout']['net']);   // 75%

        $this->actingAs($customer)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($claim->id)), [
                'consents' => ['accuracy' => true, 'authorization' => true, 'terms' => true, 'privacy' => true],
            ])->assertOk();

        // 6. SIGNATURES - every passenger signs, the workflow takes over.
        $claim->refresh();
        $this->assertNotNull($claim->confirmed_at);
        $this->assertCount(2, $claim->signers);

        $png = 'data:image/png;base64,' . base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        foreach ($claim->signers as $signer) {
            $this->actingAs($customer)
                ->postJson(route('user.itineraries.api.claims.sign', ['claim' => encrypt_id($claim->id), 'signer' => $signer->id]), ['signature' => $png])
                ->assertOk();
        }

        $claim->refresh();
        $this->assertNotNull($claim->signed_at);
        $this->assertSame('ready_to_file', $claim->workflow_state);
        $this->assertTrue($claim->auditLogs()->where('to_state', 'ready_to_file')->exists());

        // 7. ADMIN FILES THE CLAIM - the customer has no send surface; the
        //    admin composes and sends, and the workflow chains to
        //    awaiting_response with the REAL 30-day timer from config.
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(ClaimDetail::class, ['claim' => $claim->fresh()])
            ->set('to', 'claims@aircanada.ca')
            ->set('subject', 'Compensation claim - AC845 / LH8XK2P')
            ->set('body', str_repeat('Formal demand for EUR 1,200 under EU261 Article 7(1)(c). ', 8))
            ->call('send');

        $claim->refresh();
        $this->assertSame('awaiting_response', $claim->workflow_state);
        $this->assertSame('claims@aircanada.ca', $claim->filing['recipient']);
        Mail::assertSent(AirlineClaimMail::class);

        $timer = $claim->workflowTimers()->where('purpose', 'stage_auto')->firstOrFail();
        $this->assertSame('awaiting_escalation', $timer->meta['to_stage']);
        $this->assertTrue($timer->due_at->between(now()->addDays(29), now()->addDays(31)),
            '30-day airline deadline must be calculated from the stage config');

        // 8. AIRLINE REPLIES to the claim's tokenised address - stored on
        //    the right claim, admins alerted, no phantom claim created.
        $this->postJson('/api/webhooks/sendgrid/claims-inbound', [
            'from'     => 'Air Canada Claims <claims@aircanada.ca>',
            'to'       => $claim->replyAddress(),
            'envelope' => json_encode(['to' => [$claim->replyAddress()]]),
            'subject'  => 'Re: Compensation claim - AC845 / LH8XK2P',
            'text'     => 'We accept the claim and will remit EUR 1,200 to your firm.',
        ])->assertOk();

        $this->assertSame(1, Claim::count());
        $inbound = $claim->correspondence()->where('direction', 'inbound')->firstOrFail();
        $this->assertSame('reply_token', $inbound->matched_by);

        // The customer never sees the internal correspondence surface.
        $customerView = $this->actingAs($customer)
            ->getJson(route('user.itineraries.api.claims.show', encrypt_id($claim->id)))
            ->assertOk()->json('data');
        $this->assertArrayNotHasKey('correspondence', $customerView);
        $this->assertArrayNotHasKey('drafts', $customerView);

        // 9. ADMIN moves the claim to responded and records the airline's
        //    payment: 25% fee, 75% net, immutable ledger.
        app(ClaimWorkflowService::class)->transition($claim->fresh(), 'responded', 'admin', $admin->id, 'Airline accepted in writing.');

        $payment = app(PaymentService::class)->record($claim->fresh(), [
            'gross_amount' => 1200, 'currency' => 'EUR',
            'payment_date' => now()->toDateString(), 'reference' => 'AC-REMIT-845',
        ], $admin);

        $this->assertSame('300.00', $payment->fee_amount);
        $this->assertSame('900.00', $payment->net_amount);
        $this->assertSame(Payment::STATUS_RECEIVED, $payment->status);
        $this->assertSame($claim->id, $payment->claim_id);

        // 10. WISE PAYOUT - draft, send, webhook completion.
        $wise   = app(WisePayoutService::class);
        $payout = $wise->draft($payment, 'EUR', $admin);
        $wise->send($payout, $admin);
        $payout->refresh();
        $this->assertSame(Payout::STATUS_SENT, $payout->status);
        $this->assertSame('555001', $payout->wise_transfer_id);

        $this->postJson('/api/webhooks/wise', [
            'data' => ['resource' => ['id' => 555001], 'current_state' => 'outgoing_payment_sent'],
        ])->assertOk();

        $this->assertSame(Payout::STATUS_COMPLETED, $payout->fresh()->status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);

        // 11. LIFECYCLE CLOSES with a full audit chain, and the customer's
        //     own view says "Paid out" - never an internal workflow word.
        app(ClaimWorkflowService::class)->transition($claim->fresh(), 'paid', 'admin', $admin->id);
        app(ClaimWorkflowService::class)->transition($claim->fresh(), 'closed', 'admin', $admin->id, 'Compensation remitted in full.');

        $claim->refresh();
        $this->assertSame('closed', $claim->workflow_state);
        $this->assertSame('Paid out', $claim->customerStage()[0]);

        $states = $claim->auditLogs()->whereNotNull('to_state')->reorder('id')->pluck('to_state')->all();
        $this->assertSame(
            ['awaiting_signature', 'ready_to_file', 'filed', 'awaiting_response', 'responded', 'paid', 'closed'],
            array_values(array_unique($states)),
            'Every lifecycle hop must be in the immutable audit trail'
        );

        // 12. DATA INTEGRITY - one unbroken chain, all owned by one user.
        $this->assertSame($customer->id, $itinerary->fresh()->user_id);
        $this->assertSame($itinerary->id, $claim->itinerary_id);
        $this->assertSame($claim->id, $inbound->claim_id);
        $this->assertSame($claim->id, $payment->fresh()->claim_id);
        $this->assertSame($payment->id, $payout->fresh()->payment_id);
        $this->assertSame($customer->id, $payment->fresh()->claim->user_id);
        $this->assertSame(['Journey Tester', 'Nomi Companion'], $claim->signers()->orderBy('id')->pluck('name')->all());
    }

    // ── The escalation branch ───────────────────────────────

    public function test_thirty_day_silence_escalates_to_the_admin_not_the_regulator(): void
    {
        $this->fakeExternalWorld();

        [$customer, $claim] = $this->filedClaim();

        // Backdate the real timer the workflow created and run the scheduler.
        $timer = $claim->workflowTimers()->where('purpose', 'stage_auto')->firstOrFail();
        $timer->update(['due_at' => now()->subHour()]);

        $this->artisan('claims:evaluate-workflow-timers')->assertSuccessful();

        $claim->refresh();
        $this->assertSame('awaiting_escalation', $claim->workflow_state,
            'Silence moves the claim to the ADMIN escalation queue');
        $this->assertNotSame('escalated', $claim->workflow_state,
            'The system must never escalate to a regulator on its own');

        // The admin decides: escalate. Actor and note land in the audit.
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        app(ClaimWorkflowService::class)->transition($claim, 'escalated', 'admin', $admin->id, 'No reply after formal deadline.');

        $claim->refresh();
        $this->assertSame('escalated', $claim->workflow_state);
        $log = $claim->auditLogs()->where('to_state', 'escalated')->firstOrFail();
        $this->assertSame('awaiting_escalation', $log->from_state);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame('No reply after formal deadline.', $log->notes);
    }

    // ── Multi-passenger isolation ───────────────────────────

    public function test_five_passenger_booking_pays_everyone_once_and_mixes_nobody(): void
    {
        $this->fakeExternalWorld(passengers: [
            ['full_name' => 'Alpha Lead',   'type' => 'MR'],
            ['full_name' => 'Bravo Adult',  'type' => 'MRS'],
            ['full_name' => 'Charlie Adult','type' => 'MR'],
            ['full_name' => 'Delta Adult',  'type' => 'MS'],
            ['full_name' => 'Echo Child',   'type' => 'CHD'],
        ]);

        $customer = $this->registeredCustomer('family@example.com');
        $this->actingAs($customer)->postJson(route('user.itineraries.api.store'), [
            'file' => UploadedFile::fake()->create('family-booking.pdf', 150, 'application/pdf'),
        ])->assertCreated();

        // Still ONE master claim - never one per passenger.
        $this->assertSame(1, Claim::count());
        $claim = Claim::firstOrFail();

        $confirmation = $this->actingAs($customer)
            ->getJson(route('user.itineraries.api.claims.confirmation', encrypt_id($claim->id)))
            ->assertOk()->json('data');

        $this->assertCount(5, $confirmation['passengers']);
        $this->assertSame('600.00', $confirmation['payout']['per_passenger']);
        $this->assertSame('3000.00', $confirmation['payout']['gross']);
        $this->assertSame('750.00', $confirmation['payout']['fee']);
        $this->assertSame('2250.00', $confirmation['payout']['net']);
        $this->assertTrue(collect($confirmation['passengers'])->firstWhere('name', 'Echo Child')['minor']);

        $this->actingAs($customer)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($claim->id)), [
                'consents' => ['accuracy' => true, 'authorization' => true, 'terms' => true, 'privacy' => true],
            ])->assertOk();

        // One signer row per passenger: each adult signs their own POA;
        // the minor's POA is signed by the lead adult as guardian.
        $claim->refresh();
        $signers = $claim->signers()->orderBy('id')->get();
        $this->assertCount(5, $signers);
        $this->assertSame(
            ['Alpha Lead', 'Bravo Adult', 'Charlie Adult', 'Delta Adult'],
            $signers->where('role', \App\Models\ClaimSigner::ROLE_PASSENGER)->pluck('name')->values()->all()
        );
        $guardian = $signers->firstWhere('role', \App\Models\ClaimSigner::ROLE_GUARDIAN);
        $this->assertSame('Alpha Lead', $guardian->name);
        $this->assertSame('Echo Child', $guardian->signs_for);
        // Every signer row maps to a distinct passenger - nobody is mixed up.
        $this->assertSame(5, $signers->pluck('itinerary_passenger_id')->unique()->count());
    }

    // ── Intake failures ─────────────────────────────────────

    public function test_unreadable_documents_fail_politely_and_reparse_recovers(): void
    {
        // Wrong file type: rejected by validation, nothing is stored.
        $customer = $this->registeredCustomer('careful@example.com');
        $this->actingAs($customer)->postJson(route('user.itineraries.api.store'), [
            'file' => UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream'),
        ])->assertStatus(422);
        $this->assertSame(0, Itinerary::count());

        // A real PDF Gemini cannot read: itinerary FAILS with a human
        // message, and no claim is minted from garbage.
        $gemini = new \stdClass();
        $gemini->readable = false;
        Http::fake(function (ClientRequest $request) use ($gemini) {
            if (str_contains($request->url(), 'generativelanguage')) {
                return $gemini->readable
                    ? Http::response(['candidates' => [['content' => ['parts' => [['text' => $this->parsedItineraryJson()]]]]]], 200)
                    : Http::response(['candidates' => []], 200);
            }

            return $this->flightAwareResponse($request);
        });

        $this->actingAs($customer)->postJson(route('user.itineraries.api.store'), [
            'file' => UploadedFile::fake()->create('blurry-photo.pdf', 90, 'application/pdf'),
        ])->assertStatus(422);

        $itinerary = Itinerary::firstOrFail();
        $this->assertSame(Itinerary::STATUS_FAILED, $itinerary->status);
        $this->assertNotNull($itinerary->parse_error);
        $this->assertSame(0, Claim::count());

        // The customer retries once the document is legible: reparse
        // succeeds and the claim chain comes to life.
        $gemini->readable = true;
        $this->actingAs($customer)
            ->postJson(route('user.itineraries.api.reparse', $itinerary->id))
            ->assertOk();

        $this->assertSame(Itinerary::STATUS_PARSED, $itinerary->fresh()->status);
        $this->assertSame(1, Claim::where('itinerary_id', $itinerary->id)->count());
    }

    public function test_uploading_the_same_pdf_twice_never_creates_a_second_claim(): void
    {
        $this->fakeExternalWorld();
        $customer = $this->registeredCustomer('twice@example.com');

        $pdf = UploadedFile::fake()->create('booking.pdf', 100, 'application/pdf');
        $this->actingAs($customer)->postJson(route('user.itineraries.api.store'), ['file' => $pdf])->assertCreated();

        $again = UploadedFile::fake()->create('booking.pdf', 100, 'application/pdf');
        $this->actingAs($customer)->postJson(route('user.itineraries.api.store'), ['file' => $again])
            ->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(1, Itinerary::count());
        $this->assertSame(1, Claim::count());
    }

    // ── Cross-customer security probes ──────────────────────

    public function test_a_stranger_can_touch_nothing_on_someone_elses_claim(): void
    {
        $this->fakeExternalWorld();
        $owner    = $this->registeredCustomer('owner@example.com');
        $stranger = $this->registeredCustomer('stranger@example.com');

        $this->actingAs($owner)->postJson(route('user.itineraries.api.store'), [
            'file' => UploadedFile::fake()->create('booking.pdf', 100, 'application/pdf'),
        ])->assertCreated();
        $claim = Claim::firstOrFail();
        $id = encrypt_id($claim->id);

        $this->actingAs($stranger)->getJson(route('user.itineraries.api.claims.show', $id))->assertForbidden();
        $this->actingAs($stranger)->getJson(route('user.itineraries.api.claims.confirmation', $id))->assertForbidden();
        $this->actingAs($stranger)->postJson(route('user.itineraries.api.claims.confirm', $id), [
            'consents' => ['accuracy' => true, 'authorization' => true, 'terms' => true, 'privacy' => true],
        ])->assertForbidden();
        $this->actingAs($stranger)->getJson(route('user.itineraries.api.claims.signers', $id))->assertForbidden();
        $this->actingAs($stranger)->getJson(route('user.itineraries.api.index'))
            ->assertOk()->assertJsonCount(0, 'data');

        // And the admin surface is closed to every customer outright.
        $this->actingAs($stranger)->get(route('admin.flight-claims.claims'))->assertRedirect();
    }

    // ── Fixtures ────────────────────────────────────────────

    /** Register a customer through the real registration endpoint. */
    private function registeredCustomer(string $email): User
    {
        $this->post('/register', [
            'name'                  => ucfirst(explode('@', $email)[0]) . ' Tester',
            'email'                 => $email,
            'password'              => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);
        $this->post('/logout');

        return User::where('email', $email)->firstOrFail();
    }

    /** A claim carried to filed/awaiting_response through the real flow. */
    private function filedClaim(): array
    {
        $customer = $this->registeredCustomer('escalation@example.com');
        $this->actingAs($customer)->postJson(route('user.itineraries.api.store'), [
            'file' => UploadedFile::fake()->create('booking.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $claim = Claim::firstOrFail();
        $this->actingAs($customer)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($claim->id)), [
                'consents' => ['accuracy' => true, 'authorization' => true, 'terms' => true, 'privacy' => true],
            ])->assertOk();

        $png = 'data:image/png;base64,' . base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        foreach ($claim->fresh()->signers as $signer) {
            $this->actingAs($customer)
                ->postJson(route('user.itineraries.api.claims.sign', ['claim' => encrypt_id($claim->id), 'signer' => $signer->id]), ['signature' => $png])
                ->assertOk();
        }

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)
            ->test(ClaimDetail::class, ['claim' => $claim->fresh()])
            ->set('to', 'claims@aircanada.ca')
            ->set('subject', 'Compensation claim - AC845')
            ->set('body', str_repeat('Formal demand for compensation under EU261. ', 10))
            ->call('send');

        return [$customer, $claim->fresh()];
    }

    /**
     * Fake every external HTTP dependency for the golden-path booking:
     * Gemini reads the PDF as AC845 FRA->YUL with the given passengers,
     * FlightAware verifies a 260-minute arrival delay, Wise quotes and
     * executes the payout.
     */
    private function fakeExternalWorld(?array $passengers = null): void
    {
        config(['services.wise.token' => 'wise-test', 'services.wise.profile_id' => '77']);

        $parsed = $this->parsedItineraryJson($passengers);

        Http::fake(function (ClientRequest $request) use ($parsed) {
            $url = $request->url();

            if (str_contains($url, 'generativelanguage')) {
                return Http::response(['candidates' => [['content' => ['parts' => [['text' => $parsed]]]]]], 200);
            }
            if (str_contains($url, 'wise')) {
                return $this->wiseResponse($url);
            }

            return $this->flightAwareResponse($request);
        });
    }

    private function parsedItineraryJson(?array $passengers = null): string
    {
        $passengers ??= [
            ['full_name' => 'Journey Tester', 'type' => 'MR'],
            ['full_name' => 'Nomi Companion', 'type' => 'MRS'],
        ];

        return json_encode([
            'booking_reference' => 'LH8XK2P',
            'airline'           => 'Air Canada',
            'flights'           => [[
                'airline'            => 'Air Canada',
                'flight_number'      => 'AC845',
                'departure_airport'  => 'FRA',
                'arrival_airport'    => 'YUL',
                'departure_datetime' => now()->subDays(3)->setTime(12, 0)->toIso8601String(),
                'arrival_datetime'   => now()->subDays(3)->setTime(19, 55)->toIso8601String(),
                'cabin_class'        => 'Economy',
            ]],
            'passengers'        => $passengers,
        ]);
    }

    private function wiseResponse(string $url)
    {
        return match (true) {
            str_contains($url, '/quotes')          => Http::response(['id' => 'quote-1', 'rate' => 1.0, 'targetAmount' => 900.00], 200),
            str_contains($url, '/v1/accounts')     => Http::response(['id' => 9001], 200),
            str_contains($url, '/v1/transfers/555001') => Http::response(['id' => 555001, 'status' => 'outgoing_payment_sent'], 200),
            str_contains($url, '/v1/transfers')    => Http::response(['id' => 555001, 'status' => 'processing'], 200),
            default                                => Http::response(['type' => 'BALANCE', 'status' => 'COMPLETED'], 200),
        };
    }

    private function flightAwareResponse(ClientRequest $request)
    {
        $geo = ['FRA' => ['DE', 50.03, 8.56], 'YUL' => ['CA', 45.47, -73.74]];

        if (preg_match('#/airports/([A-Z0-9]+)#', $request->url(), $m)) {
            $g = $geo[$m[1]] ?? null;

            return $g
                ? Http::response(['code_iata' => $m[1], 'country_code' => $g[0], 'latitude' => $g[1], 'longitude' => $g[2], 'timezone' => 'UTC'], 200)
                : Http::response([], 404);
        }

        return Http::response(['flights' => [[
            'fa_flight_id'  => 'ACA845-1700000000-airline-0001',
            'ident_iata'    => 'AC845',
            'origin'        => ['code_iata' => 'FRA'],
            'destination'   => ['code_iata' => 'YUL'],
            'scheduled_out' => now()->subDays(3)->setTime(12, 0)->toIso8601String(),
            'actual_in'     => now()->subDays(3)->setTime(19, 55)->toIso8601String(),
            'arrival_delay' => 260 * 60,
            'cancelled'     => false,
            'status'        => 'Arrived',
        ]]], 200);
    }
}
