<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // After a cancellation the passenger either takes the rebooking or
        // abandons the trip for a refund - the remedies differ.
        Schema::table('claims', function (Blueprint $table) {
            $table->boolean('did_not_travel')->default(false)->after('reported_arrival_delay_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn('did_not_travel');
        });
    }
};
