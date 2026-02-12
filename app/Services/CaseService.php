<?php

namespace App\Services;

use App\Models\Cases;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class CaseService
{
    /**
     * Fetch filtered cases for the dashboard.
     */
    public function getUserCases(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Cases::with('institution')
            ->where('user_id', Auth::id());

        // 1. Filter by Status (Enum)
        if (!empty($filters['status']) && $filters['status'] !== 'All Statuses') {
            $query->where('status', $filters['status']);
        }

        // 2. Filter by Category (via Institution Relationship)
        if (!empty($filters['category']) && $filters['category'] !== 'All Categories') {
            $query->whereHas('institution', function (Builder $q) use ($filters) {
                $q->where('category', $filters['category']);
            });
        }

        // 3. Search (Reference ID or Institution Name)
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('case_reference_id', 'like', $term)
                  ->orWhere('institution_name', 'like', $term);
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Find a case by Reference ID with all history and attachments.
     */
    public function getCaseByReference(string $referenceId): Cases
    {
        return Cases::with(['institution', 'timeline', 'attachments'])
            ->where('case_reference_id', $referenceId)
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }

    /**
     * Helper: Extract "Amount" from the timeline metadata.
     */
    public function extractCaseMetadata(Cases $case): array
    {
        $creationLog = $case->timeline->first(function ($log) {
            return !empty($log->metadata['amount']);
        });

        if ($creationLog) {
            return [
                'amount' => $creationLog->metadata['amount'] ?? '0.00',
                'txn_date' => $creationLog->metadata['transaction_date'] ?? 'N/A',
                'ref_num' => $creationLog->metadata['reference_number'] ?? 'N/A',
            ];
        }

        return ['amount' => '0.00', 'txn_date' => '--', 'ref_num' => '--'];
    }

    /**
     * FIXED: This function now handles STRING steps (e.g., 'draft') 
     * instead of integer steps.
     */
    public function getWorkflowDetails(Cases $case): array
    {
        // 1. Get the JSON config from the related Category
        $config = $case->institution->category->workflow_config ?? [];
        $steps = $config['steps'] ?? [];

        // 2. Get Keys (e.g. ['draft', 'waiting_for_response', ...])
        $stepKeys = array_keys($steps);
        $totalSteps = count($stepKeys);
        
        // 3. Find the INDEX of the current step (0, 1, 2...)
        $currentStepKey = $case->current_workflow_step;
        
        // Safety: If step is missing or invalid, default to 0 (first step)
        $currentIndex = array_search($currentStepKey, $stepKeys);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        // 4. Get readable name
        // (Use the key to look up the 'label' in the config)
        $stepLabel = $steps[$currentStepKey]['label'] ?? 'Processing';

        // 5. Calculate Percentage
        // Avoid Division by Zero
        if ($totalSteps > 1) {
            $progress = ($currentIndex / ($totalSteps - 1)) * 100;
        } else {
            $progress = 0; // Or 100 if there is only 1 step
        }

        return [
            'step_name' => $stepLabel,
            'current_step' => $currentIndex + 1, // Human readable (1-based)
            'total_steps' => $totalSteps,
            'progress_percent' => round($progress)
        ];
    }
}