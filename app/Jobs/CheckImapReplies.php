<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserEmailConfig;
use App\Models\Cases;
use App\Models\Email;
use App\Models\CaseTimeline;
use App\Models\Attachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Webklex\PHPIMAP\ClientManager;

class CheckImapReplies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // Allow 5 mins max

    public function handle()
    {
        // Use 'echo' so you can see output in the terminal immediately
        echo "Starting IMAP Check...\n";

        UserEmailConfig::with('user')->chunk(50, function ($configs) {
            foreach ($configs as $config) {
                try {
                    $this->processUserMailbox($config);
                } catch (\Exception $e) {
                    $error = "IMAP Failed for User {$config->user_id}: " . $e->getMessage();
                    echo "❌ $error\n";
                    Log::error($error);
                }
            }
        });

        echo "✅ IMAP Check Completed.\n";
    }

    /**
     * Connect to a specific user's mailbox and fetch emails.
     */
    private function processUserMailbox(UserEmailConfig $config)
    {
        echo "➡️ Connecting to {$config->imap_host}...\n";

        $cm = new ClientManager();
        $client = $cm->make([
            'host'          => $config->imap_host,
            'port'          => $config->imap_port,
            'encryption'    => $config->imap_encryption === 'none' ? null : $config->imap_encryption,
            'validate_cert' => true,
            'username'      => $config->imap_username,
            'password'      => $config->imap_password,
            'protocol'      => 'imap',
            'options'       => ['fetch_order' => 'desc'],
        ]);

        $client->connect();
        $folder = $client->getFolder('INBOX');

        // ---------------------------------------------------------
        // OPTIMIZATION 1: Fetch Headers ONLY (No Body/Attachments)
        // ---------------------------------------------------------
        // setFetchBody(false) is the magic switch. 
        // It makes the get() request 100x faster.
        $query = $folder->query()
                        ->unseen()
                        ->since(now()->subDays(3))
                        ->setFetchBody(false); 

        $count = $query->count();
        echo "   Found {$count} new messages (Headers Only).\n";

        if ($count === 0) {
            $client->disconnect();
            return;
        }

        // Get the lightweight headers
        $messages = $query->get();

        foreach ($messages as $message) {
            $this->analyzeAndSaveMessage($message, $config->user);
        }

        $client->disconnect();
    }

    /**
     * Analyze a single message to see if it belongs to a Case.
     */
    private function analyzeAndSaveMessage($message, User $user)
    {
        $subject = $message->getSubject();
        
        // Clean headers
        $inReplyTo = trim($message->getInReplyTo(), '<> ');
        
        // Quick "Ignore" for bounced emails (Delivery Status Notification)
        // These are usually spam/failures and take up space.
        if (stripos($subject, 'Delivery Status Notification') !== false) {
             echo "   Skipping: '{$subject}' (Bounce/System msg).\n";
             return; 
        }

        echo "   Checking: '{$subject}'... ";

        $matchedCase = null;
        $parentEmail = null;

        // ---------------------------------------------------------
        // CHECK 1: Match via "In-Reply-To" Header
        // ---------------------------------------------------------
        if ($inReplyTo) {
            $parentEmail = Email::where('message_id', 'LIKE', "%{$inReplyTo}%")->first();
            if ($parentEmail) {
                $matchedCase = Cases::find($parentEmail->case_id);
                echo "[MATCHED HEADER] ";
            }
        }

        // ---------------------------------------------------------
        // CHECK 2: Match via Subject Regex
        // ---------------------------------------------------------
        if (!$matchedCase) {
            if (preg_match('/Case\s*#(\w+)/i', $subject, $matches)) {
                $referenceId = $matches[1];
                $matchedCase = Cases::where('case_reference_id', $referenceId)->first();
                echo "[MATCHED SUBJECT] ";
            }
        }

        // ---------------------------------------------------------
        // SKIP IF IRRELEVANT (Without downloading body)
        // ---------------------------------------------------------
        if (!$matchedCase) {
            echo "Skipping.\n";
            return;
        }

        // ---------------------------------------------------------
        // OPTIMIZATION 2: Download Body NOW (On Demand)
        // ---------------------------------------------------------
        // Since we skipped the body earlier, we must fetch it now.
        echo "Downloading Body... ";
        $message->parseBody(); 

        // ---------------------------------------------------------
        // SAVE TO DB
        // ---------------------------------------------------------
        try {
            DB::transaction(function () use ($message, $matchedCase, $parentEmail, $user, $subject) {
                $fromEmail = $message->getFrom()[0]->mail;
                
                // Timeline
                $timeline = CaseTimeline::create([
                    'case_id' => $matchedCase->id,
                    'type' => 'email_received',
                    'actor' => 'client', 
                    'description' => "Received reply from {$fromEmail}",
                    'occurred_at' => now(),
                    'metadata' => [
                        'subject' => $subject,
                        'sender_email' => $fromEmail,
                        'direction' => 'inbound',
                        'full_body' => $message->getHTMLBody() ?: $message->getTextBody(),
                        'message_id' => $message->getMessageId(),
                    ]
                ]);

                // Email Record
                $emailRecord = Email::create([
                    'case_id'         => $matchedCase->id,
                    'timeline_id'     => $timeline->id,
                    'parent_id'       => $parentEmail ? $parentEmail->id : null,
                    'direction'       => 'inbound',
                    'sender_email'    => $fromEmail,
                    'recipient_email' => $user->email,
                    'subject'         => $subject,
                    'body_text'       => $message->getTextBody(),
                    'body_html'       => $message->getHTMLBody(),
                    'message_id'      => $message->getMessageId(),
                ]);

                $timeline->update(['metadata' => array_merge($timeline->metadata, ['email_id' => $emailRecord->id])]);

                // Attachments
                if ($message->hasAttachments()) {
                    foreach ($message->getAttachments() as $attachment) {
                        $filename = time() . '_' . $attachment->getName();
                        $path = "cases/{$matchedCase->id}/attachments/{$filename}";
                        
                        Storage::put($path, $attachment->getContent());

                        Attachment::create([
                            'case_id' => $matchedCase->id,
                            'email_id' => $emailRecord->id,
                            'file_path' => $path,
                            'file_name' => $attachment->getName(),
                            'mime_type' => $attachment->getMimeType(),
                            'ai_analysis_status' => 'pending'
                        ]);
                    }
                }

                // Mark as SEEN
                $message->setFlag('SEEN');
            });
            
            echo "✅ Saved.\n";

        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            Log::error("Save Error: " . $e->getMessage());
        }
    }
}