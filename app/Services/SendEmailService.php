<?php

namespace App\Services;

use App\Models\Cases;
use App\Models\User;
use App\Models\UserEmailConfig;
use App\Models\CaseTimeline;
use App\Models\Email;
use App\Models\Attachment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class SendEmailService
{
    /**
     * Send email via Custom SMTP, upload files, and record database entries.
     */
    public function sendAndLog(User $user, Cases $case, string $recipient, string $subject, string $body, array $attachments = [])
    {
        // 1. Get User's SMTP Config
        $emailConfig = UserEmailConfig::where('user_id', $user->id)->first();

        if (!$emailConfig) {
            throw new \Exception('SMTP settings not found. Please configure your email settings in your profile.');
        }

        // 2. Configure Mailer Dynamically
        $this->configureMailer($emailConfig);

        // 3. Send the Email First (Ensure delivery before DB work)
        try {
            Mail::raw($body, function ($message) use ($recipient, $subject, $emailConfig, $attachments) {
                $message->to($recipient)
                        ->subject($subject)
                        ->from($emailConfig->from_email, $emailConfig->from_name ?? 'Dispute Manager');
                
                // Attach files from Temporary Upload path
                /** @var UploadedFile $file */
                foreach ($attachments as $file) {
                    $message->attach($file->getRealPath(), [
                        'as' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                    ]);
                }
            });
        } catch (\Exception $e) {
            Log::error("Custom SMTP Error: " . $e->getMessage());
            throw new \Exception("Failed to send email: " . $e->getMessage());
        }

        // 4. database Transaction: Record Timeline, Email, and Attachments
        DB::transaction(function () use ($case, $user, $recipient, $subject, $body, $attachments, $emailConfig) {
            
            // A. Create Timeline Entry
            $timeline = CaseTimeline::create([
                'case_id' => $case->id,
                'type' => 'email_sent',
                'actor' => 'user', // or $user->name
                'description' => count($attachments) > 0 
                    ? "Sent email to {$recipient} with " . count($attachments) . " attachment(s)." 
                    : "Sent email to {$recipient}",
                'occurred_at' => now(),
                'metadata' => [
                    'subject' => $subject, // Keep basic meta for quick access
                    'recipient' => $recipient,
                ]
            ]);

            // B. Create Email Record (Linked to Timeline)
            $emailRecord = Email::create([
                'case_id' => $case->id,
                'timeline_id' => $timeline->id,
                'direction' => 'outbound',
                'sender_email' => $emailConfig->from_email,
                'recipient_email' => $recipient,
                'subject' => $subject,
                'body_text' => $body,
                // 'body_html' => nl2br($body), // Optional if you want HTML version
            ]);

            // C. Upload Files and Create Attachment Records
            /** @var UploadedFile $file */
            foreach ($attachments as $file) {
                // 1. Store file securely (e.g., storage/app/cases/{id}/attachments)
                $path = $file->storeAs(
                    "cases/{$case->id}/attachments", 
                    time() . '_' . $file->getClientOriginalName()
                );

                // 2. Create DB Record linked to Email AND Case
                Attachment::create([
                    'case_id' => $case->id,
                    'email_id' => $emailRecord->id, // Linked to specific email
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'ai_analysis_status' => 'pending' // Ready for your AI processing job
                ]);
            }
        });
    }

    private function configureMailer(UserEmailConfig $config)
    {
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $config->smtp_host);
        Config::set('mail.mailers.smtp.port', $config->smtp_port);
        Config::set('mail.mailers.smtp.encryption', $config->smtp_encryption);
        Config::set('mail.mailers.smtp.username', $config->smtp_username);
        Config::set('mail.mailers.smtp.password', $config->smtp_password);
        Config::set('mail.from.address', $config->from_email);
        Config::set('mail.from.name', $config->from_name);
        
        // Reset the mailer instance to apply new config
        Mail::purge('smtp');
    }
}