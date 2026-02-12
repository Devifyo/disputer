<?php

namespace App\Http\Controllers\User;

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

        $stats = $this->dashboardService->getStats($userId);
        $latestUnread = $this->dashboardService->getLatestUnreadReply($userId);
        $activeCases = $this->dashboardService->getActiveCases($userId);
        $recentEmails = $this->dashboardService->getRecentActivity($userId);
        $isEmailConfigured = $this->dashboardService->isEmailConfigured($userId);
        return view('user.dashboard', compact('stats', 'latestUnread', 'activeCases', 'recentEmails', 'isEmailConfigured'));
    }
}