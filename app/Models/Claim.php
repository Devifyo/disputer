<?php

namespace App\Models;

use App\Jobs\EvaluateClaim;
use App\Services\Claims\ClaimWorkflowService;
use App\Services\Billing\SubscriptionGate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Claim extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT               = 'draft';
    public const STATUS_PENDING_ELIGIBILITY = 'pending_eligibility_review';
    public const STATUS_ELIGIBLE            = 'eligible';
    public const STATUS_REJECTED            = 'rejected';

    public const DISRUPTIONS = [
        'delayed'            => 'Delayed 3h+',
        'cancelled'          => 'Cancelled',
        'denied_boarding'    => 'Denied boarding',
        'downgrade'          => 'Downgraded',
        'missed_connection'  => 'Missed connection',
        'schedule_change'    => 'Schedule change',
        'returned_to_origin' => 'Returned to departure airport',
        'other'              => 'Other',
    ];

    protected $fillable = [
        'reference', 'number', 'user_id', 'itinerary_id', 'trip_id', 'itinerary_passenger_id', 'status',
        'departure_city', 'departure_airport', 'arrival_city', 'arrival_airport',
        'airline', 'flight_number', 'flight_date', 'disruption_type', 'disruption_note',
        'passenger_name', 'booking_reference', 'contact_email',
        'compensation_currency', 'compensation_amount', 'compensation_basis', 'submitted_at',
        'ticket_price', 'ticket_currency', 'documents', 'compensation_explanation',
        'fa_flight_id', 'flight_arrival_delay_minutes', 'reported_arrival_delay_minutes', 'did_not_travel', 'flight_cancelled', 'flight_diverted', 'flight_verified_at', 'flight_snapshot',
        'eligibility_status', 'eligibility_regulation', 'eligibility_article', 'eligibility_confidence',
        'eligibility_reason', 'eligibility_details', 'eligibility_evaluated_at', 'eligibility_decision_source',
        'confirmed_at', 'reminded_at', 'consents', 'plus_selected', 'signed_at', 'signature_path', 'poa_path', 'assignment_path',
        'airline_letter', 'workflow_state', 'filed_at', 'filing',
    ];

    protected $casts = [
        'flight_date'              => 'date',
        'submitted_at'             => 'datetime',
        'compensation_amount'      => 'decimal:2',
        'flight_cancelled'         => 'boolean',
        'did_not_travel'           => 'boolean',
        'flight_diverted'          => 'boolean',
        'flight_verified_at'       => 'datetime',
        'flight_snapshot'          => 'array',
        'ticket_price'             => 'decimal:2',
        'documents'                => 'array',
        'compensation_explanation' => 'array',
        'eligibility_details'      => 'array',
        'eligibility_evaluated_at' => 'datetime',
        'confirmed_at'             => 'datetime',
        'reminded_at'              => 'datetime',
        'consents'                 => 'array',
        'airline_letter'           => 'array',
        'filed_at'                 => 'datetime',
        'filing'                   => 'array',
        'plus_selected'            => 'boolean',
        'signed_at'                => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Claim $claim) {
            if (empty($claim->reference)) {
                $claim->reference = self::generateReference();
            }
            if (empty($claim->number)) {
                $claim->number = self::generateNumber();
            }
            if (empty($claim->submitted_at)) {
                $claim->submitted_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(ItineraryPassenger::class, 'itinerary_passenger_id');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(ClaimSigner::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(ClaimDraft::class)->latest('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ClaimAuditLog::class)->latest('id');
    }

    public function workflowTimers(): HasMany
    {
        return $this->hasMany(ClaimWorkflowTimer::class);
    }

    public function correspondence(): HasMany
    {
        return $this->hasMany(ClaimCorrespondence::class)->latest('id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ClaimExpense::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    /**
     * Approved out-of-pocket expenses, totalled per currency - what the
     * airline is asked to reimburse on top of compensation.
     *
     * Expense reimbursement is the passenger's own money back, so NO success
     * fee is ever charged on it: the fee applies to statutory compensation
     * only. Keep these totals separate from compensation everywhere.
     *
     * @return array<string, float>
     */
    public function approvedExpenseTotals(): array
    {
        return $this->expenseTotals(fn (ClaimExpense $e) => (float) $e->amount);
    }

    /**
     * What the airline actually paid back per currency, recorded by an admin.
     *
     * @return array<string, float>
     */
    public function reimbursedExpenseTotals(): array
    {
        return $this->expenseTotals(fn (ClaimExpense $e) => (float) $e->reimbursed_amount, 'reimbursed_amount');
    }

    /** @return array<string, float> */
    private function expenseTotals(callable $value, string $column = 'amount'): array
    {
        return $this->expenses
            ->where('status', ClaimExpense::STATUS_APPROVED)
            ->whereNotNull($column)
            ->groupBy(fn (ClaimExpense $e) => $e->currency ?: ($this->compensation_currency ?: 'EUR'))
            ->map(fn ($group) => round(array_sum($group->map($value)->all()), 2))
            ->all();
    }

    /** Expense totals rendered for display, e.g. "EUR 215.00". */
    public static function formatTotals(array $totals): string
    {
        return collect($totals)
            ->map(fn (float $amount, string $currency) => trim($currency . ' ' . number_format($amount, 2)))
            ->implode(' + ');
    }

    /**
     * Reply-to address that routes airline replies back to this claim: the
     * inbound-parse host is a catch-all, so the plus-token survives the trip.
     */
    public function replyAddress(): string
    {
        return 'claims+' . strtolower($this->reference) . '@' . config('services.inbound.reply_domain');
    }

    /** Storage path behind a composer attachment key (poa-*, assignment, itinerary, doc-*, extra-*, inbound-*). */
    public function documentPath(string $key): ?string
    {
        return match (true) {
            $key === 'assignment'              => $this->assignment_path,
            $key === 'itinerary'               => $this->itinerary?->file_path,
            str_starts_with($key, 'poa-')      => $this->signers()->find((int) substr($key, 4))?->poa_path,
            str_starts_with($key, 'doc-')      => $this->documents[(int) substr($key, 4)]['path'] ?? null,
            str_starts_with($key, 'extra-')    => $this->airline_letter['extra'][(int) substr($key, 6)]['path'] ?? null,
            str_starts_with($key, 'inbound-')  => $this->inboundAttachmentPath($key),
            // Only approved receipts can ever leave the building.
            str_starts_with($key, 'expense-')  => $this->expenses()
                ->where('status', ClaimExpense::STATUS_APPROVED)
                ->find((int) substr($key, 8))?->file_path,
            default                            => null,
        };
    }

    /** inbound-{correspondenceId}-{index} - a file the airline sent back. */
    private function inboundAttachmentPath(string $key): ?string
    {
        [$id, $index] = array_pad(explode('-', substr($key, 8)), 2, null);

        return $this->correspondence()->find((int) $id)?->attachments[(int) $index]['path'] ?? null;
    }

    /** The workflow this claim follows: its airline's attached one, else the default. */
    public function resolvedWorkflowId(): int
    {
        return Airline::match($this->airline, $this->flight_number)?->claim_workflow_id
            ?? ClaimWorkflow::defaultId();
    }

    public function workflowStage(): ?ClaimLifecycleStage
    {
        return ClaimLifecycleStage::byKey($this->workflow_state, $this->resolvedWorkflowId());
    }

    /** All authorisations collected - the claim may be filed. */
    public function signaturesComplete(): bool
    {
        $signers = $this->relationLoaded('signers') ? $this->signers : $this->signers()->get();

        return $signers->isNotEmpty() && $signers->every(fn (ClaimSigner $s) => $s->status === ClaimSigner::STATUS_SIGNED);
    }

    /**
     * May we write to the airline yet? Our letters assert that we act under a
     * signed authorisation and attach it - so until the customer has confirmed
     * AND every signature is in, sending would be a false assertion. Claims
     * already filed stay open for follow-ups.
     *
     * @return array{0: bool, 1: ?string} [allowed, reason it is blocked]
     */
    public function canContactAirline(): array
    {
        // Ready to file means the workflow already collected every signature
        // (or an admin deliberately moved it there); past that, follow-ups and
        // escalations must keep working.
        if (in_array($this->workflow_state, ['ready_to_file', 'filed', 'awaiting_response', 'responded', 'awaiting_escalation', 'escalated', 'litigation', 'paid', 'denied', 'closed'], true)) {
            return [true, null];
        }

        if ($this->status !== self::STATUS_ELIGIBLE) {
            return [false, 'This claim has not been approved as eligible yet.'];
        }

        if (!$this->confirmed_at) {
            return [false, 'The customer has not confirmed this claim or authorised us to act. Nothing can be sent to the airline until they do.'];
        }

        if (!$this->signaturesComplete()) {
            $signers = $this->relationLoaded('signers') ? $this->signers : $this->signers()->get();
            $pending = $signers->where('status', ClaimSigner::STATUS_SIGNED)->count();

            return [false, sprintf(
                'Authorisation is not signed yet (%d of %d signatures). The claim letter states that a signed authority is attached.',
                $pending, $signers->count(),
            )];
        }

        return [true, null];
    }

    public function events(): HasMany
    {
        // Open (pending) steps always render last - they are the current state.
        return $this->hasMany(ClaimEvent::class)
            ->orderByRaw("status = 'pending'")
            ->orderBy('sort')
            ->orderBy('happened_at');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT               => 'Draft',
            self::STATUS_PENDING_ELIGIBILITY => 'Pending Eligibility Review',
            self::STATUS_ELIGIBLE            => 'Eligible for Compensation',
            self::STATUS_REJECTED            => 'Not Eligible',
            default                          => ucwords(str_replace('_', ' ', (string) $this->status)),
        };
    }

    /**
     * The one state a CUSTOMER should see, in their words - eligibility and
     * workflow blended, because "draft" means nothing to a passenger and is
     * wrong the moment the engine has ruled. Returns [label, badge classes].
     *
     * @return array{0: string, 1: string, 2: string} [label, blade classes, tone]
     */
    public function customerStage(): array
    {
        if ($this->status === self::STATUS_REJECTED) {
            return ['Not eligible', 'bg-rose-100 text-rose-700', 'danger'];
        }

        if ($this->status === self::STATUS_PENDING_ELIGIBILITY) {
            return ['In review', 'bg-amber-100 text-amber-700', 'warning'];
        }

        if ($this->status !== self::STATUS_ELIGIBLE) {
            return ['Checking eligibility', 'bg-slate-100 text-slate-500', 'neutral'];
        }

        // Money outranks the workflow - but only say "paid out" when there is
        // nothing left to pay. A claim can be settled in instalments, and one
        // payout landing does not mean the rest has.
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();
        $live     = $payments->whereNotIn('status', [Payment::STATUS_CANCELLED, Payment::STATUS_REFUNDED]);

        if ($live->isNotEmpty()) {
            $paid = $live->where('status', Payment::STATUS_PAID);

            if ($paid->count() === $live->count()) {
                return ['Paid out', 'bg-emerald-100 text-emerald-700', 'success'];
            }

            return $paid->isNotEmpty()
                ? ['Partly paid', 'bg-emerald-100 text-emerald-700', 'success']
                : ['Payout on the way', 'bg-teal-100 text-teal-700', 'progress'];
        }

        // Eligible: what is the claim waiting on?
        if (!$this->confirmed_at) {
            return ['Confirm to continue', 'bg-blue-100 text-blue-700', 'action'];
        }

        if ($this->workflow_state === 'awaiting_signature') {
            return ['Signature needed', 'bg-violet-100 text-violet-700', 'action'];
        }

        // Short by design: this is a chip in a list, not the timeline sentence.
        // The stage's own customer_label ("Our team is preparing your claim
        // for filing") stays where it has room - the claim's progress steps.
        return match ($this->workflow_state) {
            'paid'                => ['Paid', 'bg-emerald-100 text-emerald-700', 'success'],
            'denied'              => ['Airline rejected', 'bg-rose-100 text-rose-700', 'danger'],
            'closed'              => ['Closed', 'bg-slate-100 text-slate-500', 'neutral'],
            'ready_to_file'       => ['Preparing to file', 'bg-slate-100 text-slate-600', 'progress'],
            'filed'               => ['Filed with airline', 'bg-slate-100 text-slate-600', 'progress'],
            'awaiting_response'   => ['Awaiting airline', 'bg-slate-100 text-slate-600', 'progress'],
            'responded'           => ['Airline responded', 'bg-slate-100 text-slate-600', 'progress'],
            'awaiting_escalation' => ['Under review', 'bg-slate-100 text-slate-600', 'progress'],
            'escalated'           => ['Escalated', 'bg-slate-100 text-slate-600', 'progress'],
            'litigation'          => ['In legal proceedings', 'bg-slate-100 text-slate-600', 'progress'],
            default               => [
                Str::limit($this->workflowStage()?->customer_label ?: 'In progress', 22),
                'bg-slate-100 text-slate-600',
                'progress',
            ],
        };
    }

    public function getDisruptionLabelAttribute(): ?string
    {
        return $this->disruption_type ? (self::DISRUPTIONS[$this->disruption_type] ?? ucfirst($this->disruption_type)) : null;
    }

    public function recordEvent(string $label, string $status = 'done', $when = null, int $sort = 0): ClaimEvent
    {
        return $this->events()->create([
            'label'       => Str::limit($label, 250),
            'status'      => $status,
            'happened_at' => $when ?: now(),
            'sort'        => $sort,
        ]);
    }

    /**
     * Create a draft Claim for every passenger on the itinerary that does not
     * already have one, copying the flight snapshot onto the claim.
     */
    /**
     * One master claim per booking: every passenger on the itinerary is
     * covered by the same claim file, with per-passenger compensation and
     * booking totals presented on top of it.
     */
    public static function ensureForItinerary(Itinerary $itinerary): void
    {
        // Itineraries registered to protect a future trip are not disputes.
        if ($itinerary->purpose === Itinerary::PURPOSE_TRIP) {
            return;
        }

        // Every claim-creation path funnels through here (SPA upload, the
        // itinerary view, inbound email) - the subscription gate applies to
        // all of them, not just the manual funnel.
        if (!SubscriptionGate::allows($itinerary->user, 'flight_claims')) {
            return;
        }

        $itinerary->load('passengers', 'flights');

        if (static::where('itinerary_id', $itinerary->id)->exists()) {
            return;
        }

        $first = $itinerary->flights->first();
        $last  = $itinerary->flights->last();
        $lead  = $itinerary->passengers->first();

        // Skip if the same booking + flight already has a claim (e.g. the
        // same ticket re-uploaded as a different file, or a photo vs PDF).
        $duplicate = self::findDuplicate($itinerary->user_id, [
            'passenger_name'    => $lead?->full_name,
            'flight_date'       => $first?->departure_at?->toDateString(),
            'departure_airport' => $first?->departure_airport,
            'arrival_airport'   => $last?->arrival_airport,
            'booking_reference' => $itinerary->booking_reference,
        ]);
        if ($duplicate) {
            return;
        }

        $claim = static::create([
            'user_id'                => $itinerary->user_id,
            'itinerary_id'           => $itinerary->id,
            'itinerary_passenger_id' => $lead?->id,
            'status'                 => self::STATUS_DRAFT,
            'departure_airport'      => $first?->departure_airport,
            'arrival_airport'        => $last?->arrival_airport,
            'airline'                => $itinerary->primary_airline ?: $first?->airline,
            'flight_number'          => $first?->flight_number,
            'flight_date'            => $first?->departure_at?->toDateString(),
            'passenger_name'         => $lead?->full_name,
            'booking_reference'      => $itinerary->booking_reference,
        ]);

        $claim->recordEvent('Your claim case has been received', 'done', $claim->created_at);
        $claim->recordEvent('Claim under review', 'pending', $claim->created_at, 1);

        app(ClaimWorkflowService::class)->audit(
            $claim,
            'Itinerary uploaded - claim created for ' . count($itinerary->passengers) . ' passenger(s)',
            'customer'
        );

        // Verify the flight + evaluate eligibility + estimate compensation
        // (covers both the upload funnel and inbound claims@ emails).
        EvaluateClaim::dispatch($claim);
    }

    /** Everyone the claim covers - all booking passengers, or the named one. */
    public function passengerNames(): array
    {
        $names = $this->itinerary?->passengers?->pluck('full_name')->filter()->values()->all() ?: [];

        return $names ?: array_values(array_filter([$this->passenger_name]));
    }

    /**
     * Find an existing (non-deleted) claim for the same user that describes the
     * same passenger + flight. Requires passenger name, flight date and both
     * airports to match; booking reference tightens the match when present.
     * Returns null when there isn't enough data to judge.
     */
    public static function findDuplicate(int $userId, array $a): ?Claim
    {
        $name    = trim((string) ($a['passenger_name'] ?? ''));
        $date    = $a['flight_date'] ?? null;
        $depart  = strtoupper(trim((string) ($a['departure_airport'] ?? '')));
        $arrive  = strtoupper(trim((string) ($a['arrival_airport'] ?? '')));

        if ($name === '' || !$date || $depart === '' || $arrive === '') {
            return null;
        }

        $query = static::where('user_id', $userId)
            ->whereRaw('LOWER(TRIM(passenger_name)) = ?', [mb_strtolower($name)])
            ->whereDate('flight_date', $date)
            ->where('departure_airport', $depart)
            ->where('arrival_airport', $arrive);

        if (!empty($a['booking_reference'])) {
            $query->where('booking_reference', $a['booking_reference']);
        }

        return $query->first();
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'CLM-' . strtoupper(Str::random(8));
        } while (static::withTrashed()->where('reference', $ref)->exists());

        return $ref;
    }

    public static function generateNumber(): int
    {
        do {
            $n = random_int(1_000_000, 9_999_999);
        } while (static::withTrashed()->where('number', $n)->exists());

        return $n;
    }
}
