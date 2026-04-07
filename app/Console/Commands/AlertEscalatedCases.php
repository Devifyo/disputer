<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cases;
use App\Models\CaseTimeline;
use App\Enums\CaseStatus;

class AlertEscalatedCases extends Command
{
    protected $signature = 'cases:alert-escalations';
    protected $description = 'Send instant alert emails to users whose cases have just passed their workflow timeout deadline';

    public function handle()
    {
        $cases = Cases::with(['user', 'institution.category'])
            ->whereNotIn('status', [CaseStatus::CLOSED->value, CaseStatus::RESOLVED->value])
            ->get();

        $alerted = 0;

        foreach ($cases as $case) {
            $status = $case->getWorkflowStatus();

            if (!$status['is_escalated']) {
                continue;
            }

            // Only send once per step — skip if alert already logged for this step
            $alreadySent = $case->timeline()
                ->where('type', 'escalation_deadline_alert')
                ->where('metadata->step_key', $status['step_key'])
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $user = $case->user;
            if (!$user || !$user->email) {
                continue;
            }

            $sent = send_dynamic_email($user->email, 'escalation-deadline-alert', [
                '[USER_NAME]'    => $user->name,
                '[CASE_REFERENCE]' => $case->case_reference_id,
                '[INSTITUTION]'  => $case->institution_name,
                '[STEP_NAME]'    => $status['step_name'],
                '[WAITING_FOR]'  => $status['waiting_for'],
                '[MAX_DAYS]'     => $status['max_days'],
                '[DAYS_OVER]'    => $status['escalated_from_days'],
                '[CASE_URL]'     => route('user.cases.show', $case->case_reference_id),
            ]);

            if (!$sent) {
                $this->warn("Failed to send alert for case {$case->case_reference_id}");
                continue;
            }

            // Log to timeline so alert is never re-sent for this step
            CaseTimeline::create([
                'case_id'     => $case->id,
                'type'        => 'escalation_deadline_alert',
                'actor'       => 'system',
                'description' => "Deadline alert email sent for step: {$status['step_name']}",
                'metadata'    => ['step_key' => $status['step_key']],
                'occurred_at' => now(),
            ]);

            $alerted++;
            $this->info("Alerted {$user->email} for case {$case->case_reference_id} ({$status['escalated_from_days']} day(s) overdue)");
        }

        $this->info("Done. {$alerted} alert(s) sent.");
    }
}
