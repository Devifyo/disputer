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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Yearly Unlimited', 'Pay Per Case', '5 Case Bundle'
            $table->string('slug')->unique();
            
            // Changed 'one_time_case' to just 'one_time' since it could be a bundle of cases now
            $table->enum('type', ['recurring_yearly', 'one_time'])->default('recurring_yearly');
            
            // NEW: How many cases this plan unlocks. Null = Unlimited.
            $table->integer('case_limit')->nullable()->comment('Null means unlimited cases. Integer represents exact number of cases allowed.');
            
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('USD');
            
            // Useful for later when you integrate Stripe, PayPal, etc.
            $table->string('payment_gateway_id')->nullable(); 
            
            // Array of features to display on the frontend pricing cards
            $table->json('features')->nullable(); 
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
