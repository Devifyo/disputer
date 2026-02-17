<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Cases; // Assuming your model is CaseModel or similar
use App\Enums\CaseStatus;
class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index()
    {   
        $stats = [
            'total_users' => User::count(),
            'total_cases' => Cases::count(),
            'pending_cases' => Cases::where('status', CaseStatus::SENT)->count(),
            'resolved_today' => Cases::where('status', CaseStatus::RESOLVED)
                                ->whereDate('updated_at', today())
                                ->count(),
            'escalated_cases' => Cases::where('status', CaseStatus::ESCALATED)->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}