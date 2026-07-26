<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'monthly_price', 'annual_price', 'currency',
        'trial_days', 'sort', 'is_active',
        'stripe_product_id', 'stripe_monthly_price_id', 'stripe_annual_price_id', 'perks',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'annual_price'  => 'decimal:2',
        'trial_days'    => 'integer',
        'sort'          => 'integer',
        'is_active'     => 'boolean',
        'perks'         => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function stripePriceId(string $interval): ?string
    {
        return $interval === 'annual' ? $this->stripe_annual_price_id : $this->stripe_monthly_price_id;
    }

    public function price(string $interval): ?float
    {
        $price = $interval === 'annual' ? $this->annual_price : $this->monthly_price;

        return $price === null ? null : (float) $price;
    }

    /** "EUR 4.99 / month" style label. */
    public function priceLabel(string $interval): ?string
    {
        $price = $this->price($interval);

        return $price === null ? null : sprintf(
            '%s %s / %s', $this->currency, number_format($price, 2), $interval === 'annual' ? 'year' : 'month'
        );
    }
}
