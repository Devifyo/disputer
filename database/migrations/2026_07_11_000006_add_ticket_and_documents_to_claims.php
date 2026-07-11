<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            // Fare per person - needed for fare-based compensation
            // (US DOT denied boarding, EU downgrade percentages).
            $table->decimal('ticket_price', 10, 2)->nullable();
            $table->string('ticket_currency', 3)->nullable();
            // Ticket + supporting documents uploaded with the claim: [{name, path}]
            $table->json('documents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn(['ticket_price', 'ticket_currency', 'documents']);
        });
    }
};
