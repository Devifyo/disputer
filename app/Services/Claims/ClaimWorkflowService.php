<?php

namespace App\Services\Claims;

use App\Models\Claim;
use App\Models\ClaimLifecycleStage;
use App\Models\ClaimWorkflowTimer;
use App\Services\Notifications\AdminNotifier;
use App\Support\Alerts\AdminAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The claim workflow engine. Every lifecycle change goes through here -
 * controllers and UI never update claim states directly. Stages and their
 * transition rules are admin-configurable (ClaimLifecycleStage); this
 * service validates transitions, runs the configured side effects
 * (timers, notifications, customer timeline), and writes the audit trail.
 */
class ClaimWorkflowService
{
    /**
     * Move a claim to a new stage.
     *
     * @param string $via customer | admin | system | airline
     * @throws RuntimeException when the transition is not allowed
     */
    public function transition(Claim $claim, string $to, string $via = 'system', ?int $actorId = null, ?string $notes = null, array $attributes = []): Claim
    {
        $workflowId = $claim->resolvedWorkflowId();
        $current    = ClaimLifecycleStage::byKey($claim->workflow_state, $workflowId);
        $target     = ClaimLifecycleStage::byKey($to, $workflowId);

        if (!$this->can($claim, $to, $via)) {
            throw new RuntimeException(sprintf(
                'Invalid claim transition: %s -> %s (via %s).',
                $current?->name ?? $claim->workflow_state, $target?->name ?? $to, $via
            ));
        }

        $claim->forceFill(['workflow_state' => $to] + $attributes)->save();

        // Leaving a stage cancels its pending auto-timers.
        $claim->workflowTimers()
            ->where('status', ClaimWorkflowTimer::STATUS_PENDING)
            ->update(['status' => ClaimWorkflowTimer::STATUS_CANCELLED, 'completed_at' => now()]);

        $this->audit($claim, "Stage changed: {$current?->name} -> {$target->name}", $via, $actorId, $notes, $current?->key, $target->key);

        // Customer-facing progress (simplified label only - internals stay internal).
        if ($target->customer_visible && $target->customer_label) {
            $claim->events()->where('status', 'pending')->update(['status' => 'done']);
            $claim->recordEvent($target->customer_label, $target->is_final ? 'done' : ($this->isRestingStage($target) ? 'pending' : 'done'), now(), 3);
        }

        if ($target->notify_admin) {
            $this->notifyAdmins($claim, $target, $notes ?: ($via === 'system' ? 'Moved automatically by the workflow engine.' : 'Moved by an administrator.'));
        }

        if ($target->notify_customer && $target->customer_visible && $target->customer_label) {
            $this->notifyCustomer($claim, $target);
        }

        // Configured AI assistance: prepare a DRAFT for the admin - never sent.
        if ($target->ai_action) {
            $this->generateStageDraft($claim, $target);
        }

        // Configured automation: immediate chain or a scheduled timer.
        if ($target->auto_next_stage && ClaimLifecycleStage::byKey($target->auto_next_stage, $workflowId)) {
            if ((int) $target->auto_delay_days === 0) {
                return $this->transition($claim->refresh(), $target->auto_next_stage, 'system', null, "Automatic after {$target->name}.");
            }

            $claim->workflowTimers()->create([
                'purpose' => 'stage_auto',
                'due_at'  => now()->addDays((int) $target->auto_delay_days),
                'meta'    => ['from_stage' => $target->key, 'to_stage' => $target->auto_next_stage],
            ]);
        }

        return $claim->refresh();
    }

    /** Whether the move is allowed by the configured rules. */
    public function can(Claim $claim, string $to, string $via = 'system'): bool
    {
        $workflowId = $claim->resolvedWorkflowId();
        $current    = ClaimLifecycleStage::byKey($claim->workflow_state, $workflowId);
        $target     = ClaimLifecycleStage::byKey($to, $workflowId);

        if (!$current || !$target || !$target->is_active || $current->is_final) {
            return false;
        }

        if (!in_array($to, $current->next_stages ?? [], true)) {
            return false;
        }

        // Configured required roles for manual entry.
        if ($via === 'admin' && !empty($target->permissions)) {
            $user = auth()->user();
            if (!$user || !$user->hasAnyRole($target->permissions)) {
                return false;
            }
        }

        return match ($via) {
            'admin'  => $target->allow_manual,
            default  => $target->allow_auto || $target->allow_manual,
        };
    }

    /** Stages an admin may move this claim to right now. */
    public function manualOptions(Claim $claim): Collection
    {
        $workflowId = $claim->resolvedWorkflowId();
        $current    = ClaimLifecycleStage::byKey($claim->workflow_state, $workflowId);

        return collect($current?->next_stages ?? [])
            ->map(fn ($key) => ClaimLifecycleStage::byKey($key, $workflowId))
            ->filter(fn ($stage) => $stage && $stage->is_active && $stage->allow_manual && $stage->admin_visible)
            ->values();
    }

    /** Evaluate due timers - called by the scheduler. Returns fired count. */
    public function evaluateTimers(): int
    {
        $due = ClaimWorkflowTimer::where('status', ClaimWorkflowTimer::STATUS_PENDING)
            ->where('due_at', '<=', now())
            ->with('claim')
            ->get();

        $fired = 0;

        foreach ($due as $timer) {
            $claim = $timer->claim;
            $to    = $timer->meta['to_stage'] ?? null;

            // Only fire if the claim is still sitting in the stage the timer was set for.
            if ($claim && $to && $claim->workflow_state === ($timer->meta['from_stage'] ?? null) && $this->can($claim, $to)) {
                try {
                    $this->transition($claim, $to, 'system', null, sprintf(
                        'Timer expired: %d day(s) in "%s" without progress.',
                        (int) $timer->created_at->diffInDays(now()),
                        ClaimLifecycleStage::byKey($timer->meta['from_stage'], $claim->resolvedWorkflowId())?->name ?? $timer->meta['from_stage'],
                    ));
                    $fired++;
                } catch (Throwable $e) {
                    Log::warning('Workflow timer transition failed', ['timer' => $timer->id, 'error' => $e->getMessage()]);
                }
            }

            $timer->forceFill(['status' => ClaimWorkflowTimer::STATUS_COMPLETED, 'completed_at' => now()])->save();
        }

        return $fired;
    }

    /** Immutable audit entry - transitions and key actions both land here. */
    public function audit(Claim $claim, string $action, string $via = 'system', ?int $actorId = null, ?string $notes = null, ?string $from = null, ?string $to = null): void
    {
        $claim->auditLogs()->create([
            'action'     => $action,
            'from_state' => $from,
            'to_state'   => $to,
            'via'        => $via,
            'actor_id'   => $actorId,
            'notes'      => $notes,
        ]);
    }

    private function notifyAdmins(Claim $claim, ClaimLifecycleStage $stage, string $reason): void
    {
        $url = url('/admin/flight-claims/claims/' . $claim->id);

        app(AdminNotifier::class)->send(new AdminAlert(
            type: AdminAlertRecipients::TYPE_ESCALATION,
            title: sprintf('Claim #%s needs an escalation decision', $claim->number),
            description: sprintf('%s - %s', $stage->name, $reason),
            url: $url,
            template: 'claim-escalation-alert',
            replacements: [
                '[CLAIM]'     => '#' . $claim->number,
                '[STAGE]'     => $stage->name,
                '[FLIGHT]'    => trim(($claim->airline ?? '') . ' ' . ($claim->flight_number ?? '')),
                '[ROUTE]'     => "{$claim->departure_airport} - {$claim->arrival_airport}",
                '[REASON]'    => $reason,
                '[CLAIM_URL]' => $url,
            ],
        ));
    }

    private function notifyCustomer(Claim $claim, ClaimLifecycleStage $stage): void
    {
        $email = $claim->contact_email ?: $claim->user?->email;
        if (!$email) {
            return;
        }

        try {
            send_dynamic_email($email, 'claim-stage-update', [
                '[NAME]'      => $claim->passenger_name ?: 'traveller',
                '[CLAIM]'     => '#' . $claim->number,
                '[STAGE]'     => $stage->customer_label,
                '[FLIGHT]'    => trim(($claim->airline ?? '') . ' ' . ($claim->flight_number ?? '')),
                '[ROUTE]'     => "{$claim->departure_airport} - {$claim->arrival_airport}",
                '[CLAIM_URL]' => url('/flight-disputes/claims/' . encrypt_id($claim->id)),
            ]);
        } catch (Throwable $e) {
            Log::warning('Customer stage notification failed', ['claim' => $claim->id, 'error' => $e->getMessage()]);
        }
    }

    /** Stage-configured AI assistance: store a new draft version for admin review. */
    private function generateStageDraft(Claim $claim, ClaimLifecycleStage $stage): void
    {
        try {
            $type   = $stage->ai_action;
            $result = app(ClaimLetterService::class)->generate($claim, $type, ['trigger' => "stage:{$stage->key}"]);

            $claim->drafts()->create([
                'type'         => $type,
                'version'      => ($claim->drafts()->where('type', $type)->max('version') ?? 0) + 1,
                'subject'      => $result['subject'],
                'body'         => $result['body'],
                'context'      => ['trigger' => "stage:{$stage->key}"],
                'generated_by' => $result['generated_by'],
            ]);

            $this->audit($claim, "AI draft prepared on entering {$stage->name} (" . str_replace('_', ' ', $type) . ') - awaiting admin review', 'system');
        } catch (Throwable $e) {
            Log::warning('Stage AI draft generation failed', ['claim' => $claim->id, 'stage' => $stage->key, 'error' => $e->getMessage()]);
        }
    }

    /** Stages where the claim rests waiting on someone - shown as an open customer step. */
    private function isRestingStage(ClaimLifecycleStage $stage): bool
    {
        if ($stage->is_final || $stage->key === 'paid') {
            return false;
        }

        // Pass-through stages (immediate auto-chain) complete instantly.
        return !((int) $stage->auto_delay_days === 0 && $stage->auto_next_stage);
    }
}
