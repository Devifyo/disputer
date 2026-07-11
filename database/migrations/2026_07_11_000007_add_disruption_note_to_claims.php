<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The passenger's own description - mandatory when the disruption
        // type is "other", since there's nothing else to judge the case on.
        Schema::table('claims', function (Blueprint $table) {
            $table->text('disruption_note')->nullable()->after('disruption_type');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn('disruption_note');
        });
    }
};
