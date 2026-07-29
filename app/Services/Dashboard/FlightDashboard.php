<?php

namespace App\Services\Dashboard;

use App\Models\Claim;
use App\Models\ClaimSigner;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserPayoutAccount;
use Illuminate\Support\Collection;

/**
 * What a customer needs to see the moment they log in: the money, the state
 * of their claims and monitored trips, and - first - anything that is
 * waiting on them. The controller only renders; the questions are asked here.
 */
class FlightDashboard
{
    public function for(User $user): array
    {
        $claims = Claim::where('user_id', $user->id)
            ->with(['signers'])
            ->latest('id')
            ->get();

        $trips = Trip::where('user_id', $user->id)->latest('departure_date')->get();

        return [
            'stats'     => $this->stats($user, $claims, $trips),
            'actions'   => $this->actions($user, $claims),
            'claims'    => $claims->take(5),
            'trips'     => $trips->take(5),
            'hasClaims' => $claims->isNotEmpty(),
            'hasTrips'  => $trips->isNotEmpty(),
        ];
    }

    /** @param Collection<int, Claim> $claims */
    private function stats(User $user, Collection $claims, Collection $trips): array
    {
        // Money the customer has actually been paid, per currency.
        $paid = Payment::where('user_id', $user->id)
            ->where('status', Payment::STATUS_PAID)
            ->selectRaw('currency, SUM(net_amount) total')
            ->groupBy('currency')->get();

        $inProgress = $claims->reject(fn (Claim $claim) => in_array($claim->workflow_state, ['paid', 'closed', 'denied'], true)
            || $claim->status === Claim::STATUS_REJECTED)->count();

        return [
            'claims_total'   => $claims->count(),
            'claims_active'  => $inProgress,
            'trips_watched'  => $trips->where('monitoring_status', Trip::MONITORING_ACTIVE)->count(),
            'trips_total'    => $trips->count(),
            // Money as data, not a concatenated string - the view decides how
            // to show two currencies without them running off the card.
            'recovered'      => $paid->map(fn ($row) => ['currency' => $row->currency, 'amount' => (float) $row->total])->values()->all(),
            'expected'       => $this->expected($claims),
        ];
    }

    /** @return array<int, array{currency: string, amount: float}> still being chased */
    private function expected(Collection $claims): array
    {
        return $claims
            ->filter(fn (Claim $claim) => $claim->status === Claim::STATUS_ELIGIBLE
                && !in_array($claim->workflow_state, ['paid', 'closed', 'denied'], true)
                && (float) $claim->compensation_amount > 0)
            ->groupBy(fn (Claim $claim) => $claim->compensation_currency ?: 'CAD')
            ->map(fn (Collection $group) => $group->sum(fn (Claim $claim) => (float) $claim->compensation_amount * max(1, count($claim->passengerNames()))))
            ->map(fn (float $amount, string $currency) => ['currency' => $currency, 'amount' => $amount])
            ->values()->all();
    }

    /**
     * Things only the customer can clear. Ordered by what blocks the money
     * soonest, because that is the order they should be done in.
     *
     * @return array<int, array{label: string, detail: string, url: string, cta: string}>
     */
    private function actions(User $user, Collection $claims): array
    {
        $actions = [];

        foreach ($claims as $claim) {
            if ($claim->status !== Claim::STATUS_ELIGIBLE) {
                continue;
            }

            if (!$claim->confirmed_at) {
                $actions[] = [
                    'label'  => 'Confirm your claim',
                    'detail' => "We can't file it until you confirm the details.",
                    'claim'  => $claim,
                    'url'    => url('/flight-disputes/claims/' . encrypt_id($claim->id) . '/confirm'),
                    'cta'    => 'Confirm',
                ];

                continue;
            }

            $pending = $claim->signers->where('status', ClaimSigner::STATUS_PENDING);

            if ($pending->isNotEmpty()) {
                $actions[] = [
                    'label'  => 'Sign your authorisation',
                    'detail' => $pending->count() . ' signature' . ($pending->count() === 1 ? '' : 's') . ' still needed before we can file.',
                    'claim'  => $claim,
                    'url'    => url('/flight-disputes/claims/' . encrypt_id($claim->id) . '/sign'),
                    'cta'    => 'Sign',
                ];
            }
        }

        // Money is waiting but we have nowhere to send it.
        $awaitingPayout = Payment::where('user_id', $user->id)
            ->whereIn('status', [Payment::STATUS_RECEIVED, Payment::STATUS_READY_FOR_PAYOUT])
            ->exists();

        if ($awaitingPayout && !UserPayoutAccount::defaultFor($user)) {
            array_unshift($actions, [
                'label'  => 'Add your bank details',
                'detail' => "Your compensation is ready - we can't pay you without an account to send it to.",
                'claim'  => null,
                'url'    => url('/flight-disputes'),
                'cta'    => 'Add account',
            ]);
        }

        return array_slice($actions, 0, 4);
    }
}
