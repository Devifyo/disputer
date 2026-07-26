<?php

namespace App\Services\Billing;

use App\Models\Setting;
use App\Models\User;

/**
 * The single authority on "does this user get this feature?".
 *
 * Two admin-configured layers, both in Settings (no code changes):
 *  - subscriptions.enabled: the master switch. OFF means the platform runs
 *    free - every check passes, checkout is hidden, stored subscriptions
 *    are kept but ignored.
 *  - subscriptions.features: which features require Plus. A feature not
 *    ticked is free for everyone even while the system is enabled.
 *
 * Deliberately independent of claim compensation, success fees and payouts.
 */
class SubscriptionGate
{
    /** Everything an admin can put behind the paywall. */
    public const FEATURES = [
        'flight_claims'       => 'Flight claims - creating new compensation claims',
        'flight_monitoring'   => 'Flight monitoring - Trip Protection on upcoming flights',
        'ai_claim_drafting'   => 'AI claim drafting - initial airline claim letters',
        'ai_follow_up_drafts' => 'AI follow-up drafts',
        'ai_regulator_drafts' => 'AI regulator complaint drafts',
        'priority_processing' => 'Priority filing queue - claims jump the review and filing queue',
        'multi_passenger'     => 'Family / multi-passenger claims',
        'auto_filing'         => 'Fully automatic claim filing - submitted to the airline on consent',
    ];

    public static function enabled(): bool
    {
        return (bool) Setting::get('subscriptions.enabled', 0);
    }

    /** Features currently requiring Plus, as feature => true. */
    public static function premiumFeatures(): array
    {
        $stored = json_decode((string) Setting::get('subscriptions.features', '[]'), true);

        return collect(is_array($stored) ? $stored : [])
            ->filter(fn ($required, $feature) => $required && isset(self::FEATURES[$feature]))
            ->all();
    }

    public static function requiresSubscription(string $feature): bool
    {
        return self::enabled() && (self::premiumFeatures()[$feature] ?? false);
    }

    /** The one call sites use: may this user use this feature right now? */
    public static function allows(?User $user, string $feature): bool
    {
        if (!self::requiresSubscription($feature)) {
            return true;
        }

        return $user !== null && $user->hasActiveSubscription();
    }

    /**
     * Guard an endpoint: aborts with 402 and a machine-readable payload the
     * SPA turns into an upgrade prompt.
     */
    public static function authorize(?User $user, string $feature): void
    {
        if (self::allows($user, $feature)) {
            return;
        }

        abort(response()->json([
            'success' => false,
            'code'    => 'subscription_required',
            'feature' => $feature,
            'message' => 'This feature is part of Unjamm Plus. Upgrade to continue.',
            'upgrade_url' => '/flight-disputes/plus',
        ], 402));
    }

    /** Feature map for the SPA: which of the user's features are locked. */
    public static function lockedFor(?User $user): array
    {
        if (!self::enabled() || ($user !== null && $user->hasActiveSubscription())) {
            return [];
        }

        return array_keys(self::premiumFeatures());
    }
}
