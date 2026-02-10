<?php

use App\Models\UserEmailConfig;
use Illuminate\Support\Facades\Auth;

if (!function_exists('isEmailConfigured')) {
    /**
     * Check if the authenticated user has fully configured their email (SMTP & IMAP).
     *
     * @return bool
     */
    function isEmailConfigured()
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Check for BOTH sending (SMTP) and receiving (IMAP) credentials
        return UserEmailConfig::where('user_id', $user->id)
            ->whereNotNull('smtp_host')
            ->whereNotNull('smtp_username')
            ->whereNotNull('smtp_password')
            ->whereNotNull('imap_host')
            ->whereNotNull('imap_username')
            ->whereNotNull('imap_password')
            ->exists();
    }
}