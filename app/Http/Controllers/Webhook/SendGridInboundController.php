<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Cases;
use App\Models\CaseTimeline;
use App\Models\Email;
use App\Models\Attachment;
use Exception;

class SendGridInboundController extends Controller
{
    /**
     * Handle incoming parsed emails from SendGrid.
     */
    public function handle(Request $request)
    {
        try {
            $toAddress = $request->input('to');
            $fromAddress = $this->extractEmailAddress($request->input('from'));

            // 1. Extract the Case Reference from the 'To' address (e.g., case-a8x9p2@...)
            if (!preg_match('/case-([a-zA-Z0-9]+)@/i', $toAddress, $matches)) {
                Log::warning("SendGrid Webhook: Ignored email not matching case routing.", ['to' => $toAddress]);
                return response()->json(['status' => 'ignored'], 200);
            }

            $caseReference = strtoupper($matches[1]);
            $case = Cases::where('case_reference_id', $caseReference)->first();

            if (!$case) {
                Log::warning("SendGrid Webhook: Case not found for reference.", ['reference' => $caseReference]);
                return response()->json(['status' => 'case_not_found'], 200); // Always return 200 so SendGrid doesn't retry
            }

            // 2. Process and Save the Email
            $this->processInboundEmail($request, $case, $fromAddress);

            return response()->json(['status' => 'success'], 200);

        } catch (Exception $e) {
            Log::error("SendGrid Webhook Error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // Still return 200 to acknowledge receipt, preventing SendGrid from spamming retries
            return response()->json(['status' => 'error_logged'], 200);
        }
    }

    /**
     * Execute database transactions for the incoming email.
     */
    private function processInboundEmail(Request $request, Cases $case, string $fromAddress): void
    {
        $subject = $request->input('subject', 'No Subject');
        $htmlBody = $request->input('html', '');
        $textBody = $request->input('text', '');

        // SendGrid sends headers as a raw string; we extract the Message-ID
        $messageId = $this->extractHeader($request->input('headers', ''), 'Message-ID');

        DB::transaction(function () use ($case, $request, $fromAddress, $subject, $htmlBody, $textBody, $messageId) {
            // 1. DYNAMIC WORKFLOW AUTOMATION
            $this->applyDynamicWorkflowUpdate($case);

            $timeline = CaseTimeline::create([
                'case_id' => $case->id,
                'type' => 'email_received',
                'actor' => 'client',
                'description' => "Received reply from {$fromAddress}",
                'occurred_at' => now(),
                'metadata' => [
                    'subject' => $subject,
                    'sender_email' => $fromAddress,
                    'direction' => 'inbound',
                    'message_id' => $messageId,
                ]
            ]);

            $emailRecord = Email::create([
                'case_id'         => $case->id,
                'timeline_id'     => $timeline->id,
                'direction'       => 'inbound',
                'sender_email'    => $fromAddress,
                'recipient_email' => $case->case_email,
                'subject'         => $subject,
                'body_text'       => $textBody,
                'body_html'       => $htmlBody,
                'message_id'      => $messageId,
            ]);

            $user = $case->user;

            $timeline->update(['metadata' => array_merge($timeline->metadata, ['email_id' => $emailRecord->id])]);

            $this->storeAttachments($request, $case->id, $emailRecord->id);

            // Notify the case owner that a new inbound email has arrived
            if ($user) {
                send_dynamic_email($user->email, 'inbound-email-received', [
                    '[USER_NAME]'      => $user->name,
                    '[CASE_REFERENCE]' => $case->case_reference_id,
                    '[SENDER_EMAIL]'   => $fromAddress,
                    '[EMAIL_SUBJECT]'  => $subject,
                    '[CASE_URL]'       => url('/cases/' . $case->case_reference_id),
                ]);
            }
        });
    }

    /**
     * SendGrid passes attachments sequentially (attachment1, attachment2, etc.)
     */
    private function storeAttachments(Request $request, int $caseId, int $emailId): void
    {
        $attachmentCount = (int) $request->input('attachments', 0);

        for ($i = 1; $i <= $attachmentCount; $i++) {
            if ($request->hasFile("attachment{$i}")) {
                $file = $request->file("attachment{$i}");
                
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

    /**
     * Helper to clean "Name <email@domain.com>" down to just "email@domain.com"
     */
    private function extractEmailAddress(string $rawFrom): string
    {
        if (preg_match('/<(.+?)>/', $rawFrom, $matches)) {
            return $matches[1];
        }
        return trim($rawFrom);
    }

    /**
     * Helper to extract a specific header from SendGrid's raw header string
     */
    private function extractHeader(string $headers, string $key): ?string
    {
        if (preg_match('/^' . preg_quote($key, '/') . ':\s*(.+)$/im', $headers, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Dynamically updates the case stage and workflow step based on the category configuration.
     */
    private function applyDynamicWorkflowUpdate(Cases $case): void
    {
        $workflowConfig = $case->institution?->category?->workflow_config;
        $currentStepKey = $case->current_workflow_step;
        $currentStepConfig = $workflowConfig['steps'][$currentStepKey] ?? null;

        // Base update array (status and resetting the clock)
        $updateData = [
            'next_action_at' => now()->addDays(7)
        ];

        if ($currentStepConfig) {
            if (!empty($currentStepConfig['on_inbound_email_step'])) {
                // ADMIN SET A TARGET: Advance the workflow step
                $newStepKey = $currentStepConfig['on_inbound_email_step'];
                
                $updateData['current_workflow_step'] = $newStepKey;
                $updateData['stage'] = $workflowConfig['steps'][$newStepKey]['label'] ?? 'Review Required';
            } else {
                // ADMIN LEFT IT BLANK: Do NOT change the step. Just prepend the alert emoji to the stage.
                $updateData['stage'] = "🚨 Reply Received: " . ($currentStepConfig['label'] ?? 'Review Required');
            }
        } else {
            // Fallback if config is totally missing
            $updateData['stage'] = '🚨 Reply Received - Review Required';
        }

        // Apply the update to the Case
        $case->update($updateData);
    }
}