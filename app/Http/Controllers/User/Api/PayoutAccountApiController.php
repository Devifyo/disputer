<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPayoutAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The customer's payout bank accounts. Full numbers go IN, only masked
 * tails ever come OUT - including to the account owner.
 */
class PayoutAccountApiController extends Controller
{
    public function index()
    {
        return response()->json(['data' => [
            'accounts' => Auth::user()->payoutAccounts()->orderByDesc('is_default')->get()->map(fn (UserPayoutAccount $account) => [
                'currency'   => $account->currency,
                'holder'     => $account->account_holder_name,
                'masked'     => $account->masked,
                'is_default' => $account->is_default,
                'saved_at'   => $account->updated_at->format('d M Y'),
            ]),
            'fields' => collect(UserPayoutAccount::TYPES)->map(fn ($spec) => $spec['fields']),
        ]]);
    }

    public function store(Request $request)
    {
        $currency = strtoupper((string) $request->input('currency'));

        // People type IBANs and sort codes with spaces and dashes - strip
        // them before validating so the rules see the bare identifier.
        foreach (['iban', 'sort_code', 'account_number', 'institution_number', 'transit_number', 'routing_number'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => preg_replace('/[\s-]+/', '', strtoupper((string) $request->input($field)))]);
            }
        }

        $rules = [
            'currency'            => ['required', Rule::in(array_keys(UserPayoutAccount::TYPES))],
            'account_holder_name' => ['required', 'string', 'max:120'],
        ];

        $rules += match ($currency) {
            'EUR' => ['iban' => ['required', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/i']],
            'GBP' => ['sort_code' => ['required', 'regex:/^\d{6}$/'], 'account_number' => ['required', 'regex:/^\d{8}$/']],
            'CAD' => ['institution_number' => ['required', 'regex:/^\d{3}$/'], 'transit_number' => ['required', 'regex:/^\d{5}$/'], 'account_number' => ['required', 'regex:/^\d{7,12}$/']],
            'USD' => ['routing_number' => ['required', 'regex:/^\d{9}$/'], 'account_number' => ['required', 'regex:/^\d{4,17}$/'],
                      'city' => ['required', 'string', 'max:80'], 'post_code' => ['required', 'string', 'max:12'], 'address' => ['required', 'string', 'max:150']],
            default => [],
        };

        $data = $request->validate($rules, [
            'iban.regex'               => 'That does not look like a valid IBAN.',
            'sort_code.regex'          => 'Sort code is the 6 digits, no dashes.',
            'account_number.regex'     => 'That account number does not look right.',
            'institution_number.regex' => 'Institution number is 3 digits.',
            'transit_number.regex'     => 'Transit number is 5 digits.',
            'routing_number.regex'     => 'Routing number is 9 digits.',
        ]);

        $details = collect($data)
            ->except(['currency', 'account_holder_name'])
            ->map(fn ($value) => preg_replace('/\s+/', '', (string) $value))
            ->all();

        $account = Auth::user()->payoutAccounts()->updateOrCreate(
            ['currency' => $currency],
            [
                'account_holder_name' => $data['account_holder_name'],
                'type'                => UserPayoutAccount::TYPES[$currency]['type'],
                'details'             => $details,
                'masked'              => UserPayoutAccount::mask($details),
            ]
        );

        // The first account (or an explicit choice) becomes the payout default.
        if ($request->boolean('make_default') || !Auth::user()->payoutAccounts()->where('is_default', true)->exists()) {
            $this->promote($account);
        }

        return $this->index();
    }

    /** The customer picks which of their accounts payouts should go to. */
    public function makeDefault(string $currency)
    {
        $account = Auth::user()->payoutAccounts()->where('currency', strtoupper($currency))->firstOrFail();
        $this->promote($account);

        return $this->index();
    }

    public function destroy(string $currency)
    {
        Auth::user()->payoutAccounts()->where('currency', strtoupper($currency))->delete();

        // Never leave the customer defaultless while accounts remain.
        if (!Auth::user()->payoutAccounts()->where('is_default', true)->exists()
            && ($next = Auth::user()->payoutAccounts()->latest('updated_at')->first())) {
            $this->promote($next);
        }

        return $this->index();
    }

    private function promote(UserPayoutAccount $account): void
    {
        Auth::user()->payoutAccounts()->whereKeyNot($account->id)->update(['is_default' => false]);
        $account->forceFill(['is_default' => true])->save();
    }
}
