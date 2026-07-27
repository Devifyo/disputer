<?php

namespace App\Services\Claims;

use App\Models\Setting;
use App\Models\User;

/**
 * Who receives operational alerts. Configured in Admin -> Settings ->
 * Flight Claims as a list of recipients, each subscribed to the alert types
 * they care about, so an ops mailbox can take airline replies while a
 * manager only sees claims awaiting an escalation decision.
 *
 * Falls back to the admin accounts when nothing is configured, so alerts are
 * never silently dropped.
 */
class AdminAlertRecipients
{
    public const TYPE_ESCALATION    = 'escalation';
    public const TYPE_AIRLINE_REPLY = 'airline_reply';
    public const TYPE_PAYMENTS      = 'payments';

    /** Alert types a recipient can subscribe to. */
    public const TYPES = [
        self::TYPE_ESCALATION    => 'Escalation decisions needed',
        self::TYPE_AIRLINE_REPLY => 'New airline replies',
        self::TYPE_PAYMENTS      => 'Payments and payouts (received, failed, large amounts)',
    ];

    private const SETTING = 'claims.alert_recipients';

    /**
     * Recipients subscribed to one alert type (all of them when null).
     *
     * @return array<int, array{email: string, name: string}>
     */
    public static function for(?string $type = null): array
    {
        $configured = collect(self::configured())
            ->filter(fn (array $r) => $type === null || in_array($type, $r['alerts'], true))
            ->map(fn (array $r) => ['email' => $r['email'], 'name' => $r['name'] ?: 'Unjamm team'])
            ->values()
            ->all();

        if ($configured !== []) {
            return $configured;
        }

        // Nothing configured for this alert - fall back to admin accounts.
        return User::role('admin')->get()
            ->map(fn (User $admin) => ['email' => $admin->email, 'name' => $admin->name])
            ->all();
    }

    /**
     * The stored list, normalised.
     *
     * @return array<int, array{name: string, email: string, alerts: array<int, string>}>
     */
    public static function configured(): array
    {
        $raw = (string) Setting::get(self::SETTING, '');

        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        // Legacy comma-separated value: everyone gets every alert.
        if (!is_array($decoded)) {
            return collect(preg_split('/[,;\r\n]+/', $raw))
                ->map(fn ($email) => trim($email))
                ->filter()
                ->map(fn ($email) => ['name' => '', 'email' => $email, 'alerts' => array_keys(self::TYPES)])
                ->values()
                ->all();
        }

        return collect($decoded)
            ->map(fn ($row) => [
                'name'   => trim((string) ($row['name'] ?? '')),
                'email'  => trim((string) ($row['email'] ?? '')),
                'alerts' => array_values(array_intersect((array) ($row['alerts'] ?? []), array_keys(self::TYPES))),
            ])
            ->filter(fn (array $row) => $row['email'] !== '')
            ->values()
            ->all();
    }

    /** @param array<int, array{name?: string, email?: string, alerts?: array}> $recipients */
    public static function store(array $recipients): void
    {
        $clean = collect($recipients)
            ->map(fn ($row) => [
                'name'   => trim((string) ($row['name'] ?? '')),
                'email'  => strtolower(trim((string) ($row['email'] ?? ''))),
                'alerts' => array_values(array_intersect((array) ($row['alerts'] ?? []), array_keys(self::TYPES))),
            ])
            ->filter(fn (array $row) => $row['email'] !== '')
            ->unique('email')
            ->values()
            ->all();

        Setting::set(self::SETTING, $clean === [] ? '' : json_encode($clean));
    }
}
