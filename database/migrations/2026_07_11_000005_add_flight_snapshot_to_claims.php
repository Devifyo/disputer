<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The flight's tracking record (times, delays, gates) captured from
        // FlightAware at verification time - shown on the claim as evidence.
        Schema::table('claims', function (Blueprint $table) {
            $table->json('flight_snapshot')->nullable()->after('flight_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn('flight_snapshot');
        });
    }
};
