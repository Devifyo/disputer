<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Expenses are fee-free by default; an admin may charge a fee on
            // them explicitly - the percent lives here for transparency.
            $table->decimal('expense_fee_percent', 5, 2)->default(0)->after('expenses_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('expense_fee_percent'));
    }
};
