<?php

namespace App\Http\Controllers;

use App\Services\Marketing\LiveDisruptionFeedService;
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
}