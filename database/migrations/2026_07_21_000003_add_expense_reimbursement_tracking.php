<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_expenses', function (Blueprint $table) {
            // What the airline actually paid back for this receipt, recorded
            // by an admin. The approved amount is what was claimed.
            $table->decimal('reimbursed_amount', 10, 2)->nullable()->after('admin_note');
            $table->timestamp('reimbursed_at')->nullable()->after('reimbursed_amount');
        });
    }

    public function down(): void
    {
        Schema::table('claim_expenses', function (Blueprint $table) {
            $table->dropColumn(['reimbursed_amount', 'reimbursed_at']);
        });
    }
};
