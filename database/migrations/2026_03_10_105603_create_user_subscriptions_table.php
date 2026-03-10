<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
           $table->id();
            
            // Relationships
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            
            // Tracking limits (Copied from the plan at the time of purchase)
            // Null means unlimited (for yearly plans)
            $table->integer('cases_allowed')->nullable(); 
            // How many cases they have actually submitted using this subscription
            $table->integer('cases_used')->default(0); 
            
            // Status: 'active', 'exhausted' (used all cases), 'expired' (yearly ran out), 'canceled'
            $table->string('status')->default('active');
            
            // Payment Gateway tracking
            $table->string('transaction_id')->nullable();
            
            // Timeframes
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // Null for one-time bundles that never expire, or 1 year from now for yearly plans
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
