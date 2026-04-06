<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Cases;
use App\Models\CaseTimeline;
use App\Models\Email;

class SendGridEventController extends Controller
{
    public function handle(Request $request)
    {
        $events = $request->json()->all();
        Log::info('SENDGRID WEBHOOK RAW DATA:', ['events' => $events]);
        if (empty($events)) {
            return response()->json(['status' => 'empty'], 200);
        }

        foreach ($events as $event) {
            // We only care about delivery failures
            if (isset($event['event']) && in_array($event['event'], ['bounce', 'dropped'])) {
                $this->handleFailedDelivery($event);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

   private function handleFailedDelivery(array $event)
    {
        $emailId = $event['email_id'] ?? null;
        $bounceReason = $event['reason'] ?? 'Unknown delivery failure';

        if ($emailId) {
            $emailRecord = Email::find($emailId);
            
            if ($emailRecord) {
                // Update ONLY the specific email's delivery status
                $emailRecord->update([
                    'delivery_status' => 'bounced'
                ]);

                Log::warning("SendGrid Bounce logged for Email ID: {$emailId}. Reason: {$bounceReason}");
            }
        }
    }
}