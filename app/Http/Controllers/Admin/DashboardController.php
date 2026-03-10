<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Cases; 
use App\Models\UserSubscription; // <-- Add this import
use App\Enums\CaseStatus;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index()
    {   

        $totalEarnings = UserSubscription::join('plans', 'user_subscriptions.plan_id', '=', 'plans.id')
            ->whereNotNull('user_subscriptions.transaction_id')
            ->sum('plans.price');

        $stats = [
            'total_users' => User::customers()->count(),
            'total_cases' => Cases::count(),
            'total_earnings' => $totalEarnings, // <-- Replaced pending_cases with this
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

        if ($admin->canImpersonate() && $targetUser->canBeImpersonated()) {
            $admin->impersonate($targetUser);
            return redirect()->route('user.cases.show', $case->case_reference_id);
        }

        return back()->with('error', 'You cannot impersonate this user.');
    }
}