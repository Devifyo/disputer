<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    public function run(PlanService $planService): void
    {
        // Plan::query()->delete();
        // dd('exit');
        $stripeMode = config('app.stripe_mode');
        $plans = [
            [
                'name' => 'Single Case',
                'slug' => 'single-case',
                'type' => 'one_time',
                'case_limit' => 1,
                'price' => 29.00,
                'currency' => 'USD',
                'features' => [
                    '1 Premium Dispute Letter',
                    'AI Assisted Drafting',
                    'Standard Email Support',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Yearly Unlimited',
                'slug' => 'yearly-unlimited',
                'type' => 'recurring_yearly',
                'case_limit' => null,
                'price' => 199.00,
                'currency' => 'USD',
                'features' => [
                    'Unlimited Cases & Disputes',
                    'Access to all premium templates',
                    'Advanced AI Features',
                    'Priority 24/7 Support',
                ],
                'is_active' => true,
            ]
        ];

        foreach ($plans as $planData) {
            // 2. Add the mode to the data array so the PlanService can save it
            $planData['stripe_mode'] = $stripeMode;
            
            // 3. Make the slug unique per environment (e.g., 'single-case-test')
            $baseSlug = Str::slug($planData['slug']);
            $environmentSlug = $baseSlug . '-' . $stripeMode;
            $planData['slug'] = $environmentSlug;

            // 4. Check if THIS environment's version of the plan exists
            $existingPlan = Plan::where('slug', $environmentSlug)
                                ->where('stripe_mode', $stripeMode)
                                ->first();

            if (!$existingPlan) {
                // Creates locally and in Stripe!
                $planService->createPlan($planData);
                $this->command->info("Created & Synced [{$stripeMode} mode]: {$planData['name']}");
            } else {
                $this->command->info("Skipped: {$planData['name']} (Already exists in {$stripeMode} mode)");
            }
        }
    }
}