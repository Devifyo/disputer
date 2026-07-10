<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Terminal / distance / progress facts from FlightAware, for the
        // live "where is the plane" display.
        Schema::table('trips', function (Blueprint $table) {
            $table->string('origin_terminal', 8)->nullable()->after('origin_gate');
            $table->string('destination_terminal', 8)->nullable()->after('destination_gate');
            $table->unsignedInteger('route_distance_miles')->nullable();
            $table->unsignedTinyInteger('progress_percent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['origin_terminal', 'destination_terminal', 'route_distance_miles', 'progress_percent']);
        });
    }
};
