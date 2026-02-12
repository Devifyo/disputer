<?php

namespace App\Livewire\User\Cases;

use Livewire\Component;
use App\Models\Cases;
use App\Models\CaseTimeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\WithFileUploads; // Needed for attachments if modal uses them

class CaseWorkflow extends Component
{
    use WithFileUploads;

    public $case;
    public $workflowConfig;
    public $currentStepKey;
    public $currentStepConfig;

    // --- AI State ---
    public $showAiModal = false; 
    public $isAnalyzing = false;
    public $aiResponse = null;

    // --- Email / Escalation State ---
    // These match standard email composition fields
    public $recipient = '';
    public $subject = '';
    public $body = '';
    public $attachments = []; 

    public function mount(Cases $case)
    {
        $this->case = $case;
        $this->workflowConfig = $this->case->institution->category->workflow_config ?? [];

        $dbValue = $this->case->current_workflow_step;
        $initialStep = $this->workflowConfig['initial_step'] ?? 'draft';

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
     * Called by the "Escalate" button via Alpine or direct click
     */
    public function prepareEscalation($targetName, $targetEmail)
    {
        // 1. Pre-fill data
        $this->recipient = $targetEmail; 
        $this->subject = "Formal Escalation: Case #{$this->case->case_reference_id}";
        $this->body = "To {$targetName},\n\nI am writing to formally escalate my dispute regarding Case #{$this->case->case_reference_id} due to lack of resolution.\n\n[Please add specific details here...]";
        
        // 2. The frontend AlpineJS will detect these changes via @entangle 
        // and open the modal.
    }

    public function sendEmail()
    {
        $this->validate([
            'recipient' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        // 1. Log the Email
        CaseTimeline::create([
            'case_id' => $this->case->id,
            'type' => 'email_sent',
            'actor' => 'user',
            'description' => "Escalation email sent to {$this->recipient}",
            'occurred_at' => now(),
            'metadata' => [
                'recipient' => $this->recipient,
                'sender_email' => Auth::user()->email,
                'subject' => $this->subject,
                'body' => $this->body,
                'direction' => 'outbound'
            ]
        ]);

        // 2. Trigger Workflow Action (if an 'escalate' action exists)
        // Find an action key that looks like escalation to move the step forward
        if ($this->currentStepConfig) {
            $actions = collect($this->currentStepConfig['actions'] ?? []);
            $escalateAction = $actions->first(fn($a) => str_contains($a['key'], 'escalate') || str_contains($a['key'], 'submit'));
            
            if ($escalateAction) {
                $this->triggerAction($escalateAction['key']);
            }
        }

        // 3. Reset & Close
        $this->reset(['recipient', 'subject', 'body', 'attachments']);
        $this->dispatch('email-sent'); // Frontend listens to close modal
        $this->dispatch('workflow-updated');
    }

    // =========================================================================
    //  AI & STANDARD ACTIONS
    // =========================================================================
    
    public function askAiForHelp() 
    {
        $this->showAiModal = true;
        $this->isAnalyzing = true;
        $this->aiResponse = null; 
        
        // ... (Your existing Gemini Logic) ...
        // Mocking response for brevity:
        $this->aiResponse = "Based on the timeline, I recommend escalating immediately.";
        $this->isAnalyzing = false;
    }
    
    public function closeAiModal() { $this->showAiModal = false; }

    public function triggerAction($actionKey)
    {
        if (!$this->currentStepConfig) return;
        $actions = collect($this->currentStepConfig['actions'] ?? []);
        $actionDef = $actions->firstWhere('key', $actionKey);
        if (!$actionDef) return;
        $this->transitionTo($actionDef['to_step'], "User clicked: {$actionDef['label']}");
    }

    private function transitionTo($newStep, $reason)
    {
        try {
            $oldStep = $this->currentStepKey;
            $this->case->update(['current_workflow_step' => $newStep]);
            $this->currentStepKey = $newStep;
            $this->loadStepConfig();

            CaseTimeline::create([
                'case_id' => $this->case->id,
                'type' => 'workflow_change',
                'actor' => 'user', 
                'description' => "Workflow changed from '{$oldStep}' to '{$newStep}'",
                'occurred_at' => now(),
                'metadata' => ['from' => $oldStep, 'to' => $newStep, 'reason' => $reason]
            ]);
            $this->dispatch('workflow-updated');
        } catch (\Exception $e) {
            Log::error("Workflow Error: " . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.user.cases.case-workflow');
    }
}