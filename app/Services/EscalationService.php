<?php

namespace App\Services;

use App\Models\Cases;

class EscalationService
{
    /**
     * Determine the correct recipient and context based on hierarchy.
     */
    public function getEscalationDetails(Cases $case): array
    {
        $institution = $case->institution;
        $category    = $institution?->category;

        // 1. Institution-specific escalation email
        if ($institution && $institution->escalation_email) {
            return [
                'email'  => $institution->escalation_email,
                'name'   => $institution->escalation_contact_name ?? 'Escalation Department',
                'source' => 'Institution Authority',
            ];
        }

        // 2. Current workflow step's escalation_email
        $stepKey = $case->current_workflow_step;
        if ($stepKey && $category) {
            $stepEmail = $category->workflow_config['steps'][$stepKey]['escalation_email'] ?? null;
            if ($stepEmail) {
                return [
                    'email'  => $stepEmail,
                    'name'   => $category->name . ' Regulator',
                    'source' => 'Workflow Step',
                ];
            }
        }

        // 3. Category-level fallback
        if ($category && $category->fallback_escalation_email) {
            return [
                'email'  => $category->fallback_escalation_email,
                'name'   => $category->name . ' Regulator',
                'source' => 'Category Standard',
            ];
        }

        // 4. Manual override required
        return [
            'email'  => '',
            'name'   => 'Authority',
            'source' => 'Manual Entry',
        ];
    }
}