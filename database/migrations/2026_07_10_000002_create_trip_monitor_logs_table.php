<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per FlightAware polling cycle (successful or not).
        Schema::create('trip_monitor_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->timestamp('polled_at');
            $table->string('trigger', 32)->default('schedule');    // schedule|manual|registration
            $table->string('flight_status', 32)->nullable();       // status at poll time
            $table->integer('departure_delay_minutes')->nullable();
            $table->integer('arrival_delay_minutes')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable(); // AeroAPI response code
            $table->string('result', 32);                           // synced|not_found|error
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'polled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_monitor_logs');
    }
};
