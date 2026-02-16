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
use Illuminate\Http\UploadedFile;

class SendEmailService
{
    /**
     * Send email via Custom SMTP, upload files, and record database entries.
     * * @param array $overrides Optional array to override timeline type, description, etc.
     */
    public function sendAndLog(
        User $user, 
        Cases $case, 
        string $recipient, 
        string $subject, 
        string $body, 
        array $attachments = [], 
        Email $parentEmail = null,
        array $overrides = [] // <--- NEW ARGUMENT (Defaults to empty)
    ) {
        // 1. Get User's SMTP Config
        $emailConfig = UserEmailConfig::where('user_id', $user->id)->first();

        if (!$emailConfig) {
            throw new \Exception('SMTP settings not found.');
        }

        // 2. Generate ID (WITHOUT BRACKETS for Symfony Header)
        $domain = substr(strrchr($emailConfig->from_email, "@"), 1);
        $cleanMessageId = time() . "." . bin2hex(random_bytes(8)) . "@" . $domain;

        // 3. Register Mailer
        $mailerName = 'custom_smtp_' . $user->id;
        $this->registerCustomMailer($mailerName, $emailConfig);

        // 4. Send Email
        try {
            Mail::mailer($mailerName)->send([], [], function ($message) use ($recipient, $subject, $body, $emailConfig, $attachments, $cleanMessageId, $parentEmail) {
                $message->to($recipient)
                        ->subject($subject)
                        ->from($emailConfig->from_email, $emailConfig->from_name)
                        ->html($body);

                // Add ID Header (No brackets allowed here)
                $message->getHeaders()->addIdHeader('Message-ID', $cleanMessageId);

                // Handle Threading
                if ($parentEmail && $parentEmail->message_id) {
                    $cleanParentId = trim($parentEmail->message_id, '<>');
                    $message->getHeaders()->addIdHeader('In-Reply-To', $cleanParentId);
                    $message->getHeaders()->addIdHeader('References', $cleanParentId);
                }

                // Attach files
                foreach ($attachments as $file) {
                    if ($file instanceof UploadedFile) {
                        $message->attach($file->getRealPath(), [
                            'as' => $file->getClientOriginalName(),
                            'mime' => $file->getClientMimeType(),
                        ]);
                    }
                }
            });

        } catch (\Exception $e) {
            Log::error("SMTP Error: " . $e->getMessage());
            throw new \Exception("Failed to send email: " . $e->getMessage());
        }

        // 5. Database Transaction
        DB::transaction(function () use ($case, $user, $recipient, $subject, $body, $attachments, $emailConfig, $cleanMessageId, $parentEmail, $overrides) {
            
            $dbMessageId = "<{$cleanMessageId}>";

            // --- MERGE OVERRIDES FOR TIMELINE ---
            $type = $overrides['type'] ?? 'email_sent';
            $description = $overrides['description'] ?? "Sent email to {$recipient}";
            $extraMetadata = $overrides['metadata'] ?? [];

            // Timeline Record
            $timeline = CaseTimeline::create([
                'case_id' => $case->id,
                'type' => $type, // Use overridden type (e.g., 'escalation_sent')
                'actor' => 'user', 
                'description' => $description, // Use overridden description
                'occurred_at' => now(),
                'metadata' => array_merge([
                    'subject' => $subject,
                    'recipient' => $recipient,
                    'direction' => 'outbound',
                    'message_id' => $dbMessageId, 
                    'email_id' => null 
                ], $extraMetadata)
            ]);

            // Email Record
            $emailRecord = Email::create([
                'case_id'         => $case->id,
                'timeline_id'     => $timeline->id,
                'parent_id'       => $parentEmail ? $parentEmail->id : null,
                'direction'       => 'outbound',
                'sender_email'    => $emailConfig->from_email,
                'recipient_email' => $recipient,
                'subject'         => $subject,
                'body_text'       => strip_tags($body),
                'body_html'       => $body,
                'message_id'      => $dbMessageId,
            ]);

            // Update Timeline with Email ID
            $timeline->update(['metadata' => array_merge($timeline->metadata, ['email_id' => $emailRecord->id])]);

            // Attachments
            foreach ($attachments as $file) {
                if ($file instanceof UploadedFile) {
                    $path = $file->storeAs("cases/{$case->id}/attachments", time() . '_' . $file->getClientOriginalName(), 'public');
                    Attachment::create([
                        'case_id' => $case->id,
                        'email_id' => $emailRecord->id,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'ai_analysis_status' => 'pending'
                    ]);
                }
            }
        });
    }

    private function registerCustomMailer(string $mailerName, UserEmailConfig $config)
    {
        $encryption = ($config->smtp_encryption === 'none') ? null : $config->smtp_encryption;
        Config::set("mail.mailers.{$mailerName}", [
            'transport' => 'smtp',
            'host'       => $config->smtp_host,
            'port'       => $config->smtp_port,
            'encryption' => $encryption,
            'username'   => $config->smtp_username,
            'password'   => $config->smtp_password,
            'timeout'    => null,
        ]);
    }
}