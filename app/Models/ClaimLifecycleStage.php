<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * One configurable stage of the claim lifecycle. Admins manage stages,
 * ordering, transitions, visibility and automation from Flight Claims ->
 * Lifecycle; system stages are code-hooked and cannot be removed.
 */
class ClaimLifecycleStage extends Model
{
    /** Preset palette: stage color key -> UI classes. */
    public const COLORS = [
        'slate'   => 'bg-slate-50 text-slate-600 ring-slate-200',
        'blue'    => 'bg-blue-50 text-blue-700 ring-blue-200',
        'violet'  => 'bg-violet-50 text-violet-700 ring-violet-200',
        'amber'   => 'bg-amber-50 text-amber-700 ring-amber-200',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'rose'    => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];

    protected $fillable = [
        'claim_workflow_id', 'key', 'name', 'description', 'sort', 'color', 'icon',
        'is_active', 'is_initial', 'is_final', 'is_system',
        'customer_visible', 'customer_label', 'admin_visible',
        'allow_manual', 'allow_auto', 'auto_delay_days', 'auto_next_stage',
        'notify_admin', 'notify_customer', 'ai_action', 'airline_contact_purpose',
        'permissions', 'next_stages', 'notes',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'is_initial'       => 'boolean',
        'is_final'         => 'boolean',
        'is_system'        => 'boolean',
        'customer_visible' => 'boolean',
        'admin_visible'    => 'boolean',
        'allow_manual'     => 'boolean',
        'allow_auto'       => 'boolean',
        'notify_admin'     => 'boolean',
        'notify_customer'  => 'boolean',
        'permissions'      => 'array',
        'next_stages'      => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn (self $stage) => Cache::forget('claim-lifecycle-stages:' . $stage->claim_workflow_id));
        static::deleted(fn (self $stage) => Cache::forget('claim-lifecycle-stages:' . $stage->claim_workflow_id));
    }

    /** A workflow's stages, ordered, cached - the engine's source of truth. */
    public static function all_cached(?int $workflowId = null): Collection
    {
        $workflowId ??= ClaimWorkflow::defaultId();

        return Cache::rememberForever(
            'claim-lifecycle-stages:' . $workflowId,
            fn () => static::where('claim_workflow_id', $workflowId)->orderBy('sort')->get()
        );
    }

    public static function byKey(?string $key, ?int $workflowId = null): ?self
    {
        return static::all_cached($workflowId)->firstWhere('key', $key);
    }

    public function badgeClasses(): string
    {
        return self::COLORS[$this->color] ?? self::COLORS['slate'];
    }
}
