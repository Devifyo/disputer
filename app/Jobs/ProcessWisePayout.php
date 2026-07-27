<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Services\Payments\WisePayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Executes one Wise transfer off the request cycle. Transient API errors
 * retry with backoff; when the retries are exhausted the payout is marked
 * failed, the customer is told, and admins are alerted to retry manually.
 */
class ProcessWisePayout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> seconds between attempts */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $payoutId)
    {
    }

    public function handle(WisePayoutService $wise): void
    {
        $payout = Payout::find($this->payoutId);

        if (!$payout || $payout->status !== Payout::STATUS_PROCESSING) {
            return; // cancelled or already handled while queued
        }

        $wise->executeTransfer($payout);
    }

    public function failed(Throwable $exception): void
    {
        $payout = Payout::find($this->payoutId);

        if ($payout && $payout->status === Payout::STATUS_PROCESSING) {
            app(WisePayoutService::class)->markFailed($payout, $exception->getMessage());
        }
    }
}
