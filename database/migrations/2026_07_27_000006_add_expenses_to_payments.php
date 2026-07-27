<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Portion of the gross that reimburses out-of-pocket expenses -
            // the success fee is charged only on the compensation part.
            $table->decimal('expenses_amount', 10, 2)->default(0)->after('gross_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('expenses_amount'));
    }
};
