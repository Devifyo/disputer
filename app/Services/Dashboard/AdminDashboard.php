<?php

namespace App\Services\Dashboard;

use App\Models\Claim;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The admin landing figures: how the flight product is doing - claims,
 * protected trips and the fees they earn. The controller only renders;
 * mirror of the customer-side FlightDashboard.
 */
class AdminDashboard
{
    /** @return array{stats: array, recentUsers: Collection, recentClaims: Collection} */
    public function overview(): array
    {
        return [
            'stats'        => $this->stats(),
            'recentUsers'  => User::latest()->take(10)->get(),
            'recentClaims' => Claim::with(['user', 'signers'])->latest()->take(10)->get(),
        ];
    }

    private function stats(): array
    {
        return [
            'total_users'     => User::customers()->count(),
            'total_claims'    => Claim::count(),
            'claims_review'   => Claim::where('status', Claim::STATUS_PENDING_ELIGIBILITY)->count(),
            'protected_trips' => Trip::count(),
            'trips_watching'  => Trip::where('monitoring_status', Trip::MONITORING_ACTIVE)->count(),
            'fees_earned'     => $this->feesEarned(),
        ];
    }

    /** Success fees actually banked: the fee share of PAID payments, per currency. */
    private function feesEarned(): string
    {
        return Payment::where('status', Payment::STATUS_PAID)
            ->selectRaw('currency, SUM(fee_amount) total')
            ->groupBy('currency')->get()
            ->map(fn ($row) => $row->currency . ' ' . number_format((float) $row->total, 2))
            ->implode(' + ');
    }
}
