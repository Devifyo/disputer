<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EmailService
{
    /**
     * Get threads with optional search and filter.
     */
    public function getThreads(string $search = '', string $filter = 'all'): Collection
    {
        // 1. Define Dummy Data
        $threads = collect([
            (object)[
                'id' => 1,
                'case_id' => '1023',
                'case_title' => 'Chase Bank - Double Charge',
                'subject' => 'Re: Formal Billing Dispute',
                'recipient' => 'Chase Fraud Dept',
                'last_message' => 'We have received your dispute and have opened an investigation...',
                'status' => 'reply_received',
                'date' => '10 mins ago',
                'has_attachment' => true,
                'unread' => true,
            ],
            (object)[
                'id' => 2,
                'case_id' => '1045',
                'case_title' => 'American Airlines Refund',
                'subject' => 'Flight Cancellation Compensation',
                'recipient' => 'AA Customer Relations',
                'last_message' => 'Please find attached the receipt for my original booking...',
                'status' => 'sent',
                'date' => 'Yesterday',
                'has_attachment' => true,
                'unread' => false,
            ],
            (object)[
                'id' => 3,
                'case_id' => '1011',
                'case_title' => 'Equifax Identity Check',
                'subject' => 'Identity Theft Affidavit',
                'recipient' => 'Equifax Security',
                'last_message' => 'Delivery incomplete. The recipient inbox is full.',
                'status' => 'failed',
                'date' => '2 days ago',
                'has_attachment' => false,
                'unread' => false,
            ]
        ]);

        // 2. Apply Filters (Simulating DB Query)
        return $threads->filter(function ($thread) use ($search, $filter) {
            
            // Filter by Status Tab
            if ($filter === 'unread' && !$thread->unread) return false;
            if ($filter === 'sent' && $thread->status !== 'sent') return false;

            // Filter by Search Query
            if (!empty($search)) {
                $search = strtolower($search);
                return Str::contains(strtolower($thread->recipient), $search) ||
                       Str::contains(strtolower($thread->subject), $search) ||
                       Str::contains(strtolower($thread->case_id), $search);
            }

            return true;
        });
    }
}