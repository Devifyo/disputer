<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Detected flight events: delay, cancellation, gate_change,
        // schedule_change, completed. Part of the monitoring history.
        Schema::create('trip_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('description');
            $table->json('data')->nullable();      // before/after values
            $table->boolean('qualifying')->default(false); // triggered eligibility review
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(['trip_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_events');
    }
};
