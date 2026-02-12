<?php

namespace App\Livewire\User\Cases;

use Livewire\Component;
use App\Models\Cases;
use App\Models\CaseTimeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\WithFileUploads;

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

    // --- Email / Escalation State (Synced with Compose Modal) ---
    public $recipient = '';
    public $subject = '';
    public $body = '';
    public $attachments = []; 

    /**
     * Initialize the component with Case data and Workflow configurations
     */
    public function mount(Cases $case)
    {
        $this->case = $case;
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

    /**
     * Load the specific configuration for the active workflow step
     */
    public function loadStepConfig()
    {
        $this->currentStepConfig = $this->workflowConfig['steps'][$this->currentStepKey] ?? null;
    }

    // =========================================================================
    //  EMAIL & ESCALATION LOGIC
    // =========================================================================

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

        // 1. Log the Email in the Activity Timeline
        CaseTimeline::create([
            'case_id'     => $this->case->id,
            'type'        => 'email_sent',
            'actor'       => 'user',
            'description' => "Formal escalation email sent to {$this->recipient}",
            'occurred_at' => now(),
            'metadata'    => [
                'recipient'    => $this->recipient,
                'sender_email' => Auth::user()->email,
                'subject'      => $this->subject,
                'body'         => $this->body,
                'direction'    => 'outbound'
            ]
        ]);

        // 2. Aggressive Transition: Auto-move workflow if an escalation action exists
        if ($this->currentStepConfig) {
            $actions = collect($this->currentStepConfig['actions'] ?? []);
            
            // Search for keys that signify escalation to move the stage forward
            $nextAction = $actions->first(fn($a) => 
                str_contains($a['key'], 'escalate') || 
                str_contains($a['key'], 'submit') || 
                str_contains($a['key'], 'send')
            );
            
            if ($nextAction) {
                $this->transitionTo($nextAction['to_step'], "System: Auto-transitioned after escalation email.");
            }
        }

        // 3. Reset fields and close modal via browser event
        $this->reset(['recipient', 'subject', 'body', 'attachments']);
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

        // Logic for the prompt variables
        $daysInStage = (int)$this->case->updated_at->diffInDays(now());
        $totalDeadlineDays = $this->currentStepConfig['timeouts'][0]['days'] ?? 14;
        $isDeadlinePassed = $daysInStage >= $totalDeadlineDays ? 'Yes' : 'No';

        // Using your refined prompt structure
        $prompt = "You are a legal workflow assistant. " .
                "Institution: {$this->case->institution_name}. " .
                "Current stage: {$this->currentStepKey}. " .
                "Days elapsed in this stage: {$daysInStage}. " .
                "Total deadline for this stage: {$totalDeadlineDays} days. " .
                "Has deadline passed: {$isDeadlinePassed}. " .
                "INSTRUCTION: Suggest the single most appropriate next step based strictly on timing and stage context. " .
                "Do NOT suggest escalation if the deadline has not passed unless there is exceptional reason. " .
                "If early in the timeline, recommend waiting or monitoring. " .
                "STRICT LIMITS: 30–45 words. Plain text only. " .
                "FORBIDDEN: No markdown, no bullet points, no labels, no legal disclaimers.";

        try {
            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]]
            ]);

            if ($response->successful()) {
                $rawText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Please check back once the deadline has passed.';
                
                // Clean up: Remove any lingering markdown or HTML tags for a purely plain-text feel
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
            
            // Update Case Model
            $this->case->update(['current_workflow_step' => $newStep]);
            
            // Update Local State
            $this->currentStepKey = $newStep;
            $this->loadStepConfig();

            // Create Audit Log in Timeline
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

    public function render()
    {
        return view('livewire.user.cases.case-workflow');
    }
}