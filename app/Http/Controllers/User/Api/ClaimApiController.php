<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateClaim;
use App\Models\Claim;
use App\Models\ClaimExpense;
use App\Models\ClaimSigner;
use App\Models\Itinerary;
use App\Models\Setting;
use App\Models\SuccessStory;
use App\Services\Billing\SubscriptionGate;
use App\Services\Claims\ClaimLegalDocumentService;
use App\Services\Claims\ClaimSignatureService;
use App\Services\Claims\ClaimWorkflowService;
use App\Services\Eligibility\ClaimEligibilityService;
use App\Services\FlightAwareService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ClaimApiController extends Controller
{
    public function __construct(private readonly FlightAwareService $flightAware)
    {
    }
    public function index()
    {
        $claims = Claim::where('user_id', Auth::id())
            ->with('itinerary.flights')
            ->latest()
            ->get()
            ->map(fn (Claim $c) => $this->summary($c));

        return response()->json(['data' => $claims]);
    }

    public function store(Request $request)
    {
        SubscriptionGate::authorize($request->user(), 'flight_claims');

        $data = $request->validate([
            'departure_city'    => ['nullable', 'string', 'max:120'],
            'departure_airport' => ['required', 'string', 'max:8'],
            'arrival_city'      => ['nullable', 'string', 'max:120'],
            'arrival_airport'   => ['required', 'string', 'max:8'],
            'airline'           => ['required', 'string', 'max:120'],
            'flight_number'     => ['nullable', 'string', 'max:20'],
            'flight_date'       => ['required', 'date'],
            'disruption_type'   => ['required', Rule::in(array_keys(Claim::DISRUPTIONS))],
            'disruption_note'   => ['required_if:disruption_type,other', 'nullable', 'string', 'min:10', 'max:1000'],
            'passenger_name'    => ['required', 'string', 'max:150'],
            'booking_reference' => ['nullable', 'string', 'max:40'],
            'contact_email'     => ['nullable', 'email', 'max:150'],
            // Fare per person - used for fare-based compensation.
            'ticket_price'      => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'ticket_currency'   => ['nullable', 'string', 'size:3'],
            // Ticket + supporting documents.
            'documents'         => ['nullable', 'array', 'max:6'],
            'documents.*'       => ['file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif', 'max:12288'],
        ], [
            'disruption_note.required_if' => 'Please describe what happened so our team can review it.',
            'disruption_note.min'         => 'Please describe what happened in a bit more detail.',
            'documents.*.mimes' => 'Documents must be PDFs or images (JPG, PNG, WEBP, HEIC).',
            'documents.*.max'   => 'Each document may not be larger than 12 MB.',
        ]);

        $data['ticket_currency'] = isset($data['ticket_currency']) ? strtoupper($data['ticket_currency']) : null;
        $data['documents'] = collect($request->file('documents', []))->map(fn ($file) => [
            'name' => $file->getClientOriginalName(),
            'path' => $file->store('claims/' . Auth::id(), 'local'),
        ])->all() ?: null;

        $data['departure_airport'] = strtoupper($data['departure_airport']);
        $data['arrival_airport']   = strtoupper($data['arrival_airport']);

        // Duplicate guard: same passenger + flight + date (+ booking ref).
        if ($existing = Claim::findDuplicate(Auth::id(), $data)) {
            return response()->json([
                'data'      => $this->detail($existing),
                'duplicate' => true,
                'message'   => 'A claim for this flight and passenger already exists.',
            ], 200);
        }

        $claim = Claim::create(array_merge($data, [
            'user_id' => Auth::id(),
            'status'  => Claim::STATUS_DRAFT,
        ]));

        $claim->recordEvent('Your claim case has been received', 'done', $claim->created_at);
        app(ClaimWorkflowService::class)->audit($claim, 'Claim submitted via the manual funnel', 'customer');
        $claim->recordEvent('Claim under review', 'pending', $claim->created_at, 1);

        // Verify the flight + evaluate eligibility + estimate compensation
        // synchronously, so the user lands on a decided claim. Falls back to
        // the queue if the evaluation hits an error.
        try {
            app(ClaimEligibilityService::class)->evaluate($claim);
            $claim->refresh();
        } catch (Throwable $e) {
            EvaluateClaim::dispatch($claim);
        }

        return response()->json(['data' => $this->detail($claim)], 201);
    }

    public function show(string $claim)
    {
        $id = decrypt_id($claim);
        abort_if($id === null, 404);

        $model = Claim::findOrFail($id);
        $this->authorizeOwner($model);

        return response()->json(['data' => $this->detail($model)]);
    }

    /** Stream a document uploaded with the claim. */
    public function document(string $claim, int $index)
    {
        $id = decrypt_id($claim);
        abort_if($id === null, 404);

        $model = Claim::findOrFail($id);
        $this->authorizeOwner($model);

        $doc = $model->documents[$index] ?? null;
        abort_unless($doc && Storage::disk('local')->exists($doc['path']), 404);

        return Storage::disk('local')->response($doc['path'], $doc['name'] ?? null);
    }

    /**
     * Update missing claim facts (fare, rebooking arrival) and re-price the
     * compensation from them.
     */
    public function updateInfo(Request $request, string $claim, ClaimEligibilityService $service)
    {
        $id = decrypt_id($claim);
        abort_if($id === null, 404);

        $model = Claim::findOrFail($id);
        $this->authorizeOwner($model);

        $data = $request->validate([
            'ticket_price'           => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'ticket_currency'        => ['required_with:ticket_price', 'nullable', 'string', 'size:3'],
            'arrival_delay_minutes'  => ['nullable', 'integer', 'min:0', 'max:10080'],
            'did_not_travel'         => ['nullable', 'boolean'],
        ]);

        $updates = array_filter([
            'ticket_price'                    => $data['ticket_price'] ?? null,
            'ticket_currency'                 => isset($data['ticket_currency']) ? strtoupper($data['ticket_currency']) : null,
            'reported_arrival_delay_minutes'  => $data['arrival_delay_minutes'] ?? null,
            'did_not_travel'                  => !empty($data['did_not_travel']) ? true : null,
        ], fn ($v) => $v !== null);

        abort_if(empty($updates), 422, 'Nothing to update.');

        $model->forceFill($updates)->save();
        $model->recordEvent('You added missing information - compensation re-estimated', 'done', now(), 2);
        $service->priceCompensation($model->refresh());

        return response()->json(['data' => $this->detail($model->refresh()), 'success' => true]);
    }

    /** Append supporting documents to an existing claim. */
    public function addDocuments(Request $request, string $claim)
    {
        $id = decrypt_id($claim);
        abort_if($id === null, 404);

        $model = Claim::findOrFail($id);
        $this->authorizeOwner($model);

        $request->validate([
            'documents'   => ['required', 'array', 'min:1', 'max:6'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif', 'max:12288'],
        ], [
            'documents.*.mimes' => 'Documents must be PDFs or images (JPG, PNG, WEBP, HEIC).',
        ]);

        $existing = $model->documents ?? [];
        abort_if(count($existing) >= 12, 422, 'Document limit reached.');

        foreach ($request->file('documents') as $file) {
            $existing[] = ['name' => $file->getClientOriginalName(), 'path' => $file->store('claims/' . Auth::id(), 'local')];
        }

        $model->forceFill(['documents' => $existing])->save();
        $model->recordEvent('You added supporting documents', 'done', now(), 2);

        return response()->json(['data' => $this->detail($model->refresh()), 'success' => true]);
    }

    /**
     * Upload one out-of-pocket expense receipt. The passenger states what it
     * was and what it cost; an admin verifies it before it is ever claimed.
     */
    public function addExpense(Request $request, string $claim)
    {
        $id = decrypt_id($claim);
        abort_if($id === null, 404);

        $model = Claim::findOrFail($id);
        $this->authorizeOwner($model);

        $data = $request->validate([
            'receipt'     => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif', 'max:12288'],
            'category'    => ['required', 'string', Rule::in(array_keys(ClaimExpense::CATEGORIES))],
            'amount'      => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'currency'    => ['nullable', 'string', 'size:3'],
            'expense_date' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:190'],
        ], [
            'receipt.mimes' => 'Receipts must be a PDF or an image (JPG, PNG, WEBP, HEIC).',
            'receipt.max'   => 'Receipts must be under 12MB.',
        ]);

        abort_if($model->expenses()->count() >= 30, 422, 'Expense receipt limit reached.');

        $file = $request->file('receipt');

        $model->expenses()->create([
            'uploaded_by'       => Auth::id(),
            'category'          => $data['category'],
            'description'       => $data['description'] ?? null,
            'amount'            => $data['amount'] ?? null,
            'currency'          => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'expense_date'      => $data['expense_date'] ?? null,
            'file_path'         => $file->store('claims/' . Auth::id() . '/expenses', 'local'),
            'original_filename' => $file->getClientOriginalName(),
            'mime'              => $file->getClientMimeType(),
            'size_bytes'        => $file->getSize(),
        ]);

        $model->recordEvent('You added an expense receipt', 'done', now(), 2);

        return response()->json(['data' => $this->detail($model->refresh()), 'success' => true]);
    }

    /** Remove a receipt - only while it is still awaiting review. */
    public function removeExpense(string $claim, int $expense)
    {
        $id = decrypt_id($claim);
        abort_if($id === null, 404);

        $model = Claim::findOrFail($id);
        $this->authorizeOwner($model);

        $record = $model->expenses()->findOrFail($expense);
        abort_unless($record->status === ClaimExpense::STATUS_PENDING, 422, 'This receipt has already been reviewed by our team.');

        Storage::disk('local')->delete($record->file_path);
        $record->delete();

        return response()->json(['data' => $this->detail($model->refresh()), 'success' => true]);
    }

    /** Stream a receipt back to the passenger who uploaded it. */
    public function expenseFile(string $claim, int $expense)
    {
        $id = decrypt_id($claim);
        abort_if($id === null, 404);

        $model = Claim::findOrFail($id);
        $this->authorizeOwner($model);

        $record = $model->expenses()->findOrFail($expense);
        abort_unless(Storage::disk('local')->exists($record->file_path), 404);

        return Storage::disk('local')->response($record->file_path, $record->original_filename);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function authorizeOwner(Claim $claim): void
    {
        abort_unless($claim->user_id === Auth::id(), 403);
    }

    /**
     * Facts the customer can still supply to strengthen or complete the
     * claim - drives the red "pending info" indicators in the UI.
     */
    private function missingInfo(Claim $c): array
    {
        if ($c->status === Claim::STATUS_REJECTED) {
            return [];
        }

        $missing = [];

        if ($c->ticket_price === null) {
            $missing[] = ['key' => 'ticket_price', 'tab' => 'details', 'label' => 'Ticket price',
                'hint' => 'Fare-based amounts (downgrades, refunds, US denied boarding) need it.'];
        }

        $cancelled = $c->flight_cancelled
            || in_array($c->disruption_type, ['cancelled', 'schedule_change', 'returned_to_origin'], true);
        if ($cancelled && $c->reported_arrival_delay_minutes === null && !$c->did_not_travel) {
            $missing[] = ['key' => 'rebooking_delay', 'tab' => 'details', 'label' => 'When you finally arrived',
                'hint' => 'Compensation rises if rebooking got you to your destination 6h/9h+ late.'];
        }

        if (empty($c->documents) && !$c->itinerary?->file_path) {
            $missing[] = ['key' => 'documents', 'tab' => 'documents', 'label' => 'Supporting documents',
                'hint' => 'Your ticket, boarding pass or airline emails strengthen the case.'];
        }

        return $missing;
    }

    /** Airport metadata field from FlightAware's forever-cached lookup. */
    private function airportField(?string $code, string $field): mixed
    {
        try {
            return $code ? ($this->flightAware->airportInfo($code)[$field] ?? null) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve display fields, preferring the claim's own snapshot and falling
     * back to the linked itinerary (for claims created before denormalisation).
     */
    private function resolve(Claim $c): array
    {
        $first = $c->itinerary?->flights->first();
        $last  = $c->itinerary?->flights->last();

        return [
            'departure_city'    => $c->departure_city,
            'departure_airport' => $c->departure_airport ?: $first?->departure_airport,
            'arrival_city'      => $c->arrival_city,
            'arrival_airport'   => $c->arrival_airport ?: $last?->arrival_airport,
            'airline'           => $c->airline ?: $c->itinerary?->primary_airline,
            'flight_number'     => $c->flight_number ?: $first?->flight_number,
            'flight_date'       => $c->flight_date ?: $first?->departure_at,
        ];
    }

    private function summary(Claim $c): array
    {
        $r = $this->resolve($c);

        return [
            'id'                => encrypt_id($c->id),
            'number'            => $c->number,
            'reference'         => $c->reference,
            'status'            => $c->status,
            'status_label'      => $c->status_label,
            'departure_city'    => $r['departure_city'],
            'departure_airport' => $r['departure_airport'],
            'arrival_city'      => $r['arrival_city'],
            'arrival_airport'   => $r['arrival_airport'],
            'airline'           => $r['airline'],
            'flight_number'     => $r['flight_number'],
            'flight_date'       => $r['flight_date']?->format('d M Y'),
            'disruption_type'   => $c->disruption_type,
            'disruption_label'  => $c->disruption_label,
            'created_at'        => $c->created_at->format('d M Y'),
        ];
    }

    private function detail(Claim $c): array
    {
        $c->load(['itinerary.flights', 'events', 'signers', 'expenses']);
        $r = $this->resolve($c);

        $documents = [];
        if ($c->itinerary && $c->itinerary->file_path) {
            $documents[] = [
                'name' => $c->itinerary->original_filename,
                'url'  => route('user.itineraries.file', $c->itinerary),
            ];
        }
        foreach ($c->documents ?? [] as $index => $doc) {
            $documents[] = [
                'name' => $doc['name'] ?? ('Document ' . ($index + 1)),
                'url'  => route('user.itineraries.api.claims.document', ['claim' => encrypt_id($c->id), 'index' => $index]),
            ];
        }

        return array_merge($this->summary($c), [
            'expenses'          => $c->expenses->map(fn (ClaimExpense $e) => [
                'id'          => $e->id,
                'category'    => $e->category,
                'category_label' => $e->categoryLabel(),
                'description' => $e->description,
                'amount'      => $e->amount,
                'currency'    => $e->currency,
                'display'     => $e->formattedAmount(),
                'date'        => $e->expense_date?->format('d M Y'),
                'filename'    => $e->original_filename,
                'status'      => $e->status,
                // Only the customer-facing reason - internal notes never ship.
                'reason'      => $e->review_reason,
                'url'         => route('user.itineraries.api.claims.expense', ['claim' => encrypt_id($c->id), 'expense' => $e->id]),
                'locked'      => $e->status !== ClaimExpense::STATUS_PENDING,
            ])->values()->all(),
            'expense_categories' => ClaimExpense::CATEGORIES,
            'passenger_name'    => $c->passenger_name,
            'disruption_note'   => $c->disruption_note,
            'booking_reference' => $c->booking_reference,
            'contact_email'     => $c->contact_email,
            'compensation'      => [
                'amount'   => $c->compensation_amount,
                'currency' => $c->compensation_currency,
                'basis'    => $c->compensation_basis,
                'breakdown' => $c->compensation_explanation ?: null,
                'ticket_price'    => $c->ticket_price,
                'ticket_currency' => $c->ticket_currency,
                'display'  => $c->compensation_amount
                    ? trim(($c->compensation_currency ?: '') . ' ' . number_format((float) $c->compensation_amount, 2))
                    : 'Pending review',
            ],
            'flight_verified'   => (bool) $c->flight_verified_at,
            'missing'           => $this->missingInfo($c),
            'flight_tracking'   => $c->flight_snapshot ? $c->flight_snapshot + [
                'origin_timezone'          => $this->airportField($r['departure_airport'], 'timezone'),
                'destination_timezone'     => $this->airportField($r['arrival_airport'], 'timezone'),
                'origin_airport_name'      => $this->airportField($r['departure_airport'], 'name'),
                'destination_airport_name' => $this->airportField($r['arrival_airport'], 'name'),
            ] : null,
            'eligibility'       => $c->eligibility_evaluated_at ? [
                'status'     => $c->eligibility_status,
                'regulation' => $c->eligibility_regulation,
                'article'    => $c->eligibility_article,
                'confidence' => $c->eligibility_confidence,
                'reason'     => $c->eligibility_reason,
                'decided_by' => $c->eligibility_decision_source === 'admin' ? 'team' : 'engine',
            ] : null,
            'documents'         => $documents,
            'legal_documents'   => $this->legalDocuments($c),
            'itinerary_id'      => $c->itinerary_id,
            'submitted_at'      => $c->submitted_at?->format('d M Y'),
            'passengers'        => $c->passengerNames(),
            'passenger_list'    => $c->itinerary?->passengers->map(fn ($p) => [
                'name'  => $p->full_name,
                'minor' => in_array(strtoupper((string) $p->type), ['CHD', 'INF'], true),
            ])->values()->all() ?: [['name' => $c->passenger_name, 'minor' => false]],
            'passengers_locked' => (bool) $c->confirmed_at,
            'payout'            => $this->payout($c),
            'workflow'          => [
                'can_confirm'         => $c->status === Claim::STATUS_ELIGIBLE && !$c->confirmed_at,
                'confirmed_at'        => $c->confirmed_at?->format('d M Y'),
                'awaiting_signatures' => (bool) ($c->confirmed_at && !$c->signed_at),
                'signed_at'           => $c->signed_at?->format('d M Y'),
                'signers_total'       => $c->signers->count(),
                'signers_signed'      => $c->signers->where('status', ClaimSigner::STATUS_SIGNED)->count(),
            ],
            'events'            => $c->events->map(fn ($e) => [
                'label'  => $e->label,
                'status' => $e->status,
                'date'   => $e->happened_at?->format('d F Y'),
            ])->values(),
        ]);
    }

    // ── Claim confirmation & e-signatures ───────────────────

    /** Everything the confirmation screen presents - display only, no calculation. */
    public function confirmation(string $claim)
    {
        $model = $this->resolveOwned($claim);
        abort_unless($model->status === Claim::STATUS_ELIGIBLE, 422, 'This claim is not ready for confirmation yet.');

        $model->load('itinerary.passengers', 'events');
        $r        = $this->resolve($model);
        $snapshot = $model->flight_snapshot ?? [];
        $depTz    = $this->airportField($r['departure_airport'], 'timezone');
        $arrTz    = $this->airportField($r['arrival_airport'], 'timezone');

        $passengers = $model->itinerary?->passengers->map(fn ($p) => [
            'name'  => $p->full_name,
            'minor' => in_array(strtoupper((string) $p->type), ['CHD', 'INF'], true),
        ])->values()->all() ?: [['name' => $model->passenger_name, 'minor' => false]];

        return response()->json(['data' => [
            'id'        => encrypt_id($model->id),
            'reference' => $model->reference,
            'number'    => $model->number,
            'confirmed' => (bool) $model->confirmed_at,
            'flight'    => [
                'airline'             => $r['airline'],
                'flight_number'       => $r['flight_number'],
                'booking_reference'   => $model->booking_reference,
                'departure_airport'   => $r['departure_airport'],
                'departure_name'      => $this->airportField($r['departure_airport'], 'name'),
                'arrival_airport'     => $r['arrival_airport'],
                'arrival_name'        => $this->airportField($r['arrival_airport'], 'name'),
                'travel_date'         => $model->flight_date?->format('d F Y'),
                'scheduled_departure' => $this->localTime($snapshot['scheduled_departure'] ?? null, $depTz),
                'actual_departure'    => $this->localTime($snapshot['actual_departure'] ?? null, $depTz),
                'scheduled_arrival'   => $this->localTime($snapshot['scheduled_arrival'] ?? null, $arrTz),
                'actual_arrival'      => $this->localTime($snapshot['actual_arrival'] ?? null, $arrTz),
            ],
            'passengers' => $passengers,
            // The SPA warns BEFORE the confirm button does: a free user with
            // a multi-passenger booking needs Plus when the admin gates it.
            'multi_passenger_locked' => count($model->passengerNames()) > 1
                && !SubscriptionGate::allows($model->user, 'multi_passenger'),
            'disruption' => [
                'headline' => $this->disruptionHeadline($model),
                'verified' => (bool) $model->flight_verified_at,
                'timeline' => $model->events->map(fn ($e) => [
                    'label' => $e->label, 'status' => $e->status, 'date' => $e->happened_at?->format('d M Y'),
                ])->values(),
            ],
            'eligibility' => [
                'regulation'   => $model->eligibility_regulation,
                'article'      => $model->eligibility_article,
                'jurisdiction' => $this->jurisdictionLabel($model),
                'reason'       => $model->eligibility_reason,
            ],
            'payout' => $this->payout($model),
            'social' => [
                'claims_won' => Setting::get('claims.social_claims_won', '12,000+'),
                'recovered'  => Setting::get('claims.social_recovered', 'EUR 6.4M'),
                'testimonials' => SuccessStory::where('is_published', true)->latest()->take(3)
                    ->get(['first_name', 'story'])
                    ->map(fn ($s) => ['name' => $s->first_name, 'story' => Str::limit($s->story, 180)]),
            ],
            'plus_selected' => $model->plus_selected,
            'plus_promo_enabled' => (bool) Setting::get('app.plus_promo_enabled', true),
        ]]);
    }

    /**
     * Correct parsed passenger details (name, under-18 flag) - allowed until
     * the claim is confirmed; the names and the minor flags drive the
     * authorisation documents and the guardian signing flow.
     */
    public function updatePassengers(Request $request, string $claim)
    {
        $model = $this->resolveOwned($claim);
        abort_if($model->confirmed_at !== null, 422, 'Passenger details are locked once the claim is confirmed - contact support to change them.');

        $data = $request->validate([
            'passengers'         => ['required', 'array', 'min:1', 'max:9'],
            'passengers.*.name'  => ['required_with:passengers.*.minor', 'string', 'max:190'],
            'passengers.*.minor' => ['sometimes', 'boolean'],
            'passengers.*'       => ['required'],
        ]);

        // Accept plain names or {name, minor} objects.
        $entries = collect($data['passengers'])->map(fn ($p) => is_array($p)
            ? ['name' => trim((string) ($p['name'] ?? '')), 'minor' => array_key_exists('minor', $p) ? (bool) $p['minor'] : null]
            : ['name' => trim((string) $p), 'minor' => null]
        )->filter(fn ($p) => $p['name'] !== '')->values();

        abort_if($entries->isEmpty(), 422, 'Passenger names cannot be empty.');

        $model->load('itinerary.passengers');
        $existing = $model->itinerary?->passengers;

        if ($existing && $existing->isNotEmpty()) {
            abort_unless($entries->count() === $existing->count(), 422, 'Provide a name for every passenger on the booking.');

            foreach ($existing->values() as $i => $passenger) {
                $entry   = $entries[$i];
                $isMinor = in_array(strtoupper((string) $passenger->type), ['CHD', 'INF'], true);

                $type = $passenger->type;
                if ($entry['minor'] === true && !$isMinor) {
                    $type = 'CHD';
                } elseif ($entry['minor'] === false && $isMinor) {
                    $type = null;
                }

                $passenger->update(['full_name' => $entry['name'], 'type' => $type]);
            }
        }

        $model->forceFill(['passenger_name' => $entries[0]['name']])->save();
        $model->recordEvent('You corrected the passenger details', 'done', now(), 2);

        return response()->json(['data' => $this->detail($model->fresh()), 'success' => true]);
    }

    /** Consent + confirmation: unlocks document generation and the signature stage. */
    public function confirm(Request $request, string $claim, ClaimSignatureService $signatures)
    {
        $model = $this->resolveOwned($claim);
        abort_unless($model->status === Claim::STATUS_ELIGIBLE, 422, 'This claim is not ready for confirmation yet.');

        $data = $request->validate([
            'consents'               => ['required', 'array'],
            'consents.accuracy'      => ['accepted'],
            'consents.authorization' => ['accepted'],
            'consents.terms'         => ['accepted'],
            'consents.privacy'       => ['accepted'],
            'plus'                   => ['sometimes', 'boolean'],
        ], [
            'consents.*.accepted' => 'All confirmations are required before we can proceed.',
        ]);

        // Family/multi-passenger claims: confirmation is the one door every
        // claim passes through (manual funnel, ticket upload, emailed
        // ticket), so the gate catches all of them. Single-passenger claims
        // are unaffected; the admin toggle decides whether this runs at all.
        if (count($model->passengerNames()) > 1) {
            SubscriptionGate::authorize($request->user(), 'multi_passenger');
        }

        if (!$model->confirmed_at) {
            $model->forceFill([
                'confirmed_at'  => now(),
                'plus_selected' => (bool) ($data['plus'] ?? false),
                'consents'      => [
                    'accuracy' => true, 'authorization' => true, 'terms' => true, 'privacy' => true,
                    'accepted_at' => now()->toIso8601String(), 'ip' => $request->ip(),
                ],
            ])->save();

            $model->recordEvent('You confirmed the claim details and authorised Unjamm', 'done', now(), 2);

            $workflow = app(ClaimWorkflowService::class);
            $workflow->audit($model, 'Claim confirmed - consent recorded', 'customer', null, 'IP ' . $request->ip());
            if ($workflow->can($model, 'awaiting_signature')) {
                $workflow->transition($model, 'awaiting_signature', 'customer', null, 'Customer confirmed and authorised Unjamm.');
            }
        } elseif ($request->has('plus')) {
            $model->forceFill(['plus_selected' => (bool) $data['plus']])->save();
        }

        $signatures->setup($model->fresh());

        return response()->json(['data' => ['next' => 'sign'], 'success' => true]);
    }

    /** Signature stage state: every required signer and how to sign. */
    public function signers(string $claim, ClaimSignatureService $signatures)
    {
        $model = $this->resolveOwned($claim);
        $model->signers->each(fn (ClaimSigner $s) => $signatures->reconcile($s));
        $model->refresh()->load('signers');

        return response()->json(['data' => [
            'mode'       => $signatures->provider()->name(),
            'all_signed' => $model->signaturesComplete(),
            'signed_at'  => $model->signed_at?->format('d M Y'),
            'assignment_url' => $model->assignment_path
                ? route('user.itineraries.api.claims.legal', ['claim' => encrypt_id($model->id), 'doc' => 'assignment'])
                : null,
            'signers'    => $model->signers->map(fn (ClaimSigner $s) => $this->signerPayload($model, $s))->values(),
        ]]);
    }

    /** Embedded signing URL (Dropbox Sign) for an in-app signer. */
    public function signUrl(string $claim, int $signer, ClaimSignatureService $signatures)
    {
        $model = $this->resolveOwned($claim);
        $s     = $model->signers()->findOrFail($signer);

        return response()->json(['data' => ['sign_url' => $signatures->provider()->embeddedSignUrl($s),
            'client_id' => config('services.dropbox_sign.client_id')]]);
    }

    /** Built-in signature pad submission for a signer the account holder covers. */
    public function sign(Request $request, string $claim, int $signer, ClaimSignatureService $signatures)
    {
        $model = $this->resolveOwned($claim);
        $s     = $model->signers()->findOrFail($signer);

        abort_unless($s->status === ClaimSigner::STATUS_PENDING, 422, 'This authorisation is already signed.');

        $data = $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:700000'],
        ]);

        $signatures->recordNativeSignature($s, $data['signature']);

        return $this->signers($claim, $signatures);
    }

    /** Email an additional adult passenger their individual signing request. */
    public function inviteSigner(Request $request, string $claim, int $signer, ClaimSignatureService $signatures)
    {
        $model = $this->resolveOwned($claim);
        $s     = $model->signers()->findOrFail($signer);

        abort_unless($s->status === ClaimSigner::STATUS_PENDING, 422, 'This authorisation is already signed.');

        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);

        $signatures->invite($s, $data['email']);
        $model->recordEvent("Signature request sent to {$s->name}", 'done', now(), 2);

        return $this->signers($claim, $signatures);
    }

    /** Stream a generated authorisation document (poa-{signerId} or assignment). */
    public function legalDocument(string $claim, string $doc)
    {
        $model = $this->resolveOwned($claim);

        $path = $doc === 'assignment'
            ? $model->assignment_path
            : (str_starts_with($doc, 'poa-')
                ? $model->signers()->findOrFail((int) substr($doc, 4))->poa_path
                : null);

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        // Documents are regenerated (signature embedded, wording updates) -
        // never let the browser serve a stale cached copy.
        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type'  => 'application/pdf',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function resolveOwned(string $claim): Claim
    {
        $id = decrypt_id($claim);
        abort_if($id === null, 404);

        $model = Claim::findOrFail($id);
        $this->authorizeOwner($model);

        return $model;
    }

    private function signerPayload(Claim $model, ClaimSigner $s): array
    {
        return [
            'id'        => $s->id,
            'name'      => $s->name,
            'role'      => $s->role,
            'covers'    => $s->signs_for ?: $s->name,
            'email'     => $s->email,
            'status'    => $s->status,
            'signed_at' => $s->signed_at?->format('d M Y'),
            'invited_at' => $s->invited_at?->format('d M Y'),
            // The account holder signs their own (and guardian) documents
            // in-app; other adults get an emailed signing request.
            'signs_in_app' => $s->email !== null && strcasecmp($s->email, (string) Auth::user()->email) === 0,
            'poa_url'   => $s->poa_path
                ? route('user.itineraries.api.claims.legal', ['claim' => encrypt_id($model->id), 'doc' => "poa-{$s->id}"])
                : null,
        ];
    }

    /** The claim's authorisation documents (per-passenger POA + Assignment). */
    private function legalDocuments(Claim $c): array
    {
        $docs = [];

        foreach ($c->signers as $s) {
            if ($s->poa_path) {
                $docs[] = [
                    'name'   => 'Power of Attorney - ' . ($s->signs_for ?: $s->name),
                    'signed' => $s->status === ClaimSigner::STATUS_SIGNED,
                    'url'    => route('user.itineraries.api.claims.legal', ['claim' => encrypt_id($c->id), 'doc' => "poa-{$s->id}"]),
                ];
            }
        }

        if ($c->assignment_path) {
            $docs[] = [
                'name'   => 'Assignment of Claims',
                'signed' => $c->signers->first()?->status === ClaimSigner::STATUS_SIGNED,
                'url'    => route('user.itineraries.api.claims.legal', ['claim' => encrypt_id($c->id), 'doc' => 'assignment']),
            ];
        }

        return $docs;
    }

    /** Booking totals for display - amounts come from the Eligibility Engine only. */
    private function payout(Claim $c): ?array
    {
        if ($c->compensation_amount === null) {
            return null;
        }

        $count = max(1, count($c->passengerNames()));
        $per   = (float) $c->compensation_amount;
        $pct   = (float) Setting::get('claims.success_fee_percent', 25);
        $gross = round($per * $count, 2);
        $fee   = round($gross * $pct / 100, 2);

        return [
            'currency'        => $c->compensation_currency,
            'per_passenger'   => number_format($per, 2, '.', ''),
            'passenger_count' => $count,
            'gross'           => number_format($gross, 2, '.', ''),
            'fee_percent'     => $pct + 0,
            'fee'             => number_format($fee, 2, '.', ''),
            'net'             => number_format($gross - $fee, 2, '.', ''),
        ];
    }

    private function disruptionHeadline(Claim $c): string
    {
        if ($c->flight_cancelled || $c->disruption_type === 'cancelled') {
            return 'Flight cancelled';
        }

        $delay = $c->flight_arrival_delay_minutes ?? $c->reported_arrival_delay_minutes;
        if ($c->disruption_type === 'delayed' && $delay) {
            return sprintf('Flight arrived %dh %02dm late', intdiv($delay, 60), $delay % 60);
        }

        return $c->disruption_label ?: 'Flight disrupted';
    }

    private function jurisdictionLabel(Claim $c): string
    {
        return match (ClaimLegalDocumentService::jurisdiction($c)) {
            'UK'    => 'United Kingdom',
            'CA'    => 'Canada',
            'US'    => 'United States',
            default => 'European Union',
        };
    }

    private function localTime(?string $iso, ?string $tz): ?string
    {
        if (!$iso) {
            return null;
        }

        try {
            $time = Carbon::parse($iso);

            return ($tz ? $time->tz($tz) : $time)->format('D d M, H:i');
        } catch (Throwable) {
            return null;
        }
    }
}
