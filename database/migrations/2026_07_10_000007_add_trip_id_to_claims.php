<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Claims started from an eligible monitored trip keep a link to it.
        Schema::table('claims', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable()->after('itinerary_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trip_id');
        });
    }
};
