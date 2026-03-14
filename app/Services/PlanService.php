<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Exception;

class PlanService
{
    protected $stripe;

    public function __construct()
    {
        $secret = config('services.stripe.secret');
        if ($secret) {
            $this->stripe = new StripeClient($secret);
        }
    }

    /**
     * Create a new plan locally and in Stripe
     */
    public function createPlan(array $data): Plan
    {
        $stripePriceId = null;

        if ($this->stripe) {
            // 1. Create the Product in Stripe
            $product = $this->stripe->products->create([
                'name' => $data['name'],
                'description' => 'Unjamm ' . $data['name'] . ' Plan',
                'active' => $data['is_active'] ?? true,
            ]);

            // 2. Create the Price in Stripe
            $priceParams = [
                'product' => $product->id,
                'unit_amount' => (int) ($data['price'] * 100), // Convert to cents
                'currency' => strtolower($data['currency'] ?? 'usd'),
                'tax_behavior' => 'exclusive',
            ];

            if ($data['type'] === 'recurring_yearly') {
                $priceParams['recurring'] = ['interval' => 'year'];
            }

            $price = $this->stripe->prices->create($priceParams);
            $stripePriceId = $price->id;
        }

        // 3. Save to local database
        return Plan::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['slug']),
            'type' => $data['type'],
            'stripe_mode' =>$data['stripe_mode'],
            'case_limit' => $data['case_limit'] ?? null,
            'price' => $data['price'],
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'payment_gateway_id' => $stripePriceId,
            'features' => $data['features'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update a plan. If the price changes, it safely creates a new Stripe price
     * because Stripe does not allow modifying existing price amounts.
     */
    public function updatePlan(Plan $plan, array $data): Plan
    {
        $stripePriceId = $plan->payment_gateway_id;

        if ($this->stripe && $stripePriceId) {
            try {
                $oldPrice = $this->stripe->prices->retrieve($stripePriceId);
                $productId = $oldPrice->product;

                // Update product name and active status in Stripe
                $this->stripe->products->update($productId, [
                    'name' => $data['name'],
                    'active' => $data['is_active'] ?? true,
                ]);

                // If price, currency, or type changed, we MUST create a new price in Stripe
                if (
                    $plan->price != $data['price'] || 
                    strcasecmp($plan->currency, $data['currency']) !== 0 || 
                    $plan->type !== $data['type']
                ) {
                    $priceParams = [
                        'product' => $productId,
                        'unit_amount' => (int) ($data['price'] * 100),
                        'currency' => strtolower($data['currency']),
                        'tax_behavior' => 'exclusive',
                    ];

                    if ($data['type'] === 'recurring_yearly') {
                        $priceParams['recurring'] = ['interval' => 'year'];
                    }

                    $newPrice = $this->stripe->prices->create($priceParams);
                    $stripePriceId = $newPrice->id;

                    // Archive the old price so it can't be used for new checkouts
                    $this->stripe->prices->update($plan->payment_gateway_id, ['active' => false]);
                }
            } catch (Exception $e) {
                // Log exception in production
            }
        }

        // Update local database
        $plan->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['slug']),
            'type' => $data['type'],
            'case_limit' => $data['case_limit'] ?? null,
            'price' => $data['price'],
            'currency' => strtoupper($data['currency']),
            'payment_gateway_id' => $stripePriceId,
            'features' => $data['features'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $plan;
    }

    /**
     * Delete a plan locally and archive it in Stripe
     */
    public function deletePlan(Plan $plan): void
    {
        if ($this->stripe && $plan->payment_gateway_id) {
            try {
                // Stripe doesn't allow hard deleting products/prices if they have transactions.
                // We archive them instead by setting active to false.
                $price = $this->stripe->prices->retrieve($plan->payment_gateway_id);
                $this->stripe->prices->update($price->id, ['active' => false]);
                $this->stripe->products->update($price->product, ['active' => false]);
            } catch (Exception $e) {
                // Ignore if not found in Stripe
            }
        }

        $plan->delete();
    }
}