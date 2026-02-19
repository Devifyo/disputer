<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('institutions_tables', function (Blueprint $table) {
            Schema::table('institution_categories', function (Blueprint $table) {
                $table->softDeletes();
            });

            // Add deleted_at to Institutions
            Schema::table('institutions', function (Blueprint $table) {
                $table->softDeletes();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institutions_tables', function (Blueprint $table) {
            Schema::table('institution_categories', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });

            Schema::table('institutions', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        });
    }
};
