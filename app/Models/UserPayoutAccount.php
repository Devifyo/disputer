<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's payout bank account for one currency. The sensitive fields
 * are encrypted at rest; only the masked tail is ever displayed - to the
 * customer, to admins, everywhere.
 */
class UserPayoutAccount extends Model
{
    /** Wise recipient type + the fields each currency needs. */
    public const TYPES = [
        'EUR' => ['type' => 'iban',      'fields' => ['iban']],
        'GBP' => ['type' => 'sort_code', 'fields' => ['sort_code', 'account_number']],
        'CAD' => ['type' => 'canadian',  'fields' => ['institution_number', 'transit_number', 'account_number']],
        'USD' => ['type' => 'aba',       'fields' => ['routing_number', 'account_number', 'city', 'post_code', 'address']],
    ];

    protected $fillable = ['user_id', 'currency', 'account_holder_name', 'type', 'details', 'masked', 'is_default'];

    protected $casts = [
        'details'    => 'encrypted:array',
        'is_default' => 'boolean',
    ];

    /**
     * The account the customer chose for payouts: their explicit default,
     * or their only/most recent account when none is marked yet.
     */
    public static function defaultFor(User $user): ?self
    {
        return $user->payoutAccounts()
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The tail of the main account identifier, for display. */
    public static function mask(array $details): string
    {
        $identifier = $details['iban'] ?? $details['account_number'] ?? '';

        return '····' . substr(preg_replace('/\s+/', '', (string) $identifier), -4);
    }

    /** The Wise /v1/accounts payload for this account. */
    public function wiseRecipient(): array
    {
        $d = $this->details;

        $details = match ($this->type) {
            'iban'      => ['IBAN' => $d['iban']],
            'sort_code' => ['sortCode' => $d['sort_code'], 'accountNumber' => $d['account_number']],
            'canadian'  => ['institutionNumber' => $d['institution_number'], 'transitNumber' => $d['transit_number'], 'accountNumber' => $d['account_number'], 'accountType' => 'CHECKING'],
            'aba'       => ['abartn' => $d['routing_number'], 'accountNumber' => $d['account_number'], 'accountType' => 'CHECKING',
                            'address' => ['country' => 'US', 'city' => $d['city'] ?? '', 'postCode' => $d['post_code'] ?? '', 'firstLine' => $d['address'] ?? '']],
            default     => [],
        };

        return ['type' => $this->type, 'details' => ['legalType' => 'PRIVATE'] + $details];
    }
}
