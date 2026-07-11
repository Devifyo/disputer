<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Funnel answers + supporting documents attached to a passenger's
        // disruption report: {questions: [{question, answer}], documents: [{name, path}]}
        Schema::table('trips', function (Blueprint $table) {
            $table->json('report_details')->nullable()->after('reported_disruption');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('report_details');
        });
    }
};
