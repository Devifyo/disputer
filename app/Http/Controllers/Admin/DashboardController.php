<?php

namespace App\Http\Controllers\Admin;

use App\Services\Dashboard\AdminDashboard;

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
    public function index(AdminDashboard $dashboard)
    {
        // The dashboard follows the flight product: claims, protected trips
        // and the fees they earn. The retired case-management figures (Cases
        // counts, subscription earnings) left with that module.
        return view('admin.dashboard', $dashboard->overview());
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