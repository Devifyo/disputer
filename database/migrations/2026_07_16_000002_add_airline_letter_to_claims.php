<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            // Draft of the claim email to the airline: {to, subject, body,
            // attachments, generated_at, generated_by}
            $table->json('airline_letter')->nullable()->after('assignment_path');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn('airline_letter');
        });
    }
};
