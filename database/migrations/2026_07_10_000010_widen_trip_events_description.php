<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eligibility verdict descriptions can exceed VARCHAR(255).
        Schema::table('trip_events', function (Blueprint $table) {
            $table->text('description')->change();
        });
    }

    public function down(): void
    {
        Schema::table('trip_events', function (Blueprint $table) {
            $table->string('description')->change();
        });
    }
};
