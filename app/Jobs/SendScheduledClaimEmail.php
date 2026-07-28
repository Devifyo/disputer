<?php

namespace App\Jobs;

use App\Models\ClaimCorrespondence;
use App\Services\Claims\ClaimCorrespondenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends an email an admin scheduled for later. The correspondence record
 * already exists (status "scheduled"), so the claim timeline shows what is
 * coming; this delivers it and flips the status.
 */
class SendScheduledClaimEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function __construct(private readonly int $correspondenceId, private readonly array $attachmentKeys = [])
    {
    }

    public function handle(ClaimCorrespondenceService $correspondence): void
    {
        $record = ClaimCorrespondence::with('claim')->find($this->correspondenceId);

        // Cancelled, already sent, or the claim is gone: nothing to do.
        if (!$record || !$record->claim || $record->status !== ClaimCorrespondence::STATUS_SCHEDULED) {
            return;
        }

        $correspondence->deliverScheduled($record, $this->attachmentKeys);
    }

    public function failed(Throwable $e): void
    {
        ClaimCorrespondence::where('id', $this->correspondenceId)
            ->where('status', ClaimCorrespondence::STATUS_SCHEDULED)
            ->update(['status' => ClaimCorrespondence::STATUS_FAILED]);
    }
}
