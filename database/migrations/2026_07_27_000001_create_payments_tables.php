<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money the airline paid Unjamm for a claim, and how it splits.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // the passenger being paid out
            $table->string('airline')->nullable();
            $table->string('currency', 3);
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('fee_percent', 5, 2);
            $table->decimal('fee_amount', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->string('status')->default('received');   // pending|received|ready_for_payout|processing|paid|failed|cancelled|refunded
            $table->date('payment_date');
            $table->string('reference')->nullable();          // airline's payment reference
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['claim_id']);
            $table->index(['status', 'payment_date']);
        });

        // One attempt to move the net amount to the passenger (Wise or manual).
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method', 10)->default('wise');    // wise | manual
            $table->string('status')->default('draft');       // draft|processing|sent|completed|failed|cancelled
            $table->string('currency', 3);                    // what the passenger receives
            $table->decimal('amount', 12, 2);
            $table->string('source_currency', 3);
            $table->decimal('source_amount', 12, 2);
            $table->decimal('exchange_rate', 14, 6)->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_iban')->nullable();     // last-4 masked at display time
            $table->string('wise_recipient_id')->nullable();
            $table->string('wise_quote_id')->nullable();
            $table->string('wise_transfer_id')->nullable();
            $table->string('transfer_reference')->nullable(); // shows on the customer's bank statement
            $table->string('transfer_status')->nullable();    // Wise's own state string
            $table->timestamp('transferred_at')->nullable();
            $table->json('api_response')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['payment_id']);
            $table->index(['status']);
            $table->index(['wise_transfer_id']);
        });

        // Append-only financial ledger: every event, conversion and status hop.
        Schema::create('payout_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                           // payment_received|fee_deducted|payout_created|wise_transfer|conversion|completed|failed|refund|cancelled
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();                 // e.g. {rate, from, to, converted_amount, converted_at}
            $table->timestamps();
            $table->index(['payment_id', 'type']);
            $table->index(['created_at']);
        });

        // Immutable audit: who did what to money, from where, before/after.
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['payment_id']);
        });

        // Financial actions are permissioned individually - "admin" alone is
        // not enough to move money or change the fee.
        $now = now();
        foreach (['payments.view', 'payments.manage', 'payments.override_fee', 'payouts.send'] as $permission) {
            DB::table('permissions')->insertOrIgnore(['name' => $permission, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }

        $roleId = DB::table('roles')->where('name', 'admin')->value('id');
        if ($roleId) {
            $ids = DB::table('permissions')->whereIn('name', ['payments.view', 'payments.manage', 'payments.override_fee', 'payouts.send'])->pluck('id');
            foreach ($ids as $id) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
            }
        }

        // Admin alert threshold for unusually large airline payments.
        if (!DB::table('settings')->where('key', 'payments.large_payment_threshold')->exists()) {
            DB::table('settings')->insert(['key' => 'payments.large_payment_threshold', 'value' => '10000', 'created_at' => $now, 'updated_at' => $now]);
        }

        // Customer email templates (admin-editable, localisable via Templates).
        $templates = [
            'payment-received'  => ['The airline has paid your claim [CLAIM]', <<<'HTML'
<p>Hi [NAME],</p>
<p>Great news - the airline has paid compensation for claim <strong>[CLAIM]</strong>.</p>
<p style="margin:16px 0 4px;"><strong>Compensation received:</strong> [GROSS]</p>
<p style="margin:0 0 4px;"><strong>Unjamm success fee ([FEE_PERCENT]%):</strong> [FEE]</p>
<p style="font-size:20px; margin:8px 0 16px;"><strong>Your payout: [NET]</strong></p>
<p>We are preparing your payout now - you will hear from us the moment it is on its way.</p>
<p style="margin:22px 0;"><a href="[CLAIM_URL]" style="background:#0f172a; color:#ffffff; padding:12px 26px; border-radius:12px; text-decoration:none; font-weight:bold; display:inline-block;">View your claim</a></p>
HTML],
            'payout-initiated'  => ['Your payout of [NET] is on its way - claim [CLAIM]', <<<'HTML'
<p>Hi [NAME],</p>
<p>Your payout for claim <strong>[CLAIM]</strong> has been sent.</p>
<p style="font-size:20px; margin:14px 0 4px;"><strong>[NET]</strong></p>
<p style="margin:0 0 14px;">Reference: <strong>[REFERENCE]</strong> - you will see this on your bank statement. Transfers usually arrive within 1-2 business days.</p>
<p style="margin:22px 0;"><a href="[CLAIM_URL]" style="background:#0f172a; color:#ffffff; padding:12px 26px; border-radius:12px; text-decoration:none; font-weight:bold; display:inline-block;">Track your payout</a></p>
HTML],
            'payout-completed'  => ['Paid: [NET] for claim [CLAIM]', <<<'HTML'
<p>Hi [NAME],</p>
<p>Your payout of <strong>[NET]</strong> for claim <strong>[CLAIM]</strong> has completed. Reference: <strong>[REFERENCE]</strong>.</p>
<p>That closes the money side of your claim - the full breakdown ([GROSS] compensation, [FEE] success fee) stays available on your claim page.</p>
<p>Thank you for flying with Unjamm in your corner.</p>
HTML],
            'payout-failed'     => ['Action needed: your payout for claim [CLAIM]', <<<'HTML'
<p>Hi [NAME],</p>
<p>Your payout of <strong>[NET]</strong> for claim <strong>[CLAIM]</strong> could not be completed. This is usually a bank-detail mismatch.</p>
<p>Our team has been alerted and is on it - we may contact you to confirm your account details. Your money is safe with us until the transfer succeeds.</p>
HTML],
            'payment-refunded'  => ['Refund processed for claim [CLAIM]', <<<'HTML'
<p>Hi [NAME],</p>
<p>A refund of <strong>[GROSS]</strong> has been processed on claim <strong>[CLAIM]</strong>. Reference: [REFERENCE].</p>
<p>If anything about this looks unexpected, just reply to this email.</p>
HTML],
            'payment-admin-alert' => ['[SUBJECT]', <<<'HTML'
<p>Hi [NAME],</p>
<p>[BODY]</p>
<p style="margin:16px 0 6px;"><strong>Claim:</strong> [CLAIM] · <strong>Amount:</strong> [AMOUNT]</p>
<p style="margin:22px 0;"><a href="[URL]" style="background:#0f172a; color:#ffffff; padding:12px 26px; border-radius:12px; text-decoration:none; font-weight:bold; display:inline-block;">Open payments</a></p>
HTML],
        ];

        foreach ($templates as $slug => [$subject, $body]) {
            DB::table('email_templates')->updateOrInsert(['slug' => $slug], [
                'is_active' => 1, 'subject' => $subject, 'body' => $body,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
        Schema::dropIfExists('payout_transactions');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payments');
        DB::table('permissions')->whereIn('name', ['payments.view', 'payments.manage', 'payments.override_fee', 'payouts.send'])->delete();
        DB::table('email_templates')->whereIn('slug', ['payment-received', 'payout-initiated', 'payout-completed', 'payout-failed', 'payment-refunded', 'payment-admin-alert'])->delete();
        DB::table('settings')->where('key', 'payments.large_payment_threshold')->delete();
    }
};
