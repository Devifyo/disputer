<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Itinerary;
use App\Services\ItineraryParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ItineraryApiController extends Controller
{
    public function index()
    {
        $itineraries = Itinerary::where('user_id', Auth::id())
            ->with('flights')
            ->withCount(['flights', 'passengers'])
            ->latest()
            ->get()
            ->map(fn (Itinerary $i) => $this->summary($i));

        return response()->json(['data' => $itineraries]);
    }

    public function store(Request $request, ItineraryParserService $parser)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif', 'max:12288'],
        ], [
            'file.required' => 'Please choose a PDF or photo of your itinerary to upload.',
            'file.mimes'    => 'The itinerary must be a PDF or an image (JPG, PNG, WEBP, HEIC).',
            'file.max'      => 'The file may not be larger than 12 MB.',
        ]);

        $upload = $request->file('file');

        // Duplicate guard: if this exact file was already uploaded, reuse it
        // instead of re-parsing (saves an AI call and avoids duplicate claims).
        $hash = @hash_file('sha256', $upload->getRealPath()) ?: null;
        if ($hash) {
            $existing = Itinerary::where('user_id', Auth::id())->where('file_hash', $hash)->latest()->first();
            if ($existing) {
                return response()->json([
                    'data'      => $this->detail($existing),
                    'success'   => true,
                    'duplicate' => true,
                    'message'   => 'You already uploaded this itinerary — your existing claims are ready.',
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
        ]);

        $ok = $parser->parse($itinerary);

        return response()->json([
            'data'    => $this->detail($itinerary->fresh()),
            'success' => $ok,
            'message' => $ok
                ? 'Itinerary processed — here are the flights and passengers.'
                : ($itinerary->fresh()->parse_error ?: 'We could not read that itinerary.'),
        ], $ok ? 201 : 422);
    }

    public function show(Itinerary $itinerary)
    {
        $this->authorizeOwner($itinerary);

        if ($itinerary->isParsed()) {
            Claim::ensureForItinerary($itinerary);
        }

        return response()->json(['data' => $this->detail($itinerary)]);
    }

    public function reparse(Itinerary $itinerary, ItineraryParserService $parser)
    {
        $this->authorizeOwner($itinerary);

        $ok = $parser->parse($itinerary);

        return response()->json([
            'data'    => $this->detail($itinerary->fresh()),
            'success' => $ok,
            'message' => $ok ? 'Itinerary re-processed.' : ($itinerary->fresh()->parse_error ?: 'We could not read that itinerary.'),
        ], $ok ? 200 : 422);
    }

    public function destroy(Itinerary $itinerary)
    {
        $this->authorizeOwner($itinerary);

        if ($itinerary->file_path && Storage::exists($itinerary->file_path)) {
            Storage::delete($itinerary->file_path);
        }
        $itinerary->delete();

        return response()->json(['success' => true]);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function authorizeOwner(Itinerary $itinerary): void
    {
        abort_unless($itinerary->user_id === Auth::id(), 403);
    }

    private function summary(Itinerary $i): array
    {
        return [
            'id'               => $i->id,
            'status'           => $i->status,
            'booking_reference'=> $i->booking_reference,
            'primary_airline'  => $i->primary_airline,
            'route_summary'    => $i->route_summary,
            'original_filename'=> $i->original_filename,
            'flights_count'    => $i->flights_count ?? $i->flights->count(),
            'passengers_count' => $i->passengers_count ?? $i->passengers()->count(),
            'created_at_human' => $i->created_at->diffForHumans(),
            'created_at'       => $i->created_at->format('d M Y'),
            'file_url'         => route('user.itineraries.file', $i),
        ];
    }

    private function detail(Itinerary $i): array
    {
        $i->load(['flights', 'passengers.claim']);

        return array_merge($this->summary($i), [
            'travel_dates' => $i->travel_dates,
            'parse_error'  => $i->parse_error,
            'flights'      => $i->flights->map(fn ($f) => [
                'id'                => $f->id,
                'flight_number'     => $f->flight_number,
                'airline'           => $f->airline,
                'departure_airport' => $f->departure_airport,
                'arrival_airport'   => $f->arrival_airport,
                'departure_at'      => $f->departure_at?->format('d M Y, H:i'),
                'arrival_at'        => $f->arrival_at?->format('d M Y, H:i'),
                'cabin_class'       => $f->cabin_class,
            ])->values(),
            'passengers'   => $i->passengers->map(fn ($p) => [
                'id'            => $p->id,
                'full_name'     => $p->full_name,
                'type'          => $p->type,
                'ticket_number' => $p->ticket_number,
                'claim'         => $p->claim ? [
                    'reference'    => $p->claim->reference,
                    'status'       => $p->claim->status,
                    'status_label' => $p->claim->status_label,
                ] : null,
            ])->values(),
        ]);
    }
}
