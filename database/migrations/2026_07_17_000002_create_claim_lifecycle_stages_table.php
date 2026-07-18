<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_lifecycle_stages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                 // internal name
            $table->string('name');                          // display name
            $table->text('description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->string('color')->default('slate');       // preset palette key
            $table->string('icon')->default('circle');       // lucide icon name
            $table->boolean('is_active')->default(true);
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_final')->default(false);
            $table->boolean('is_system')->default(false);    // code-hooked: key locked, cannot deactivate/delete
            $table->boolean('customer_visible')->default(true);
            $table->string('customer_label')->nullable();    // simplified label the customer sees
            $table->boolean('admin_visible')->default(true);
            $table->boolean('allow_manual')->default(false); // may be entered by an admin action
            $table->boolean('allow_auto')->default(false);   // may be entered by a system event/timer
            $table->unsignedInteger('auto_delay_days')->nullable(); // timer: days in this stage before auto-move
            $table->string('auto_next_stage')->nullable();          // where the timer moves the claim
            $table->boolean('notify_admin')->default(false);
            $table->boolean('notify_customer')->default(false);
            $table->json('permissions')->nullable();         // required roles (future use)
            $table->json('next_stages')->nullable();         // allowed next stage keys
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $now = now();
        $stage = fn (array $s) => array_merge([
            'description' => null, 'is_active' => 1, 'is_initial' => 0, 'is_final' => 0, 'is_system' => 1,
            'customer_visible' => 1, 'admin_visible' => 1, 'allow_manual' => 0, 'allow_auto' => 0,
            'auto_delay_days' => null, 'auto_next_stage' => null, 'notify_admin' => 0, 'notify_customer' => 1,
            'permissions' => null, 'notes' => null, 'created_at' => $now, 'updated_at' => $now,
        ], $s, ['next_stages' => json_encode($s['next_stages'] ?? [])]);

        DB::table('claim_lifecycle_stages')->insert([
            $stage(['key' => 'draft', 'name' => 'Draft', 'sort' => 10, 'color' => 'slate', 'icon' => 'file-text',
                'is_initial' => 1, 'customer_label' => 'Claim received', 'notify_customer' => 0,
                'description' => 'Claim created - the customer may still modify it (documents, passengers, missing info).',
                'next_stages' => ['awaiting_signature']]),
            $stage(['key' => 'awaiting_signature', 'name' => 'Awaiting Signature', 'sort' => 20, 'color' => 'violet', 'icon' => 'pen-line',
                'allow_auto' => 1, 'customer_label' => 'Awaiting your signatures', 'notify_customer' => 0,
                'description' => 'Claim confirmed - waiting for POA / Assignment signatures.',
                'next_stages' => ['ready_to_file']]),
            $stage(['key' => 'ready_to_file', 'name' => 'Ready To File', 'sort' => 30, 'color' => 'emerald', 'icon' => 'clipboard-check',
                'allow_auto' => 1, 'customer_label' => 'Our team is preparing your claim for filing',
                'description' => 'All required documents signed - ready for admin review and filing.',
                'next_stages' => ['filed']]),
            $stage(['key' => 'filed', 'name' => 'Filed', 'sort' => 40, 'color' => 'blue', 'icon' => 'send',
                'allow_manual' => 1, 'customer_label' => 'Claim filed with the airline',
                'auto_delay_days' => 0, 'auto_next_stage' => 'awaiting_response',
                'description' => 'Submitted to the airline - submission date, recipient and attachments recorded.',
                'next_stages' => ['awaiting_response']]),
            $stage(['key' => 'awaiting_response', 'name' => 'Awaiting Airline Response', 'sort' => 50, 'color' => 'blue', 'icon' => 'hourglass',
                'allow_auto' => 1, 'customer_label' => "Waiting for the airline's response",
                'auto_delay_days' => 30, 'auto_next_stage' => 'awaiting_escalation', 'notify_customer' => 0,
                'description' => '30-day response timer runs; expiry moves the claim to escalation review.',
                'next_stages' => ['responded', 'awaiting_escalation']]),
            $stage(['key' => 'responded', 'name' => 'Airline Responded', 'sort' => 60, 'color' => 'amber', 'icon' => 'inbox',
                'allow_manual' => 1, 'customer_label' => 'The airline responded - under review',
                'description' => 'Airline response received and attached - admin decides the outcome.',
                'next_stages' => ['paid', 'denied']]),
            $stage(['key' => 'paid', 'name' => 'Paid', 'sort' => 70, 'color' => 'emerald', 'icon' => 'circle-dollar-sign',
                'allow_manual' => 1, 'customer_label' => 'Compensation paid by the airline',
                'description' => 'The airline accepted and compensation has been paid.',
                'next_stages' => ['closed']]),
            $stage(['key' => 'denied', 'name' => 'Denied', 'sort' => 80, 'color' => 'rose', 'icon' => 'circle-x',
                'allow_manual' => 1, 'customer_label' => 'The airline rejected the claim - we are assessing next steps',
                'description' => 'The airline rejected the claim.',
                'next_stages' => ['awaiting_escalation', 'closed']]),
            $stage(['key' => 'awaiting_escalation', 'name' => 'Awaiting Admin Escalation', 'sort' => 90, 'color' => 'amber', 'icon' => 'alert-triangle',
                'allow_manual' => 1, 'allow_auto' => 1, 'notify_admin' => 1, 'customer_label' => 'Under review by our team', 'notify_customer' => 0,
                'description' => 'Response deadline expired or a denial needs review - escalation requires an explicit admin decision.',
                'next_stages' => ['escalated', 'responded', 'closed']]),
            $stage(['key' => 'escalated', 'name' => 'Escalated To Regulator', 'sort' => 100, 'color' => 'violet', 'icon' => 'scale',
                'allow_manual' => 1, 'customer_label' => 'Escalated to the regulator',
                'description' => 'Admin escalated - the regulator complaint is prepared with AI and submitted manually.',
                'next_stages' => ['litigation', 'closed']]),
            $stage(['key' => 'litigation', 'name' => 'Litigation', 'sort' => 110, 'color' => 'rose', 'icon' => 'gavel',
                'allow_manual' => 1, 'customer_label' => 'In legal proceedings',
                'description' => 'The claim proceeds to legal action.',
                'next_stages' => ['closed']]),
            $stage(['key' => 'closed', 'name' => 'Closed', 'sort' => 120, 'color' => 'slate', 'icon' => 'archive',
                'is_final' => 1, 'allow_manual' => 1, 'customer_label' => 'Claim closed',
                'description' => 'Final state - the claim can no longer progress.',
                'next_stages' => []]),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_lifecycle_stages');
    }
};
