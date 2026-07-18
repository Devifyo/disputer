<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $workflowId = DB::table('claim_workflows')->insertGetId([
            'name'        => 'Standard lifecycle',
            'description' => 'The default claim lifecycle, used by every airline without its own workflow.',
            'is_default'  => 1,
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        Schema::table('claim_lifecycle_stages', function (Blueprint $table) {
            $table->foreignId('claim_workflow_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['key']);
            $table->unique(['claim_workflow_id', 'key']);
        });

        DB::table('claim_lifecycle_stages')->update(['claim_workflow_id' => $workflowId]);

        // Escalation-stage emails go to the airline's escalation contact.
        DB::table('claim_lifecycle_stages')->where('key', 'awaiting_escalation')->update(['airline_contact_purpose' => 'escalation']);

        Schema::table('airlines', function (Blueprint $table) {
            // The one workflow this airline follows (null = the default workflow).
            $table->foreignId('claim_workflow_id')->nullable()->after('is_active')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claim_workflow_id');
        });
        Schema::table('claim_lifecycle_stages', function (Blueprint $table) {
            $table->dropUnique(['claim_workflow_id', 'key']);
            $table->dropConstrainedForeignId('claim_workflow_id');
            $table->unique(['key']);
        });
        Schema::dropIfExists('claim_workflows');
    }
};
