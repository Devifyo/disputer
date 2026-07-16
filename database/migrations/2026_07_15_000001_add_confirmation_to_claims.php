<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('compensation_explanation');
            $table->json('consents')->nullable()->after('confirmed_at');
            $table->boolean('plus_selected')->default(false)->after('consents');
            $table->timestamp('signed_at')->nullable()->after('plus_selected');
            $table->string('signature_path')->nullable()->after('signed_at');
            $table->string('poa_path')->nullable()->after('signature_path');
            $table->string('assignment_path')->nullable()->after('poa_path');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn([
                'confirmed_at', 'consents', 'plus_selected', 'signed_at',
                'signature_path', 'poa_path', 'assignment_path',
            ]);
        });
    }
};
