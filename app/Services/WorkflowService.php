<?php

namespace App\Services;

use App\Models\Cases;
use App\Enums\CaseStatus;
use Carbon\Carbon;

class WorkflowService
{
    /**
     * Advance a case to the next step in its defined workflow.
     */
    public function advanceCase(Cases $case)
    {
        // 1. Load the workflow from the Category DB record
        $category = $case->institution->category;
        $config = $category->workflow_config;

        if (empty($config) || !isset($config['steps'])) {
            // Fallback safety if DB is empty
            $config = config('workflow_templates.standard');
        }

        $steps = $config['steps'];
        
        // 2. Identify Current Position (Array is 0-indexed, steps are 1-indexed)
        // If current_workflow_step is 1, we are technically done with step 1 logic, moving to 2.
        // Or, we are currently AT step 1 waiting for step 2.
        // Let's assume we want to trigger the NEXT action.
        
        $nextStepIndex = $case->current_workflow_step; // e.g., If currently at 1, index 1 is Step 2.
        
        if (!isset($steps[$nextStepIndex])) {
            return "Case has reached the end of the workflow.";
        }

        $nextStep = $steps[$nextStepIndex];

        // 3. Execute Action logic here (e.g. Send Email)
        // ... mail logic ...

        // 4. Update Case State
        $case->update([
            'current_workflow_step' => $case->current_workflow_step + 1,
            'next_action_at' => Carbon::now()->addDays($nextStep['wait_days']),
            'status' => CaseStatus::ESCALATED
        ]);

        return "Advanced to step: " . $nextStep['name'];
    }
}