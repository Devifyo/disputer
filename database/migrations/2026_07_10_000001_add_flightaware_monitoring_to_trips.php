<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // FlightAware identity
            $table->string('fa_flight_id')->nullable()->after('delay_score');   // e.g. ACA845-1752...
            $table->string('fa_ident', 16)->nullable()->after('fa_flight_id');  // e.g. AC845 / ACA845

            // Live status
            $table->string('flight_status', 32)->nullable()->after('fa_ident');      // scheduled|on_time|delayed|cancelled|completed
            $table->string('monitoring_status', 32)->default('pending')->after('flight_status'); // pending|monitoring|completed|failed
            $table->text('flight_status_text')->nullable()->after('monitoring_status'); // FlightAware human status, e.g. "On time"

            // Times reported by FlightAware (UTC)
            $table->timestamp('scheduled_departure')->nullable();
            $table->timestamp('scheduled_arrival')->nullable();
            $table->timestamp('estimated_departure')->nullable();
            $table->timestamp('estimated_arrival')->nullable();
            $table->timestamp('actual_departure')->nullable();
            $table->timestamp('actual_arrival')->nullable();

            $table->integer('departure_delay_minutes')->nullable();
            $table->integer('arrival_delay_minutes')->nullable();
            $table->string('origin_gate', 16)->nullable();
            $table->string('destination_gate', 16)->nullable();

            // Disruption / eligibility flags (Eligibility Engine comes later)
            $table->boolean('potentially_eligible')->default(false);
            $table->timestamp('disruption_notified_at')->nullable();

            // Historical route reliability (informational)
            $table->json('route_stats')->nullable();

            // Poll bookkeeping
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('next_poll_at')->nullable();

            $table->index('next_poll_at');
            $table->index('fa_flight_id');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['next_poll_at']);
            $table->dropIndex(['fa_flight_id']);
            $table->dropColumn([
                'fa_flight_id', 'fa_ident', 'flight_status', 'monitoring_status', 'flight_status_text',
                'scheduled_departure', 'scheduled_arrival', 'estimated_departure', 'estimated_arrival',
                'actual_departure', 'actual_arrival', 'departure_delay_minutes', 'arrival_delay_minutes',
                'origin_gate', 'destination_gate', 'potentially_eligible', 'disruption_notified_at',
                'route_stats', 'last_synced_at', 'next_poll_at',
            ]);
        });
    }
};
