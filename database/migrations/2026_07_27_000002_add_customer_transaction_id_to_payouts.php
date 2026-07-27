<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            // Idempotency key sent to Wise as customerTransactionId: queue
            // jobs are at-least-once, and a re-run with the same key can
            // never create a second (double-paying) transfer.
            $table->uuid('customer_transaction_id')->nullable()->unique()->after('wise_quote_id');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', fn (Blueprint $table) => $table->dropColumn('customer_transaction_id'));
    }
};
