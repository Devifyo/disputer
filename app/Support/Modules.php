<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Feature modules an admin can switch off from Settings. A disabled module
 * disappears from BOTH portals: its nav links hide and its routes refuse -
 * hiding a link alone is not access control.
 *
 * The list mirrors the admin navigation, one toggle per page. Everything
 * defaults to ON except the retired customer case pages (default false),
 * and the data is never touched: switching a module back on restores it
 * exactly as it was.
 */
class Modules
{
    public const TRIP_REVIEWS    = 'trip_reviews';
    public const TRIPS           = 'trips';
    public const CLAIMS          = 'claims';
    public const PASSENGERS      = 'passengers';
    public const LIFECYCLE       = 'lifecycle';
    public const AIRLINES        = 'airlines';
    public const CLAIM_TEMPLATES = 'claim_templates';
    public const SUBSCRIPTIONS   = 'subscriptions';
    public const PAYMENTS        = 'payments';
    public const EMAIL_TEMPLATES = 'email_templates';
    public const SUCCESS_STORIES = 'success_stories';
    public const INSTITUTIONS    = 'institutions';
    public const CATEGORIES      = 'categories';
    public const PLANS           = 'plans';
    public const CASES           = 'cases';
    public const DOCUMENTS       = 'documents';
    public const CASE_TEMPLATES  = 'case_templates';

    /** Every toggleable module, grouped by nav heading, in nav order. */
    public const ALL = [
        self::TRIP_REVIEWS => [
            'group'       => 'Flight Claims',
            'label'       => 'Trip Reviews',
            'description' => 'The admin queue of disruption reports awaiting review.',
        ],
        self::TRIPS => [
            'group'       => 'Flight Claims',
            'label'       => 'Protected Trips',
            'description' => 'The admin Protected Trips page plus the customer Protect Your Trip pages. Scheduled monitoring pauses while off.',
        ],
        self::CLAIMS => [
            'group'       => 'Flight Claims',
            'label'       => 'Claims',
            'description' => 'The admin claims list and claim workspace plus the customer Flight Disputes pages.',
        ],
        self::PASSENGERS => [
            'group'       => 'Flight Claims',
            'label'       => 'Passengers',
            'description' => 'The admin passenger directory.',
        ],
        self::LIFECYCLE => [
            'group'       => 'Flight Claims',
            'label'       => 'Lifecycle',
            'description' => 'The admin claim lifecycle board.',
        ],
        self::AIRLINES => [
            'group'       => 'Flight Claims',
            'label'       => 'Airlines',
            'description' => 'The admin airline directory and contact addresses.',
        ],
        self::CLAIM_TEMPLATES => [
            'group'       => 'Flight Claims',
            'label'       => 'Claim Templates',
            'description' => 'The admin claim email templates. The composer falls back to AI drafting only.',
        ],
        self::SUBSCRIPTIONS => [
            'group'       => 'Flight Claims',
            'label'       => 'Subscriptions',
            'description' => 'The admin Subscriptions page. Unjamm Plus itself keeps its own master switch inside.',
        ],
        self::PAYMENTS => [
            'group'       => 'Flight Claims',
            'label'       => 'Payments',
            'description' => 'The admin Payments module. Customers keep seeing payouts already on their claims.',
        ],
        self::EMAIL_TEMPLATES => [
            'group'       => 'Other',
            'label'       => 'Templates',
            'description' => 'The admin email template library behind the customer Cases Email Templates.',
        ],
        self::SUCCESS_STORIES => [
            'group'       => 'Other',
            'label'       => 'Success Stories',
            'description' => 'The admin Success Stories manager.',
        ],
        self::INSTITUTIONS => [
            'group'       => 'Other',
            'label'       => 'All Institutes',
            'description' => 'The admin institute directory customers pick from when creating a case.',
        ],
        self::CATEGORIES => [
            'group'       => 'Other',
            'label'       => 'Categories',
            'description' => 'The admin institute categories used across cases.',
        ],
        self::PLANS => [
            'group'       => 'Other',
            'label'       => 'Plans & Pricing',
            'description' => 'The admin Plans & Pricing manager.',
        ],
        self::CASES => [
            'group'       => 'Other',
            'label'       => 'My Cases',
            'description' => 'The customer case-management pages (create and track cases). Retired, so off by default - switch on to bring them back.',
            'default'     => false,
        ],
        self::DOCUMENTS => [
            'group'       => 'Other',
            'label'       => 'Documents',
            'description' => 'The customer Documents page for case evidence. Retired, so off by default.',
            'default'     => false,
        ],
        self::CASE_TEMPLATES => [
            'group'       => 'Other',
            'label'       => 'Cases Email Templates',
            'description' => 'The customer letter template browser (subscription-gated). Retired, so off by default.',
            'default'     => false,
        ],
    ];

    /** @var array<string, bool> one settings read per module per request */
    private static array $resolved = [];

    public static function enabled(string $module): bool
    {
        $default = (self::ALL[$module]['default'] ?? true) ? '1' : '0';

        return self::$resolved[$module] ??= (string) Setting::get("modules.{$module}", $default) === '1';
    }

    public static function set(string $module, bool $on): void
    {
        Setting::set("modules.{$module}", $on ? 1 : 0);
        self::$resolved[$module] = $on;
    }

    /** Reset the per-request cache - tests share one process, requests don't. */
    public static function flush(): void
    {
        self::$resolved = [];
    }

    /** @return array<string, array<string, array>> modules keyed by their Settings heading */
    public static function grouped(): array
    {
        return collect(self::ALL)->groupBy('group', true)->map->all()->all();
    }

    /** @return array<string, bool> current state of every module */
    public static function states(): array
    {
        return collect(self::ALL)
            ->map(fn ($definition, $key) => self::enabled($key))
            ->all();
    }
}
