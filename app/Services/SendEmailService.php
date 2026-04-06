<?php

namespace App\Services;

use App\Models\Cases;
use App\Models\User;
use App\Models\CaseTimeline;
use App\Models\Email;
use App\Models\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;
use Exception;

class SendEmailService
{
    /**
     * Dispatch an outbound email with dynamic SendGrid routing and log the transaction.
     */
    public function sendAndLog(
        User $user, 
        Cases $case, 
        string $recipient, 
        string $subject, 
        string $body, 
        array $attachments = [], 
        ?Email $parentEmail = null,
        array $overrides = [] 
    ): void {
        if (empty($case->case_email) || empty($case->case_reference_id)) {
            throw new InvalidArgumentException('Case lacks a required routing email or reference ID.');
        }

        $domain = explode('@', config('mail.from.address'))[1] ?? 'unjamm.com';
        $caseRef = strtolower($case->case_reference_id);
        
        $fromEmail = "case-{$caseRef}@{$domain}";
        $replyToEmail = $case->case_email;
        $senderName = "{$user->name} (Unjamm Case #" . strtoupper($caseRef) . ")";
        $messageId = time() . "." . bin2hex(random_bytes(8)) . "@" . $domain;

        // 1. MUST log transaction FIRST to get the Email ID
        $emailRecord = $this->logTransaction(
            $case, $recipient, $subject, $body, $replyToEmail, 
            $messageId, $attachments, $parentEmail, $overrides
        );

        try {
            // 2. Transmit the email, passing the $emailRecord along
            $this->transmitEmail(
                $case, $emailRecord, $recipient, $subject, $body, $fromEmail, 
                $replyToEmail, $senderName, $messageId, $attachments, $parentEmail
            );
        } catch (Exception $e) {
            // 3. If SendGrid fails immediately, update the DB so it's not stuck as "sent"
            $emailRecord->update(['delivery_status' => 'failed']);
            throw $e; 
        }
    }

    /**
     * Handle the SMTP transmission via Laravel Mailer.
     */
/**
     * Handle the SMTP transmission via Laravel Mailer.
     */
/**
     * Handle the SMTP transmission via Laravel Mailer.
     */
    private function transmitEmail(
        Cases $case, Email $emailRecord,
        string $recipient, string $subject, string $body, 
        string $from, string $replyTo, string $senderName, 
        string $messageId, array $attachments, ?Email $parentEmail
    ): void {
        
        // --- THE FIX: Convert invisible text newlines (\n) into HTML breaks (<br>) ---
        $formattedBody = nl2br($body);

        // 1. Wrap the text in a professional, responsive HTML email skeleton
        $htmlBody = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 800px; padding: 20px; margin: 0;">
            ' . $formattedBody . '
        </body>
        </html>';

        try {
            Mail::send([], [], function ($message) use ($case, $emailRecord, $recipient, $subject, $htmlBody, $body, $from, $replyTo, $senderName, $messageId, $attachments, $parentEmail) {
                
                $message->to($recipient)
                        ->subject($subject)
                        ->from($from, $senderName)
                        ->replyTo($replyTo, $senderName)
                        ->html($htmlBody)                     // The styled HTML version (with <br> tags)
                        ->text(strip_tags($body));            // The plain-text fallback (keeps original \n for spam filters)

                $message->getHeaders()->addIdHeader('Message-ID', $messageId);
                $message->getHeaders()->addTextHeader(
                    'X-SMTPAPI', 
                    json_encode([
                        'unique_args' => [
                            'case_id' => (string) $case->id,
                            'email_id' => (string) $emailRecord->id
                        ]
                    ])
                );
                if ($parentEmail?->message_id) {
                    $cleanParentId = trim($parentEmail->message_id, '<>');
                    $message->getHeaders()->addIdHeader('In-Reply-To', $cleanParentId);
                    $message->getHeaders()->addIdHeader('References', $cleanParentId);
                }

                foreach ($attachments as $file) {
                    if ($file instanceof UploadedFile) {
                        $message->attach($file->getRealPath(), [
                            'as' => $file->getClientOriginalName(),
                            'mime' => $file->getClientMimeType(),
                        ]);
                    }
                }
            });
        } catch (Exception $e) {
            Log::error("SendGrid SMTP Error: " . $e->getMessage());
            throw new RuntimeException("Failed to dispatch email: " . $e->getMessage());
        }
    }

    /**
     * Persist the outbound communication data to the database.
     */
    private function logTransaction(
        Cases $case, string $recipient, string $subject, string $body, 
        string $replyToEmail, string $messageId, array $attachments, 
        ?Email $parentEmail, array $overrides
    ): Email {
        return DB::transaction(function () use ($case, $recipient, $subject, $body, $replyToEmail, $messageId, $attachments, $parentEmail, $overrides) {
            $dbMessageId = "<{$messageId}>";
            $currentStep = $case->current_workflow_step;
            $timeline = CaseTimeline::create([
                'case_id' => $case->id,
                'type' => $overrides['type'] ?? 'email_sent',
                'actor' => 'user',
                'description' => $overrides['description'] ?? "Sent email to {$recipient}",
                'occurred_at' => now(),
                'metadata' => array_merge([
                    'subject' => $subject,
                    'recipient' => $recipient,
                    'direction' => 'outbound',
                    'message_id' => $dbMessageId,
                    'email_id' => null,
                    'step_key' => $currentStep,
                ], $overrides['metadata'] ?? []),
            ]);

            $emailRecord = Email::create([
                'case_id' => $case->id,
                'step_key' => $currentStep,
                'timeline_id' => $timeline->id,
                'parent_id' => $parentEmail?->id,
                'direction' => 'outbound',
                'sender_email' => $replyToEmail,
                'recipient_email' => $recipient,
                'subject' => $subject,
                'body_text' => strip_tags($body),
                'body_html' => $body,
                'message_id' => $dbMessageId,
            ]);

            $timeline->update([
                'metadata' => array_merge($timeline->metadata, ['email_id' => $emailRecord->id])
            ]);

            if (!empty($attachments)) {
                $this->storeAttachments($attachments, $case->id, $emailRecord->id);
            }
            return $emailRecord;
        });
    }

    /**
     * Process and link file uploads to the email record.
     */
    private function storeAttachments(array $attachments, int $caseId, int $emailId): void
    {
        foreach ($attachments as $file) {
            if ($file instanceof UploadedFile) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs("cases/{$caseId}/attachments", $fileName, 'public');

                Attachment::create([
                    'case_id' => $caseId,
                    'email_id' => $emailId,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'ai_analysis_status' => 'pending'
                ]);
            }
        }
    }
}