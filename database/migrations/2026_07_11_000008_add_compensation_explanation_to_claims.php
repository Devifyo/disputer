<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plain-language bullets explaining how the compensation amount was
        // derived - shown to the customer with the estimate.
        Schema::table('claims', function (Blueprint $table) {
            $table->json('compensation_explanation')->nullable()->after('compensation_basis');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn('compensation_explanation');
        });
    }
};
