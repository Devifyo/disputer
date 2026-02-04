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
        // Eager load institution to avoid N+1 queries
        // We also grab the 'latest' timeline entry to try and find metadata if needed
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
     * Since amount isn't on the main table, we look for it in the history.
     */
    public function extractCaseMetadata(Cases $case): array
    {
        // Look for the timeline event where the dispute was created or details logged
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

    public function getWorkflowDetails(Cases $case): array
    {
        // 1. Get the JSON config from the related Category
        // defined in the database (or fallback to config file)
        $config = $case->institution->category->workflow_config 
                  ?? config('workflow_templates.standard');

        $steps = $config['steps'] ?? [];
        $totalSteps = count($steps);
        $currentStepIdx = $case->current_workflow_step; // e.g., 1

        // 2. Find the current step name (Arrays are 0-indexed, steps are 1-indexed)
        // If step is 1, we want index 0.
        $stepData = $steps[$currentStepIdx - 1] ?? end($steps);
        
        $stepName = $stepData['name'] ?? 'Processing';

        // 3. Calculate Percentage
        $progress = $totalSteps > 0 ? round(($currentStepIdx / $totalSteps) * 100) : 0;

        return [
            'step_name' => $stepName,
            'current_step' => $currentStepIdx,
            'total_steps' => $totalSteps,
            'progress_percent' => $progress
        ];
    }
}