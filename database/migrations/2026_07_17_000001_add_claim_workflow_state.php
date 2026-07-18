<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->string('workflow_state')->default('draft')->after('status');
            $table->timestamp('filed_at')->nullable()->after('signed_at');
            $table->json('filing')->nullable()->after('filed_at'); // {recipient, email_reference, subject, attachments, notes}
        });

        // Immutable audit trail: state transitions AND key actions.
        Schema::create('claim_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('from_state')->nullable();
            $table->string('to_state')->nullable();
            $table->string('via');                                   // customer | admin | system | airline
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['claim_id']);
        });

        // Workflow timers (airline response deadline, future reminders...).
        Schema::create('claim_workflow_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->string('purpose');                               // stage_auto | ...
            $table->timestamp('due_at');
            $table->string('status')->default('pending');            // pending | completed | cancelled
            $table->timestamp('completed_at')->nullable();
            $table->json('meta')->nullable();                        // {from_stage, to_stage}
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });

        // Backfill: signed claims are ready to file; confirmed ones await signatures.
        DB::table('claims')->whereNotNull('signed_at')->update(['workflow_state' => 'ready_to_file']);
        DB::table('claims')->whereNull('signed_at')->whereNotNull('confirmed_at')->update(['workflow_state' => 'awaiting_signature']);

        // Admin alert when a claim needs an escalation decision.
        DB::table('email_templates')->updateOrInsert(['slug' => 'claim-escalation-alert'], [
            'is_active'  => 1,
            'subject'    => 'Action needed: claim [CLAIM] - [STAGE]',
            'body'       => <<<'HTML'
<p>Hi [NAME],</p>
<p>Claim <strong>[CLAIM]</strong> (flight [FLIGHT], [ROUTE]) has moved to <strong>[STAGE]</strong>.</p>
<p><strong>Why:</strong> [REASON]</p>
<p><a href="[CLAIM_URL]">Open the claim</a> to review it - escalation and legal decisions always require your explicit action.</p>
HTML,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_workflow_timers');
        Schema::dropIfExists('claim_audit_logs');
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn(['workflow_state', 'filed_at', 'filing']);
        });
        DB::table('email_templates')->where('slug', 'claim-escalation-alert')->delete();
    }
};
