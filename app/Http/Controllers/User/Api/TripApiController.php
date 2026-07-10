<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Services\ItineraryParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * "Protect Your Trip" — future trips the user registers ahead of travel,
 * either manually (funnel form) or by uploading an itinerary. Trips never
 * create claims; they exist so Unjamm can monitor upcoming flights.
 */
class TripApiController extends Controller
{
    public function index()
    {
        $trips = Trip::where('user_id', Auth::id())
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
            // The ticket shows the fare paid — required to value the claim
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
            'delay_score'       => $t->delay_score, // reserved for the future route-delay feature
            'created_at_human'  => $t->created_at->diffForHumans(),
        ];
    }

    /** All trip/itinerary files are written to the local disk; read from it too. */
    private function disk(): \Illuminate\Filesystem\FilesystemAdapter
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
