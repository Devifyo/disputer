<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * A named claim lifecycle. Airlines attach to exactly one workflow; every
 * airline without its own follows the default. Each workflow owns a full
 * set of lifecycle stages.
 */
class ClaimWorkflow extends Model
{
    protected $fillable = ['name', 'description', 'is_default', 'is_active'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('claim-workflow-default-id'));
        static::deleted(fn () => Cache::forget('claim-workflow-default-id'));
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ClaimLifecycleStage::class)->orderBy('sort');
    }

    public function airlines(): HasMany
    {
        return $this->hasMany(Airline::class);
    }

    public static function defaultId(): int
    {
        return (int) Cache::rememberForever('claim-workflow-default-id', fn () => static::where('is_default', true)->value('id'));
    }

    /** Duplicate this workflow's stages into a new workflow. */
    public function duplicateAs(string $name, ?string $description = null): self
    {
        $copy = static::create(['name' => $name, 'description' => $description, 'is_default' => false, 'is_active' => true]);

        foreach ($this->stages as $stage) {
            $copy->stages()->create($stage->only([
                'key', 'name', 'description', 'sort', 'color', 'icon',
                'is_active', 'is_initial', 'is_final', 'is_system',
                'customer_visible', 'customer_label', 'admin_visible',
                'allow_manual', 'allow_auto', 'auto_delay_days', 'auto_next_stage',
                'notify_admin', 'notify_customer', 'ai_action', 'airline_contact_purpose',
                'permissions', 'next_stages', 'notes',
            ]));
        }

        return $copy;
    }
}
