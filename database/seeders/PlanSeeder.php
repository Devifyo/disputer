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
            $existingPlan = Plan::where('slug', Str::slug($planData['slug']))->first();

            if (!$existingPlan) {
                // If it doesn't exist, create it locally and in Stripe!
                $planService->createPlan($planData);
                $this->command->info("Created & Synced: {$planData['name']}");
            } else {
                // Optional: If you want to force an update to existing plans on re-seeding
                // $planService->updatePlan($existingPlan, $planData);
                $this->command->info("Skipped: {$planData['name']} (Already exists)");
            }
        }
    }
}