<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            // Which of the customer's saved bank accounts this payout targets
            // (admin-selected; falls back to currency match / email request).
            $table->foreignId('user_payout_account_id')->nullable()
                ->after('recipient_iban')->constrained('user_payout_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_payout_account_id');
        });
    }
};
