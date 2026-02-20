<?php

namespace App\Services;

use App\Models\Cases;
use App\Models\Email; // Assuming you have an Email model linked to Cases
use Illuminate\Support\Facades\DB;

class UserDashboardService
{
    /**
     * Get top-level statistics for the dashboard cards.
     */
    public function getStats(int $userId): array
    {
        return [
            'active' => Cases::where('user_id', $userId)
                ->whereIn('status', ['sent', 'waiting_institution']) // Adjust status keys as needed
                ->count(),
            
            'replies' => Email::whereHas('case', fn($q) => $q->where('user_id', $userId))
                ->where('direction', 'inbound')
                ->where('is_read', false) // Optional: only count unread
                ->count(),

            'drafts' => Cases::where('user_id', $userId)
                ->where('status', 'draft')
                ->count(),

            'resolved' => Cases::where('user_id', $userId)
                ->where('status', 'resolved')
                ->count(),
        ];
    }

    /**
     * Get the most recent unread inbound email for the banner.
     */
    public function getLatestUnreadReply(int $userId)
    {
        return Email::whereHas('case', fn($q) => $q->where('user_id', $userId))
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->latest()
            ->first();
    }

    /**
     * Get active cases for the main table.
     */
    public function getActiveCases(int $userId)
    {
        return Cases::where('user_id', $userId)
            ->where('status', '!=', 'resolved')
            ->latest('updated_at')
            ->take(5)
            ->get();
    }

    /**
     * Get recent emails (Inbound & Outbound) for the Activity Log sidebar.
     */
    public function getRecentActivity(int $userId)
    {
        return Email::whereHas('case', fn($q) => $q->where('user_id', $userId))
            ->with('case:id,institution_name,case_reference_id,status') // Optimize query
            ->latest('created_at')
            ->take(6)
            ->get();
    }

    public function isEmailConfigured(int $userId): bool
    {
        $config = \App\Models\UserEmailConfig::where('user_id', $userId)->first();

        if (!$config) {
            return false;
        }

        $requiredFields = [
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 
            'imap_host', 'imap_port', 'imap_username', 'imap_password', 
            'from_name', 'from_email'
        ];

        foreach ($requiredFields as $field) {
            // Check if field is null or an empty string
            if (empty($config->$field)) {
                return false;
            }
        }

        return true;
    }
}