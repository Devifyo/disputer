<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Institution; // Import your Model

class CaseController extends Controller
{
    /**
     * Step 1: Show the Institution Selection Wizard
     */
    public function createStep1()
    {
        // Pass popular institutions for the "Quick Pick" section
        $popular = Institution::where('is_verified', true)->limit(4)->get();

        // 2. All Categories (for when user creates a custom institution)
        // We pluck them to make a simple dropdown list
        $categories = \App\Models\InstitutionCategory::orderBy('name')->get();

        return view('user.cases.create_wizard', compact('popular','categories'));
    }

    /**
     * API: Handle the AJAX Search
     */
    public function searchInstitutions(Request $request)
    {
        $query = $request->get('q');

        if (!$query) {
            return response()->json([]);
        }

        // Search logic using the Model you provided
        $institutions = Institution::with('category') // Eager load category
            ->where('name', 'LIKE', "%{$query}%")
            ->where('is_verified', true) // Only show verified ones in search
            ->limit(5)
            ->get();

        return response()->json($institutions);
    }
}
