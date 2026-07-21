<?php

namespace App\Http\Controllers;

use App\Services\Marketing\LiveDisruptionFeedService;
use App\Services\Marketing\PublicFlightLookupService;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    /**
     * Display the Unjamm Landing Page.
     */
    public function index()
    {
        return view('marketing.landing');
    }

    /** Real disrupted flights for the landing page's live board (cached server-side). */
    public function liveDisruptions(LiveDisruptionFeedService $feed)
    {
        return response()->json(['data' => $feed->rows()])
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * "Check your flight" for visitors with no account - a provisional read
     * on whether a flight looks compensable, before they sign up.
     */
    public function flightLookup(Request $request, PublicFlightLookupService $lookup)
    {
        $data = $request->validate([
            'flight' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]{2,3}\s?\d{1,4}$/'],
            'date'   => ['required', 'date', 'after_or_equal:-2 years', 'before_or_equal:+1 year'],
        ], [
            'flight.regex' => 'Use the flight number as it appears on your ticket, e.g. AC123.',
            'date.after_or_equal' => 'Claims older than two years are rarely recoverable - contact us if yours is.',
        ]);

        return response()->json(['data' => $lookup->lookup($data['flight'], $data['date'])]);
    }
}