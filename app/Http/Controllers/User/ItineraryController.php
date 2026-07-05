<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ItineraryController extends Controller
{
    /**
     * Serve the Vue SPA shell (handles the list and details screens client-side).
     */
    public function index()
    {
        return view('user.itineraries.index');
    }

    /**
     * Stream the original uploaded file (owner only).
     */
    public function file(Itinerary $itinerary)
    {
        abort_unless($itinerary->user_id === Auth::id(), 403);
        abort_unless(Storage::exists($itinerary->file_path), 404);

        return Storage::response(
            $itinerary->file_path,
            $itinerary->original_filename,
            ['Content-Type' => $itinerary->mime_type ?: 'application/pdf']
        );
    }
}
