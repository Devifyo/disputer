<?php

namespace App\Livewire\User\Cases;

use Livewire\Component;
use App\Models\Cases;
use App\Models\CaseTimeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\WithFileUploads;
use App\Services\EscalationService;

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
        
        $this->body = "To {$contactName},\n\n" .
                      "I am formally escalating Dispute Case #{$this->case->case_reference_id}.\n" .
                      "Current Escalation Level: {$level}\n\n" .
                      "Reason: The institution has failed to provide a satisfactory response within the required timeframe.\n\n" .
                      "[Please add specific details here]";

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
    public function sendEmail()
    {   
        $this->validate([
            'recipient' => 'required|email',
            'subject'   => 'required|string|max:255',
            'body'      => 'required|string',
        ]);

        // 1. Determine if this is an escalation based on our flag OR subject fallback
        $isEscalation = $this->isEscalationMode || str_contains(strtolower($this->subject), 'escalat');

        // 2. Log to Timeline
        CaseTimeline::create([
            'case_id'     => $this->case->id,
            'type'        => $isEscalation ? 'escalation_sent' : 'email_sent',
            'actor'       => 'user',
            'description' => $isEscalation 
                ? "Escalation (Level " . ($this->case->escalation_level + 1) . ") sent to {$this->recipient}" 
                : "Email sent to {$this->recipient}",
            'occurred_at' => now(),
            'metadata'    => [
                'recipient'    => $this->recipient,
                'sender_email' => Auth::user()->email,
                'subject'      => $this->subject,
                'body'         => $this->body,
                'direction'    => 'outbound',
                'level'        => $isEscalation ? ($this->case->escalation_level + 1) : null
            ]
        ]);

        // 3. Update State if Escalation
        if ($isEscalation) {
            $this->case->update([
                'escalation_level' => $this->case->escalation_level + 1,
                'last_escalated_at' => now(),
                // Use the string if you don't have the Enum imported, or \App\Enums\CaseStatus::ESCALATED
                'status' => 'escalated' 
            ]);

            // Refresh model so UI updates immediately
            $this->case->refresh();
        }

        // 4. Reset & Close
        $this->reset(['recipient', 'subject', 'body', 'attachments', 'isEscalationMode']);
        $this->dispatch('email-sent'); 
        $this->dispatch('workflow-updated');
    }

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

        $daysInStage = (int)$this->case->updated_at->diffInDays(now());
        $totalDeadlineDays = $this->currentStepConfig['timeouts'][0]['days'] ?? 14;
        $isDeadlinePassed = $daysInStage >= $totalDeadlineDays ? 'Yes' : 'No';

        $prompt = "You are a legal workflow assistant. " .
                "Institution: {$this->case->institution_name}. " .
                "Current stage: {$this->currentStepKey}. " .
                "Days elapsed: {$daysInStage}. " .
                "Deadline passed: {$isDeadlinePassed}. " .
                "INSTRUCTION: Suggest the single most appropriate next step based strictly on timing. " .
                "STRICT LIMITS: 30–45 words. Plain text only.";

        try {
            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]]
            ]);

            if ($response->successful()) {
                $rawText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Please check back later.';
                $this->aiResponse = trim(strip_tags($rawText));
            } else {
                $this->aiResponse = "I'm unable to analyze the case details at this moment.";
            }
        } catch (\Exception $e) {
            Log::error("Gemini Assistant Error: " . $e->getMessage());
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
            $this->transitionTo($actionDef['to_step'], "User action: {$actionDef['label']}");
        }
    }

    public function jumpToStep($targetStepKey)
    {
        if (isset($this->workflowConfig['steps'][$targetStepKey])) {
            $this->transitionTo($targetStepKey, "Manual administrative override.");
        }
    }

    private function transitionTo($newStep, $reason)
    {
        try {
            $oldStep = $this->currentStepKey;

            if($this->isStepFinal($newStep)){
                $this->case->update(['current_workflow_step' => $newStep, 'status' => \App\Enums\CaseStatus::CLOSED]);
            }else{

                $this->case->update(['current_workflow_step' => $newStep]);
            }
            
            $this->currentStepKey = $newStep;
            $this->loadStepConfig();
            // =========================================================
            // NEW CODE: Update Dynamic Recipient Email for the Frontend
            // =========================================================
            $recipientData = $this->case->institution->getStepRecipient($newStep);
            $newEmail = ($recipientData && $recipientData['type'] === 'email') ? $recipientData['value'] : '';
            $newUrl = ($recipientData && $recipientData['type'] === 'url') ? $recipientData['value'] : '';
            // dd($recipientData, $newEmail);
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