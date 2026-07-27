<?php

namespace App\Console\Commands;

use App\Models\Payout;
use App\Services\Payments\WisePayoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * SANDBOX ONLY: walk a Wise transfer through its lifecycle using Wise's
 * simulation API, then re-sync the payout. Lets the whole payout loop -
 * send, state change, completion, customer email - be tested end to end
 * without a funded balance or a real recipient bank account.
 */
class WiseSimulate extends Command
{
    protected $signature   = 'wise:simulate {payout : Payout ID} {--state=outgoing_payment_sent : Target state}';
    protected $description = 'Sandbox only: advance a Wise transfer through its states and re-sync the payout';

    /** Accept a payout ID, or a claim number (its latest Wise payout). */
    private function resolvePayout(string $key): ?Payout
    {
        if ($payout = Payout::find($key)) {
            return $payout;
        }

        return Payout::where('method', Payout::METHOD_WISE)
            ->whereHas('payment.claim', fn ($q) => $q->where('number', $key)->orWhere('reference', $key))
            ->latest('id')
            ->first();
    }

    /** The order Wise allows states to advance in. */
    private const CHAIN = ['processing', 'funds_converted', 'outgoing_payment_sent'];

    public function handle(WisePayoutService $wise): int
    {
        if (!$wise->sandbox()) {
            $this->error('Refusing: WISE_SANDBOX is off - simulation exists only in the sandbox.');

            return self::FAILURE;
        }

        $payout = $this->resolvePayout((string) $this->argument('payout'));

        if (!$payout) {
            $this->error('No payout found by that ID or claim number.');
            foreach (Payout::latest('id')->limit(5)->get() as $candidate) {
                $this->line(sprintf('  payout #%d  %s  %s  claim #%s  transfer %s',
                    $candidate->id, $candidate->status, $candidate->money(),
                    $candidate->payment?->claim?->number, $candidate->wise_transfer_id ?: '-'));
            }

            return self::FAILURE;
        }

        if (!$payout->wise_transfer_id) {
            $this->error("Payout #{$payout->id} has no Wise transfer yet - send it first.");

            return self::FAILURE;
        }

        // Fund first - simulation only advances funded transfers.
        $funding = $wise->fund($payout);
        $this->line('  funding                ' . (isset($funding['skipped']) ? $funding['skipped'] : ($funding['status'] ?? 'ok')));

        $target = (string) $this->option('state');
        $states = array_slice(self::CHAIN, 0, (int) array_search($target, self::CHAIN, true) + 1);

        if (!in_array($target, self::CHAIN, true)) {
            $states = [$target]; // e.g. funds_refunded / cancelled failure paths
        }

        foreach ($states as $state) {
            // Sandbox V2 simulation is a GET, one state hop at a time.
            $response = Http::withToken(config('services.wise.token'))
                ->acceptJson()->timeout(20)
                ->get(rtrim(config('services.wise.base_url'), '/') . "/v1/simulation/transfers/{$payout->wise_transfer_id}/{$state}");

            $this->line(sprintf('  %-22s %s', $state, $response->successful() ? 'ok' : 'HTTP ' . $response->status() . ' - ' . \Illuminate\Support\Str::limit($response->json('errors.0.message') ?? $response->body(), 90)));
        }

        $wise->refreshStatus($payout->fresh());
        $payout->refresh();

        // Last resort: if Wise's simulation could not advance the transfer
        // (unfunded, or a sandbox hiccup), apply the target state locally so
        // testing never waits. Safe because this command refuses to run
        // outside the sandbox; live completion stays webhook-verified.
        if ($payout->transfer_status !== $target && $payout->status !== Payout::STATUS_COMPLETED) {
            $this->warn("Wise's sandbox did not advance the transfer - applying '{$target}' locally (sandbox only).");
            $wise->applyTransferState($payout->fresh(), $target);
            $payout->refresh();
        }

        $this->info("Payout {$payout->id}: {$payout->status} (Wise state: {$payout->transfer_status})");
        $this->info('Payment status: ' . $payout->payment->fresh()->status);

        return self::SUCCESS;
    }
}
