<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the flight was cancelled, tracking data can't know how late the
        // rebooked passenger finally arrived - the user supplies it.
        Schema::table('claims', function (Blueprint $table) {
            $table->integer('reported_arrival_delay_minutes')->nullable()->after('flight_arrival_delay_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn('reported_arrival_delay_minutes');
        });
    }
};
