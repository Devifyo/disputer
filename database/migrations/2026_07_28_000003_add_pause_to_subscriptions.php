<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Stripe's pause_collection mirrored locally, so the app can show
            // and gate on a paused membership without calling Stripe.
            $table->timestamp('paused_at')->nullable()->after('canceled_at');
            $table->timestamp('resumes_at')->nullable()->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn(['paused_at', 'resumes_at']));
    }
};
