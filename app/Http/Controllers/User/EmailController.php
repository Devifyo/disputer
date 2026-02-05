<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function index()
    {
        // SIMULATED DATA
        $threads = collect([
            (object)[
                'id' => 1,
                'case_id' => '1023',
                'case_title' => 'Chase Bank - Double Charge',
                'subject' => 'Re: Formal Billing Dispute',
                'recipient' => 'Chase Fraud Dept',
                'last_message' => 'We have received your dispute and have opened an investigation...',
                'status' => 'reply_received', // sent, reply_received, failed
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

        return view('user.emails.index', compact('threads'));
    }

    public function show($id)
    {
        // SIMULATED THREAD
        $case = (object) ['id' => '1023', 'title' => 'Chase Bank - Double Charge', 'institution' => 'Chase Bank'];
        
        $messages = collect([
            (object) [
                'type' => 'incoming', // or 'outgoing'
                'sender' => 'Chase Fraud Dept',
                'email' => 'fraud-support@chase.com',
                'body' => "Dear Customer,\n\nWe have received your dispute regarding the transaction of $450.00. We have opened an investigation (Case Ref: #CB-998877).\n\nPlease allow 7-10 business days for a resolution.\n\nSincerely,\nChase Fraud Team",
                'date' => 'Today, 10:42 AM',
                'attachments' => []
            ],
            (object) [
                'type' => 'outgoing',
                'sender' => 'You',
                'email' => 'me@example.com',
                'body' => "To Whom It May Concern,\n\nI am writing to formally dispute a transaction on my statement dated Jan 24th...",
                'date' => 'Yesterday, 4:00 PM',
                'attachments' => ['evidence_scan.pdf']
            ]
        ]);

        return view('user.emails.show', compact('messages', 'case'));
    }

    public function create()
    {
        return view('user.emails.create');
    }
}