<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unjamm Plus membership plans - admin-managed, Stripe IDs included,
        // so new plans (Business, Enterprise...) never need a deploy.
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                 // machine name, e.g. "plus"
            $table->string('name');                          // display name, e.g. "Unjamm Plus"
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 8, 2)->nullable();
            $table->decimal('annual_price', 8, 2)->nullable();
            $table->string('currency', 3)->default('CAD');
            $table->unsignedInteger('trial_days')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_monthly_price_id')->nullable();
            $table->string('stripe_annual_price_id')->nullable();
            $table->json('perks')->nullable();               // bullet list shown on the plan card
            $table->timestamps();
        });

        // One row per Stripe subscription, synced by webhooks.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->unique();
            $table->string('stripe_price_id')->nullable();
            $table->string('interval', 10)->default('monthly');   // monthly | annual
            $table->string('status')->default('incomplete');      // trialing|active|past_due|canceled|unpaid|incomplete|incomplete_expired|paused
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('remember_token');
        });

        DB::table('subscription_plans')->insert([
            'key'           => 'plus',
            'name'          => 'Unjamm Plus',
            'description'   => 'Your claims file themselves. Jump the queue, cover the whole family, and let Unjamm submit to the airline the moment you consent.',
            'monthly_price' => 9.99,
            'annual_price'  => null,
            'currency'      => 'CAD',
            'trial_days'    => 0,
            'sort'          => 1,
            'is_active'     => true,
            'perks'         => json_encode([
                'Priority filing queue',
                'Multi-passenger / family accounts',
                'Fully automatic claim filing (after initial consent)',
            ]),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Master switch OFF by default: launch free, flip it later.
        foreach ([
            ['key' => 'subscriptions.enabled', 'value' => '0'],
            ['key' => 'subscriptions.features', 'value' => '[]'],
        ] as $row) {
            if (!DB::table('settings')->where('key', $row['key'])->exists()) {
                DB::table('settings')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('stripe_customer_id'));
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
        DB::table('settings')->whereIn('key', ['subscriptions.enabled', 'subscriptions.features'])->delete();
    }
};
