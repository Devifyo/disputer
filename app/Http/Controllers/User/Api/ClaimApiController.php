<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Itinerary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ClaimApiController extends Controller
{
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
            'passenger_name'    => ['required', 'string', 'max:150'],
            'booking_reference' => ['nullable', 'string', 'max:40'],
            'contact_email'     => ['nullable', 'email', 'max:150'],
        ]);

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

        $claim->recordEvent('Claim received', 'done', $claim->created_at);

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

    // ── Helpers ─────────────────────────────────────────────

    private function authorizeOwner(Claim $claim): void
    {
        abort_unless($claim->user_id === Auth::id(), 403);
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

        return array_merge($this->summary($c), [
            'passenger_name'    => $c->passenger_name,
            'booking_reference' => $c->booking_reference,
            'contact_email'     => $c->contact_email,
            'compensation'      => [
                'amount'   => $c->compensation_amount,
                'currency' => $c->compensation_currency,
                'display'  => $c->compensation_amount
                    ? trim(($c->compensation_currency ?: '') . ' ' . number_format((float) $c->compensation_amount, 2))
                    : 'Pending review',
            ],
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
