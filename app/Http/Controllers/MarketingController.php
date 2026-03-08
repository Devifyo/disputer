<?php

namespace App\Http\Controllers;

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
}