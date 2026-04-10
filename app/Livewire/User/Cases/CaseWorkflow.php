<?php

namespace App\Livewire\User\Cases;

use Livewire\Component;
use App\Models\{Cases, Email};
use App\Models\CaseTimeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\WithFileUploads;
use App\Services\EscalationService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
class CaseWorkflow extends Component
{
    use WithFileUploads;

    public $case;
    public $workflowConfig;
    public $currentStepKey;
    public $currentStepConfig;

    // --- AI Modal State ---
    public $showAiModal = false; 
    public $isAnalyzing = false;
    public $aiResponse = null;

    // --- Email / Escalation State ---
    public $recipient = '';
    public $subject = '';
    public $body = '';
    public $attachments = []; 
    
    // 🚩 CRITICAL FIX: Track escalation mode explicitly
    public $isEscalationMode = false;
    public $pendingStepJump = null;
    /**
     * Initialize the component with Case data and Workflow configurations
     */
    public function mount(Cases $case)
    {
        $this->case = $case;
        // Access config via Institution -> Category
        $this->workflowConfig = $this->case->institution->category->workflow_config ?? [];

        $dbValue = $this->case->current_workflow_step;
        $initialStep = $this->workflowConfig['initial_step'] ?? 'draft';

        // Set current step or fallback to initial
        if (empty($dbValue) || !isset($this->workflowConfig['steps'][$dbValue])) {
            $this->currentStepKey = $initialStep;
        } else {
            $this->currentStepKey = $dbValue;
        }

        $this->loadStepConfig();
    }

    #[On('email-read-state-changed')]
    public function refreshWorkflow()
    {
        // Livewire will automatically re-render the component, query the database,
        // see the email is now read, and remove the red banner instantly!
    }

    #[Computed]
    public function unreadEmails()
    {
        return Email::where('case_id', $this->case->id)
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->latest()
            ->get();
    }

    public function loadStepConfig()
    {   
        $this->currentStepConfig = $this->workflowConfig['steps'][$this->currentStepKey] ?? null;
    }

    // =========================================================================
    //  EMAIL & ESCALATION LOGIC
    // =========================================================================

    /**
     * Triggered when user clicks "Escalate Now" or "Escalate Further"
     */
    public function initiateEscalation(EscalationService $service)
    {
        // 1. Set the flag so sendEmail knows this is an escalation
        $this->isEscalationMode = true;

        // 2. Get correct email from hierarchy
        $details = $service->getEscalationDetails($this->case);

        // 3. Pre-fill the compose modal
        $this->recipient = $details['email'];
        $this->subject = 'Formal Escalation: Case #' . $this->case->case_reference_id;
        
        $level = $this->case->escalation_level + 1;
        $contactName = $details['name'] ?? 'Authority';
        
        $this->body = "";

        // 4. Open the modal
        $this->dispatch('open-compose-modal', [
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'body' => $this->body,
            'isEscalation' => true
        ]); 
    }

    /**
     * Triggered when user clicks normal "New Email" or "Reply"
     */
    public function openComposeModal()
    {
        $this->isEscalationMode = false; // Reset flag for normal emails
        $this->reset(['recipient', 'subject', 'body']);
        $this->dispatch('open-compose-modal');
    }

    /**
     * Handles the final submission from the Compose Email Modal
     */
    // public function sendEmail()
    // {   
    //     $this->validate([
    //         'recipient' => 'required|email',
    //         'subject'   => 'required|string|max:255',
    //         'body'      => 'required|string',
    //     ]);

    //     // 1. Determine if this is an escalation based on our flag OR subject fallback
    //     $isEscalation = $this->isEscalationMode || str_contains(strtolower($this->subject), 'escalat');

    //     // 2. Log to Timeline
    //     CaseTimeline::create([
    //         'case_id'     => $this->case->id,
    //         'type'        => $isEscalation ? 'escalation_sent' : 'email_sent',
    //         'actor'       => 'user',
    //         'description' => $isEscalation 
    //             ? "Escalation (Level " . ($this->case->escalation_level + 1) . ") sent to {$this->recipient}" 
    //             : "Email sent to {$this->recipient}",
    //         'occurred_at' => now(),
    //         'metadata'    => [
    //             'recipient'    => $this->recipient,
    //             'sender_email' => Auth::user()->email,
    //             'subject'      => $this->subject,
    //             'body'         => $this->body,
    //             'direction'    => 'outbound',
    //             'level'        => $isEscalation ? ($this->case->escalation_level + 1) : null
    //         ]
    //     ]);

    //     // 3. Update State if Escalation
    //     if ($isEscalation) {
    //         $this->case->update([
    //             'escalation_level' => $this->case->escalation_level + 1,
    //             'last_escalated_at' => now(),
    //             // Use the string if you don't have the Enum imported, or \App\Enums\CaseStatus::ESCALATED
    //             'status' => 'escalated' 
    //         ]);

    //         // Refresh model so UI updates immediately
    //         $this->case->refresh();
    //     }

    //     // 4. Reset & Close
    //     $this->reset(['recipient', 'subject', 'body', 'attachments', 'isEscalationMode']);
    //     $this->dispatch('email-sent'); 
    //     $this->dispatch('workflow-updated');
    // }

    // =========================================================================
    //  AI COPILOT LOGIC
    // =========================================================================

    public function askAiForHelp()
    {
        $this->showAiModal = true;
        $this->isAnalyzing = true;
        $this->aiResponse = null;

        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            $this->aiResponse = "System error: API key missing.";
            $this->isAnalyzing = false;
            return;
        }

        $daysInStage       = (int) $this->case->updated_at->diffInDays(now());
        $totalDeadlineDays = data_get($this->currentStepConfig, 'timeouts.0.days', 14);
        $daysRemaining     = max(0, $totalDeadlineDays - $daysInStage);
        $deadlineBreached  = $daysInStage >= $totalDeadlineDays;
        $emailsSent        = $this->case->emails()->where('direction', 'outbound')->count();
        $escalationLevel   = $this->case->escalation_level ?? 0;
        $stepLabel         = data_get($this->currentStepConfig, 'label', $this->currentStepKey);

        $lastEscalatedAt   = $this->case->last_escalated_at;
        $daysSinceEscalation = $lastEscalatedAt ? (int) $lastEscalatedAt->diffInDays(now()) : null;
        $recentlyEscalated = $daysSinceEscalation !== null && $daysSinceEscalation <= 14;

        // Build deadline context
        $deadlineNote = $deadlineBreached
            ? "The response deadline has been breached by " . ($daysInStage - $totalDeadlineDays) . " day(s)."
            : "There are {$daysRemaining} day(s) left before the response deadline.";

        // Build escalation context so the AI doesn't recommend something already done
        if ($recentlyEscalated) {
            $escalationContext =
                "IMPORTANT: This case was already escalated {$daysSinceEscalation} day(s) ago (escalation level: {$escalationLevel}). " .
                "Do NOT recommend escalating again. The escalation is in progress — the user must wait for the regulator or next party to respond. " .
                "Advise patience and tell them what signs to watch for that would indicate they need to follow up.";
        } elseif ($escalationLevel > 0) {
            $escalationContext =
                "This case has been escalated (level {$escalationLevel}) but it has been {$daysSinceEscalation} day(s) since the last escalation. " .
                "If the deadline has been breached, recommend a firm follow-up with the regulator or ombudsman, not a fresh escalation.";
        } else {
            $escalationContext =
                "This case has not been escalated yet (level 0). " .
                "If the deadline has been breached and no resolution is near, escalation to a regulator or ombudsman may be appropriate.";
        }

        $prompt =
            "You are a dispute resolution advisor giving a quick case status briefing to the person running this dispute.\n\n" .
            "CASE FACTS:\n" .
            "- Institution: {$this->case->institution_name}\n" .
            "- Current Stage: {$stepLabel}\n" .
            "- Days spent in this stage: {$daysInStage} (deadline: {$totalDeadlineDays} days)\n" .
            "- {$deadlineNote}\n" .
            "- Outbound emails sent so far: {$emailsSent}\n" .
            "- {$escalationContext}\n\n" .
            "Write a plain-text status briefing in exactly 3 short paragraphs (no labels, no bullets, no markdown, no asterisks):\n" .
            "Paragraph 1 — Where the case stands right now in plain human terms.\n" .
            "Paragraph 2 — The single most important action they should take next, and why.\n" .
            "Paragraph 3 — Deadline and escalation status. Follow the escalation context strictly — never recommend an action that has already been taken.\n\n" .
            "Maximum 90 words total. Write like a knowledgeable friend, not a legal robot.";

        try {
            $model    = 'gemini-2.5-flash';
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(60)
                ->retry(3, 2000, function (\Exception $e) {
                    if ($e instanceof \Illuminate\Http\Client\ConnectionException) return true;
                    if ($e instanceof \Illuminate\Http\Client\RequestException) {
                        return in_array($e->response->status(), [429, 503]);
                    }
                    return false;
                }, throw: false)
                ->post($endpoint, [
                    'contents'         => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['thinkingConfig' => ['thinkingBudget' => 0]],
                ]);

            if ($response->successful()) {
                $rawText = data_get($response->json(), 'candidates.0.content.parts.0.text', '');
                // Strip any markdown the model sneaks in
                $clean = preg_replace('/\*{1,3}([^*]+)\*{1,3}/', '$1', $rawText);
                $clean = preg_replace('/_{1,2}([^_]+)_{1,2}/', '$1', $clean);
                $this->aiResponse = trim($clean) ?: "No advice could be generated. Please try again.";
            } else {
                Log::error('AI Copilot API Error', ['status' => $response->status(), 'body' => $response->body()]);
                $this->aiResponse = "The AI is temporarily unavailable. Please try again in a moment.";
            }
        } catch (\Exception $e) {
            Log::error('AI Copilot Exception', ['message' => $e->getMessage()]);
            $this->aiResponse = "Connection failed. Please try again shortly.";
        }

        $this->isAnalyzing = false;
    }

    public function closeAiModal()
    {
        $this->showAiModal = false;
    }

    // =========================================================================
    //  WORKFLOW TRANSITIONS
    // =========================================================================

    public function triggerAction($actionKey)
    {
        if (!$this->currentStepConfig) return;

        $actions = collect($this->currentStepConfig['actions'] ?? []);
        $actionDef = $actions->firstWhere('key', $actionKey);

        if ($actionDef) {
            $this->jumpToStep($actionDef['to_step']);
        }
    }

public function jumpToStep($targetStepKey)
{   
    $category = $this->case->institution->category;
    $stepLabel = $this->workflowConfig['steps'][$targetStepKey]['label'] ?? $targetStepKey;
    if ($category->stepRequiresEmail($targetStepKey)) {
        if ($this->case->hasSentEmailForStep($targetStepKey)) {
            // Already sent? Dispatch choice to Alpine
            $this->dispatch('email-already-sent-for-step', [
                'stepKey' => $targetStepKey,
                'stepLabel' => $stepLabel
            ]);
            return;
        }
        // Not sent? Open forced modal
        $this->resendEmailForStep($targetStepKey);
        return;
    }

    $this->proceedToStep($targetStepKey);
}

public function proceedToStep($stepKey)
{
    $this->transitionTo($stepKey, "Manual jump.");
    $this->dispatch('step-jumped-successfully');
}

public function resendEmailForStep($stepKey)
{
    $this->pendingStepJump = $stepKey;
    $recipientData = $this->case->institution->getStepRecipient($stepKey);
    
    $this->dispatch('open-compose-modal', [
        'subject' => "Re: Case #{$this->case->case_reference_id}",
        'recipient' => $recipientData['value'] ?? '',
        'targetStepKey' => $stepKey
    ]);
}


public function sendEmail(\App\Services\SendEmailService $emailService)
{   
    $this->validate(['recipient' => 'required|email', 'subject' => 'required', 'body' => 'required']);

    // 1. Update object in memory so Service "sees" the target step
    $overrides = [];
    if ($this->pendingStepJump) {
        $overrides['metadata'] = ['step_key' => $this->pendingStepJump];
    }


    // 2. Dispatch Email
    $emailService->sendAndLog(
        Auth::user(), $this->case, $this->recipient,
    $this->subject, $this->body, $this->attachments,
    null, $overrides
    );

    // 3. Finalize the Status Change
    if ($this->pendingStepJump) {
        $this->transitionTo($this->pendingStepJump, "Workflow advanced via required email.");
        $this->pendingStepJump = null;
    }

    $this->case->refresh();
    $this->reset(['recipient', 'subject', 'body', 'attachments', 'isEscalationMode']);
    $this->dispatch('email-sent'); 
    $this->dispatch('workflow-updated');
    $this->dispatch('step-jumped-successfully'); // Triggers Alpine reload
}

    private function transitionTo($newStep, $reason)
    {
        $oldStep = $this->currentStepKey;

        if($this->isStepFinal($newStep)){
            $this->case->update(['current_workflow_step' => $newStep, 'status' => \App\Enums\CaseStatus::CLOSED]);
        }else{

            $this->case->update(['current_workflow_step' => $newStep]);
        }
        $this->currentStepKey = $newStep;
        $this->loadStepConfig();
         $stepLabel = $this->currentStepConfig['label'] ?? $newStep;

        try {
            
            // =========================================================
            // NEW CODE: Update Dynamic Recipient Email for the Frontend
            // =========================================================
            $recipientData = $this->case->institution->getStepRecipient($newStep);
            
            $newEmail = '';
            $newUrl = '';

            if ($recipientData) {
                if ($recipientData['type'] === 'email') {
                    $newEmail = $recipientData['value'];
                } elseif ($recipientData['type'] === 'url') {
                    $newUrl = $recipientData['value'];
                    // If it's a URL, auto-fill the email variable with the fallback!
                    $newEmail = $recipientData['fallback_email'] ?? '';
                }
            }
            // Now $newEmail contains either the real email OR the fallback email.
            $this->dispatch('workflow-step-changed', email: $newEmail, url: $newUrl);
            // =========================================================
            CaseTimeline::create([
                'case_id'     => $this->case->id,
                'type'        => 'workflow_change',
                'actor'       => 'user',
                'description' => "Workflow changed to '{$this->currentStepConfig['label']}'",
                'occurred_at' => now(),
                'metadata'    => [
                    'from'   => $oldStep,
                    'to'     => $newStep,
                    'reason' => $reason
                ]
            ]);
            
            $this->dispatch('workflow-updated');

        } catch (\Exception $e) {
            Log::error("Workflow Transition Error: " . $e->getMessage());
        }
    }

    /**
     * Check if a given workflow step key is marked as final in the category config.
     */
    private function isStepFinal(string $stepKey): bool
    {
        // Safely get the category from the case's institution
        $category = $this->case->institution->category ?? null;
        
        // If there is no category or workflow config, it defaults to false
        if (!$category || empty($category->workflow_config['steps'])) {
            return false;
        }

        // Safely extract the configuration for the requested step
        $stepConfig = $category->workflow_config['steps'][$stepKey] ?? null;

        // Check if the 'is_final' key exists and is strictly set to true
        return isset($stepConfig['is_final']) && $stepConfig['is_final'] === true;
    }

    public function render()
    {
        return view('livewire.user.cases.case-workflow');
    }
}