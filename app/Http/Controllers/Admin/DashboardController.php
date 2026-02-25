<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Cases; 
use App\Enums\CaseStatus;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index()
    {   
        $stats = [
            'total_users' => User::customers()->count(),
            'total_cases' => Cases::count(),
            'pending_cases' => Cases::where('status', CaseStatus::SENT)->count(), // Adjust based on your enum
            'resolved_today' => Cases::where('status', CaseStatus::RESOLVED)
                                ->whereDate('updated_at', today())
                                ->count(),
            'escalated_cases' => Cases::where('status', CaseStatus::ESCALATED)->count(),
        ];

        // Fetch recent 10 users
        $recentUsers = User::latest()->take(10)->get();
        
        // Fetch recent 10 cases with their owning user
        $recentCases = Cases::with('user')->latest()->take(10)->get();

        return view('admin.dashboard', compact('stats', 'recentUsers', 'recentCases'));
    }

    /**
     * Impersonate the user and redirect to their specific case.
     */
    public function impersonateAndViewCase(Cases $case)
    {
        $admin = auth()->user();
        $targetUser = $case->user;

        // Ensure the admin has permission and the target user can be impersonated
        if ($admin->canImpersonate() && $targetUser->canBeImpersonated()) {
            
            // Start impersonation
            $admin->impersonate($targetUser);
            
            // Redirect directly to the user's case view
            return redirect()->route('user.cases.show', $case->case_reference_id);
        }

        return back()->with('error', 'You cannot impersonate this user.');
    }
}