<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable claim letter for one airline. Admins can send one verbatim
 * (variables substituted, no AI) or let the AI use it as the base so the
 * airline's own wording and tone survive the draft.
 */
class AirlineEmailTemplate extends Model
{
    public const TYPE_INITIAL     = 'initial_claim';
    public const TYPE_FOLLOW_UP   = 'follow_up';
    public const TYPE_ESCALATION  = 'escalation';
    public const TYPE_FINAL       = 'final_notice';
    public const TYPE_CUSTOM      = 'custom';

    public const TYPES = [
        self::TYPE_INITIAL    => 'Initial claim',
        self::TYPE_FOLLOW_UP  => 'Follow up',
        self::TYPE_ESCALATION => 'Escalation',
        self::TYPE_FINAL      => 'Final notice',
        self::TYPE_CUSTOM     => 'Custom',
    ];

    /** Which airline contact a template of each type should be addressed to. */
    public const TYPE_PURPOSE = [
        self::TYPE_INITIAL    => 'claims',
        self::TYPE_FOLLOW_UP  => 'claims',
        self::TYPE_ESCALATION => 'escalation',
        self::TYPE_FINAL      => 'legal',
        self::TYPE_CUSTOM     => 'claims',
    ];

    protected $fillable = [
        'airline_id', 'name', 'type', 'subject', 'body',
        'is_default', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Stored as 1 or NULL (see the mutator); always read as a real bool. */
    public function getIsDefaultAttribute($value): bool
    {
        return (bool) $value;
    }

    /**
     * "Not the default" is stored as NULL, never false - that is what lets
     * the unique index allow many non-defaults but only one default.
     */
    public function setIsDefaultAttribute($value): void
    {
        $this->attributes['is_default'] = $value ? 1 : null;
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /** The airline contact purpose this template should be sent to. */
    public function contactPurpose(): string
    {
        return self::TYPE_PURPOSE[$this->type] ?? 'claims';
    }

    /**
     * The template an admin (or the AI) should start from for this airline
     * and letter type: the marked default, else the most recent active one.
     */
    public static function defaultFor(?Airline $airline, string $type): ?self
    {
        if (!$airline) {
            return null;
        }

        return static::where('airline_id', $airline->id)
            ->where('type', $type)
            ->active()
            ->orderByDesc('is_default')
            ->latest('updated_at')
            ->first();
    }
}
