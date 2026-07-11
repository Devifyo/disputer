<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateClaim;
use App\Models\Claim;
use App\Models\Itinerary;
use App\Services\Eligibility\ClaimEligibilityService;
use App\Services\FlightAwareService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

        $cancelled = $c->flight_cancelled || $c->disruption_type === 'cancelled';
        if ($cancelled && $c->reported_arrival_delay_minutes === null && !$c->did_not_travel) {
            $missing[] = ['key' => 'rebooking_delay', 'tab' => 'details', 'label' => 'When you finally arrived',
                'hint' => 'Compensation rises if rebooking got you to your destination 6h/9h+ late.'];
        }

        if (empty($c->documents)) {
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
        $c->load(['itinerary.flights', 'events']);
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
            'itinerary_id'      => $c->itinerary_id,
            'submitted_at'      => $c->submitted_at?->format('d M Y'),
            'events'            => $c->events->map(fn ($e) => [
                'label'  => $e->label,
                'status' => $e->status,
                'date'   => $e->happened_at?->format('d F Y'),
            ])->values(),
        ]);
    }
}
