<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->json('passengers')->nullable()->after('passenger_name');
            $table->string('ticket_file_path')->nullable()->after('passengers');
            // Filled from the uploaded ticket (manually for now, parsed later) —
            // needed to value the claim if the flight is disrupted.
            $table->decimal('ticket_price', 10, 2)->nullable()->after('ticket_file_path');
            $table->string('ticket_currency', 3)->nullable()->after('ticket_price');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['passengers', 'ticket_file_path', 'ticket_price', 'ticket_currency']);
        });
    }
};
