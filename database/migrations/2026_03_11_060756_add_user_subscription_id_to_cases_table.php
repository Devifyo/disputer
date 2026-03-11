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
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('user_subscription_id')
              ->nullable()
              ->after('user_id')
              ->constrained('user_subscriptions')
              ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
           $table->dropForeign(['user_subscription_id']);
            $table->dropColumn('user_subscription_id');
        });
    }
};
