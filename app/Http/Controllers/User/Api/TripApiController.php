<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncTripFlight;
use App\Models\Claim;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Services\FlightAwareService;
use App\Services\ItineraryParserService;
use App\Services\TripMonitoringService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * "Protect Your Trip" - future trips the user registers ahead of travel,
 * either manually (funnel form) or by uploading an itinerary. Trips never
 * create claims; they exist so Unjamm can monitor upcoming flights.
 */
class TripApiController extends Controller
{
    public function __construct(private readonly FlightAwareService $flightAware)
    {
    }

    public function index()
    {
        $trips = Trip::where('user_id', Auth::id())
            ->withExists('claims')
            ->orderByRaw('departure_date IS NULL, departure_date ASC')
            ->get()
            ->map(fn (Trip $t) => $this->summary($t));

        return response()->json(['data' => $trips]);
    }

    /** Manual funnel: route → flight → passengers → ticket upload. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'departure_airport' => ['required', 'string', 'max:8'],
            'arrival_airport'   => ['required', 'string', 'max:8'],
            'departure_city'    => ['nullable', 'string', 'max:120'],
            'arrival_city'      => ['nullable', 'string', 'max:120'],
            'airline'           => ['nullable', 'string', 'max:120'],
            'flight_number'     => ['required', 'string', 'max:12'],
            'departure_date'    => ['required', 'date', 'after_or_equal:today'],
            'departure_time'    => ['nullable', 'date_format:H:i'],
            'booking_reference' => ['nullable', 'string', 'max:32'],
            'passengers'        => ['required', 'array', 'min:1'],
            'passengers.*'      => ['required', 'string', 'max:190'],
            // The ticket shows the fare paid - required to value the claim
            // if this flight ends up disrupted.
            'ticket'            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif', 'max:12288'],
        ], [
            'departure_date.after_or_equal' => 'The departure date must be in the future. Trips can only be protected before you fly.',
            'passengers.required'           => 'Add at least one passenger.',
            'passengers.*.required'         => 'Passenger name cannot be empty.',
            'ticket.required'               => 'Please attach your ticket or booking confirmation. We need it to know your ticket price if a claim arises.',
            'ticket.mimes'                  => 'The ticket must be a PDF or an image (JPG, PNG, WEBP, HEIC).',
            'ticket.max'                    => 'The ticket file may not be larger than 12 MB.',
        ]);

        $passengers = array_values(array_filter(array_map('trim', $data['passengers'])));
        if (empty($passengers)) {
            // 'required' lets whitespace-only names through; re-check after trimming.
            throw ValidationException::withMessages(['passengers' => 'Add at least one passenger.']);
        }

        $trip = Trip::create([
            'user_id'           => Auth::id(),
            'source'            => Trip::SOURCE_MANUAL,
            'status'            => Trip::STATUS_PROTECTED,
            'departure_airport' => strtoupper($data['departure_airport']),
            'arrival_airport'   => strtoupper($data['arrival_airport']),
            'departure_city'    => $data['departure_city'] ?? null,
            'arrival_city'      => $data['arrival_city'] ?? null,
            'airline'           => $data['airline'] ?? null,
            'flight_number'     => strtoupper($data['flight_number']),
            'departure_date'    => $data['departure_date'],
            'departure_time'    => $data['departure_time'] ?? null,
            'booking_reference' => $data['booking_reference'] ?? null,
            'passenger_name'    => $passengers[0] ?? null,
            'passengers'        => $passengers,
            'ticket_file_path'  => $request->file('ticket')->store('trips/' . Auth::id(), 'local'),
        ]);

        $this->startMonitoring($trip);

        return response()->json([
            'data'    => $this->summary($trip),
            'success' => true,
            'message' => 'Your trip is now protected by Unjamm.',
        ], 201);
    }

    /** Upload funnel: itinerary file → AI parse → one trip per future flight. */
    public function upload(Request $request, ItineraryParserService $parser)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif', 'max:12288'],
        ], [
            'file.required' => 'Please choose a PDF or photo of your itinerary to upload.',
            'file.mimes'    => 'The itinerary must be a PDF or an image (JPG, PNG, WEBP, HEIC).',
            'file.max'      => 'The file may not be larger than 12 MB.',
        ]);

        $upload = $request->file('file');
        $hash   = @hash_file('sha256', $upload->getRealPath()) ?: null;

        if ($hash) {
            $existing = Itinerary::where('user_id', Auth::id())
                ->where('purpose', Itinerary::PURPOSE_TRIP)
                ->where('file_hash', $hash)
                ->latest()
                ->first();
            if ($existing) {
                return response()->json([
                    'data'      => Trip::where('itinerary_id', $existing->id)->get()->map(fn ($t) => $this->summary($t)),
                    'success'   => true,
                    'duplicate' => true,
                    'message'   => 'You already uploaded this itinerary. These trips are already protected.',
                ], 200);
            }
        }

        $itinerary = Itinerary::create([
            'user_id'           => Auth::id(),
            'original_filename' => $upload->getClientOriginalName(),
            'file_path'         => $upload->store('itineraries/' . Auth::id(), 'local'),
            'file_size'         => $upload->getSize(),
            'mime_type'         => $upload->getMimeType() ?: 'application/pdf',
            'file_hash'         => $hash,
            'status'            => Itinerary::STATUS_UPLOADED,
            'purpose'           => Itinerary::PURPOSE_TRIP,
        ]);

        $parsed = $parser->parse($itinerary);
        $itinerary->refresh()->load('flights', 'passengers');

        if (!$parsed) {
            $error = $itinerary->parse_error ?: 'We could not read that itinerary.';
            // Discard the failed row so retrying the same file isn't treated
            // as an "already protected" duplicate.
            $this->discardItinerary($itinerary);

            return response()->json(['success' => false, 'message' => $error], 422);
        }

        $futureFlights = $itinerary->flights->filter(
            fn ($f) => $f->departure_at !== null && $f->departure_at->isFuture()
        );

        if ($futureFlights->isEmpty()) {
            $this->discardItinerary($itinerary);

            return response()->json([
                'success' => false,
                'message' => 'This itinerary has no upcoming flights, so it cannot be protected. If your flight was already disrupted, please use Flight Disputes to make a claim.',
            ], 422);
        }

        $passengers = $itinerary->passengers->pluck('full_name')->filter()->values()->all();

        $trips = $futureFlights->map(fn ($f) => Trip::create([
            'user_id'           => Auth::id(),
            'itinerary_id'      => $itinerary->id,
            'source'            => Trip::SOURCE_UPLOAD,
            'status'            => Trip::STATUS_PROTECTED,
            'airline'           => $f->airline ?: $itinerary->primary_airline,
            'flight_number'     => $f->flight_number,
            'departure_airport' => $f->departure_airport,
            'arrival_airport'   => $f->arrival_airport,
            'departure_date'    => $f->departure_at?->toDateString(),
            'departure_time'    => $f->departure_at?->format('H:i'),
            'booking_reference' => $itinerary->booking_reference,
            'passenger_name'    => $passengers[0] ?? null,
            'passengers'        => $passengers,
            'ticket_file_path'  => $itinerary->file_path,
        ]))->values();

        $trips->each(fn (Trip $t) => $this->startMonitoring($t));

        return response()->json([
            'data'    => $trips->map(fn ($t) => $this->summary($t)),
            'success' => true,
            'message' => $trips->count() === 1
                ? 'Your trip is now protected by Unjamm.'
                : $trips->count() . ' flights are now protected by Unjamm.',
        ], 201);
    }

    public function show(Trip $trip)
    {
        abort_unless($trip->user_id === Auth::id(), 403);

        return response()->json(['data' => $this->summary($trip)]);
    }

    /**
     * Monitoring history shown to the customer: detected flight events only.
     * Raw poll logs (trip_monitor_logs) are an internal audit trail - HTTP
     * codes and provider error strings don't belong in the customer UI.
     */
    public function monitoring(Trip $trip)
    {
        abort_unless($trip->user_id === Auth::id(), 403);

        return response()->json([
            'data' => [
                'events' => $trip->events->map(fn ($e) => [
                    'id'             => $e->id,
                    'type'           => $e->type,
                    'description'    => $e->description,
                    'qualifying'     => $e->qualifying,
                    'detected_at'    => $e->detected_at->toIso8601String(),
                    'detected_human' => $e->detected_at->diffForHumans(),
                ]),
            ],
        ]);
    }

    /**
     * Turn an eligible trip into compensation claims - one per passenger,
     * pre-filled from the trip's verified flight data. Idempotent: calling
     * again returns the existing claims.
     */
    public function createClaim(Trip $trip)
    {
        abort_unless($trip->user_id === Auth::id(), 403);

        if ($trip->eligibility_status !== 'eligible') {
            return response()->json([
                'success' => false,
                'message' => 'This trip is not eligible for a claim.',
            ], 422);
        }

        if ($trip->claims()->exists()) {
            return response()->json([
                'data'      => $this->claimRefs($trip),
                'success'   => true,
                'duplicate' => true,
                'message'   => 'Claims for this trip already exist.',
            ]);
        }

        $passengers = $trip->passengers ?: array_values(array_filter([$trip->passenger_name]));
        if (empty($passengers)) {
            return response()->json(['success' => false, 'message' => 'This trip has no passengers to claim for.'], 422);
        }

        $disruption = $trip->flight_status === Trip::FLIGHT_CANCELLED ? 'cancelled' : 'delayed';

        foreach ($passengers as $passenger) {
            $claim = Claim::create([
                'user_id'           => $trip->user_id,
                'trip_id'           => $trip->id,
                'itinerary_id'      => $trip->itinerary_id,
                'status'            => Claim::STATUS_PENDING_ELIGIBILITY,
                'departure_city'    => $trip->departure_city,
                'departure_airport' => $trip->departure_airport,
                'arrival_city'      => $trip->arrival_city,
                'arrival_airport'   => $trip->arrival_airport,
                'airline'           => $trip->airline,
                'flight_number'     => $trip->flight_number,
                'flight_date'       => $trip->departure_date?->toDateString(),
                'disruption_type'   => $disruption,
                'passenger_name'    => $passenger,
                'booking_reference' => $trip->booking_reference,
                'contact_email'     => $trip->user?->email,
            ]);

            $claim->recordEvent('Claim created from your protected trip', 'done', now());
            $claim->recordEvent(
                sprintf('Found eligible under %s (%s) at %d%% confidence', $trip->eligibility_regulation, $trip->eligibility_article, $trip->eligibility_confidence),
                'done',
                now(),
                1
            );
            $claim->recordEvent('Claim under review', 'pending', now(), 2);
        }

        $trip->events()->create([
            'type'        => 'claim_created',
            'description' => count($passengers) === 1
                ? 'Compensation claim created from this trip.'
                : count($passengers) . ' compensation claims created from this trip (one per passenger).',
            'detected_at' => now(),
        ]);

        return response()->json([
            'data'    => $this->claimRefs($trip),
            'success' => true,
            'message' => count($passengers) === 1
                ? 'Your claim has been created.'
                : count($passengers) . ' claims have been created - one per passenger.',
        ], 201);
    }

    /** Manual "refresh now" - one synchronous FlightAware sync, lightly throttled. */
    public function sync(Trip $trip, TripMonitoringService $monitor)
    {
        abort_unless($trip->user_id === Auth::id(), 403);

        $recentlySynced = $trip->last_synced_at && $trip->last_synced_at->gt(now()->subMinute());

        if (!$recentlySynced && $trip->monitoring_status !== Trip::MONITORING_COMPLETED) {
            $monitor->sync($trip, 'manual');
            $trip->refresh();
        }

        return response()->json(['data' => $this->summary($trip), 'success' => true]);
    }

    /** Stream the ticket / itinerary file attached to this trip. */
    public function ticket(Trip $trip)
    {
        abort_unless($trip->user_id === Auth::id(), 403);
        abort_unless($trip->ticket_file_path && $this->disk()->exists($trip->ticket_file_path), 404);

        return $this->disk()->response($trip->ticket_file_path, 'ticket-' . $trip->id);
    }

    public function destroy(Trip $trip)
    {
        abort_unless($trip->user_id === Auth::id(), 403);

        // Manual trips own their ticket file (upload trips share the itinerary's).
        if ($trip->source === Trip::SOURCE_MANUAL && $trip->ticket_file_path) {
            $this->disk()->delete($trip->ticket_file_path);
        }

        // Remove the backing itinerary file when this was its only trip.
        if ($trip->itinerary_id) {
            $siblings = Trip::where('itinerary_id', $trip->itinerary_id)->where('id', '!=', $trip->id)->exists();
            if (!$siblings && $trip->itinerary) {
                $this->discardItinerary($trip->itinerary);
            }
        }

        $trip->forceDelete(); // the ticket file is gone, so keep row and storage consistent

        return response()->json(['success' => true]);
    }

    // ── Helpers ─────────────────────────────────────────────

    /** Register a freshly created trip with FlightAware and start monitoring. */
    private function startMonitoring(Trip $trip): void
    {
        // next_poll_at lets trips:monitor pick the trip up even if this
        // queued registration job is lost.
        $trip->forceFill([
            'monitoring_status' => Trip::MONITORING_PENDING,
            'next_poll_at'      => now(),
        ])->save();

        SyncTripFlight::dispatch($trip);
    }

    private const STATUS_LABELS = [
        'scheduled'                   => 'Scheduled',
        'monitoring'                  => 'Monitoring',
        'on_time'                     => 'On Time',
        'delayed'                     => 'Delayed',
        'cancelled'                   => 'Cancelled',
        'completed'                   => 'Completed',
        'potentially_eligible'        => 'Potentially Eligible',
        'eligibility_review_pending'  => 'Eligibility Review Pending',
        'eligible'                    => 'Eligible for Compensation',
        'not_eligible'                => 'Not Eligible',
        'claim_filed'                 => 'Claim Filed',
    ];

    /** Lightweight references to the claims created from a trip. */
    private function claimRefs(Trip $t): array
    {
        return $t->claims()->get()->map(fn (Claim $c) => [
            'id'             => encrypt_id($c->id),
            'number'         => $c->number,
            'passenger_name' => $c->passenger_name,
            'status_label'   => $c->status_label,
        ])->all();
    }

    private function summary(Trip $t): array
    {
        return [
            'id'                => $t->id,
            'status'            => $t->status,
            'protected'         => $t->status === Trip::STATUS_PROTECTED,
            'upcoming'          => $t->isUpcoming(),
            'source'            => $t->source,
            'airline'           => $t->airline,
            'flight_number'     => $t->flight_number,
            'departure_airport' => $t->departure_airport,
            'departure_city'    => $t->departure_city,
            'arrival_airport'   => $t->arrival_airport,
            'arrival_city'      => $t->arrival_city,
            'departure_date'    => $t->departure_date?->format('d M Y'),
            'departure_time'    => $t->departure_time,
            'booking_reference' => $t->booking_reference,
            'passenger_name'    => $t->passenger_name,
            'passengers'        => $passengers = $t->passengers ?: array_values(array_filter([$t->passenger_name])),
            'passengers_count'  => count($passengers),
            'has_ticket'        => (bool) $t->ticket_file_path,
            'ticket_url'        => $t->ticket_file_path ? route('user.itineraries.api.trips.ticket', $t) : null,
            'ticket_price'      => $t->ticket_price,
            'ticket_currency'   => $t->ticket_currency,
            'delay_score'       => $t->delay_score,
            'created_at_human'  => $t->created_at->diffForHumans(),

            // FlightAware live monitoring
            'display_status'          => $status = $t->displayStatus(),
            'display_status_label'    => self::STATUS_LABELS[$status] ?? ucfirst($status),
            'flight_status'           => $t->flight_status,
            'flight_status_text'      => $t->flight_status_text,
            'flight_phase'            => $t->flightPhase(),
            'origin_timezone'         => $this->airportField($t->departure_airport, 'timezone'),
            'destination_timezone'    => $this->airportField($t->arrival_airport, 'timezone'),
            'origin_airport_name'      => $this->airportField($t->departure_airport, 'name'),
            'destination_airport_name' => $this->airportField($t->arrival_airport, 'name'),
            'monitoring_status'       => $t->monitoring_status,
            'monitoring'              => $t->monitoring_status === Trip::MONITORING_ACTIVE,
            'potentially_eligible'    => $t->potentially_eligible,
            'fa_flight_id'            => $t->fa_flight_id,
            'fa_ident'                => $t->fa_ident,
            'scheduled_departure'     => $t->scheduled_departure?->toIso8601String(),
            'scheduled_arrival'       => $t->scheduled_arrival?->toIso8601String(),
            'estimated_departure'     => $t->estimated_departure?->toIso8601String(),
            'estimated_arrival'       => $t->estimated_arrival?->toIso8601String(),
            'actual_departure'        => $t->actual_departure?->toIso8601String(),
            'actual_arrival'          => $t->actual_arrival?->toIso8601String(),
            'departure_delay_minutes' => $t->departure_delay_minutes,
            'arrival_delay_minutes'   => $t->arrival_delay_minutes,
            'origin_gate'             => $t->origin_gate,
            'destination_gate'        => $t->destination_gate,
            'origin_terminal'         => $t->origin_terminal,
            'destination_terminal'    => $t->destination_terminal,
            'route_distance_miles'    => $t->route_distance_miles,
            'progress_percent'        => $t->progress_percent,
            'route_stats'             => $t->route_stats,

            // Trip → claim handoff
            'can_claim' => $t->eligibility_status === 'eligible' && !$t->claims()->exists(),
            'claims'    => $this->claimRefs($t),

            // Eligibility Engine verdict (null until evaluated)
            'eligibility' => $t->eligibility_evaluated_at ? [
                'status'       => $t->eligibility_status,
                'regulation'   => $t->eligibility_regulation,
                'article'      => $t->eligibility_article,
                'confidence'   => $t->eligibility_confidence,
                'reason'       => $t->eligibility_reason,
                'evaluated_at' => $t->eligibility_evaluated_at->toIso8601String(),
            ] : null,

            'last_synced_at'          => $t->last_synced_at?->toIso8601String(),
            'last_synced_human'       => $t->last_synced_at?->diffForHumans(),
            'next_poll_at'            => $t->next_poll_at?->toIso8601String(),
        ];
    }

    /** Airport metadata field (timezone, name, …) from FlightAware's forever-cached lookup. */
    private function airportField(?string $code, string $field): ?string
    {
        try {
            return $code ? ($this->flightAware->airportInfo($code)[$field] ?? null) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** All trip/itinerary files are written to the local disk; read from it too. */
    private function disk(): FilesystemAdapter
    {
        return Storage::disk('local');
    }

    /** Permanently drop an itinerary and its file (failed parse, last trip removed). */
    private function discardItinerary(Itinerary $itinerary): void
    {
        if ($itinerary->file_path) {
            $this->disk()->delete($itinerary->file_path);
        }
        $itinerary->forceDelete();
    }
}
