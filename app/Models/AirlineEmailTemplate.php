<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'name', 'type', 'subject', 'body',
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

    /**
     * Which airlines this letter is for. THE RULE: none attached means every
     * airline (a house template); one or more means exactly those.
     */
    public function airlines(): BelongsToMany
    {
        return $this->belongsToMany(Airline::class, 'airline_email_template_airline');
    }

    public function appliesToAll(): bool
    {
        return $this->airlines->isEmpty();
    }

    /** Who this template covers, in words. */
    public function reachLabel(): string
    {
        if ($this->appliesToAll()) {
            return 'All airlines';
        }

        $names = $this->airlines->pluck('name');

        return $names->count() <= 2
            ? $names->implode(', ')
            : $names->take(2)->implode(', ') . ' +' . ($names->count() - 2);
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

    /** Templates usable for an airline: its own, plus the house ones. */
    public function scopeForAirline(Builder $query, ?Airline $airline): Builder
    {
        return $query->where(function (Builder $q) use ($airline) {
            $q->whereDoesntHave('airlines');   // house template - fits any airline

            if ($airline) {
                $q->orWhereHas('airlines', fn (Builder $a) => $a->whereKey($airline->id));
            }
        });
    }

    /**
     * The template an admin (or the AI) should start from for this airline
     * and letter type. An airline-specific template always beats a house one,
     * then the marked default, then the most recently edited.
     */
    public static function defaultFor(?Airline $airline, string $type): ?self
    {
        return static::with('airlines')
            ->where('type', $type)
            ->active()
            ->forAirline($airline)
            ->get()
            ->sortBy([
                fn (self $t) => $t->appliesToAll() ? 1 : 0,   // specific first
                fn (self $t) => $t->is_default ? 0 : 1,
                fn (self $t) => -$t->updated_at->timestamp,
            ])
            ->first();
    }
}
