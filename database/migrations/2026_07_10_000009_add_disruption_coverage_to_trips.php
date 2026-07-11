<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Diversions come from FlightAware; reported disruptions are the
            // ones no flight-data API can see (denied boarding, downgrade),
            // reported by the passenger or auto-detected (missed connection).
            $table->boolean('diverted')->default(false);
            $table->string('reported_disruption', 32)->nullable();
            $table->timestamp('reported_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['diverted', 'reported_disruption', 'reported_at']);
        });
    }
};
