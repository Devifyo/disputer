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
        // 1. Primary: Check Institution Specific
        if ($case->institution && $case->institution->escalation_email) {
            return [
                'email' => $case->institution->escalation_email,
                'name'  => $case->institution->escalation_contact_name ?? 'Escalation Department',
                'source' => 'Institution Authority'
            ];
        }
        // 2. Fallback: Check Category Default
        if ($case->institution->category && $case->institution->category->fallback_escalation_email) {
 
            return [
                'email' => $case->institution->category->fallback_escalation_email,
                'name'  => $case->institution->category->name . ' Regulator',
                'source' => 'Category Standard'
            ];
        }

        // 3. Manual Override Required
        return [
            'email' => '', // Empty to force user input
            'name'  => 'Authority',
            'source' => 'Manual Entry'
        ];
    }
}