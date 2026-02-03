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
        Schema::create('case_timelines', function (Blueprint $table) {
            $table->id();

            // Link to the main Case
            $table->foreignId('case_id')->constrained('cases')->onDelete('cascade');

            // Event Type (e.g., 'case_created', 'email_received', 'status_change')
            $table->string('type')->index();

            // Who performed the action? (e.g., 'User', 'System', 'Chase Bank')
            $table->string('actor');

            // Human-readable summary (e.g., "Dispute initiated for $50.00")
            $table->text('description')->nullable();

            // Structured Data (JSON) for storing amounts, dates, specific refs
            $table->json('metadata')->nullable();

            // The actual time the event happened (defaults to now)
            $table->timestamp('occurred_at')->useCurrent();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_timelines');
    }
};
