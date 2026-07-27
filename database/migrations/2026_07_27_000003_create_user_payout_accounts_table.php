<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a customer wants their payouts sent - one account per
        // currency. The account fields live encrypted in `details`; nothing
        // readable at rest, only masked digits ever leave the server.
        Schema::create('user_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->string('account_holder_name');
            $table->string('type', 20);              // iban | sort_code | aba | canadian
            $table->text('details');                 // encrypted JSON
            $table->string('masked');                // "····3000" for display
            $table->timestamps();
            $table->unique(['user_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payout_accounts');
    }
};
