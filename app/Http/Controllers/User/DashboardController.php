<?php

namespace App\Http\Controllers\User;

use App\Services\Dashboard\FlightDashboard;

use Illuminate\Http\Request;
use App\Services\UserDashboardService;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(UserDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $userId = auth()->id();

        // The dashboard is the flight-dispute product now: claims, monitored
        // trips and payouts. The case-management figures below belong to the
        // retired module and are kept only for reference.
        // $stats = $this->dashboardService->getStats($userId);
        // $latestUnread = $this->dashboardService->getLatestUnreadReply($userId);
        // $activeCases = $this->dashboardService->getActiveCases($userId);
        // $recentEmails = $this->dashboardService->getRecentActivity($userId);
        // $isEmailConfigured = $this->dashboardService->isEmailConfigured($userId);

        return view('user.dashboard', app(FlightDashboard::class)->for(auth()->user()));
    }
}