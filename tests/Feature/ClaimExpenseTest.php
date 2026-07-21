<?php

namespace Tests\Feature;

use App\Livewire\Admin\FlightClaims\ClaimDetail;
use App\Models\Claim;
use App\Models\ClaimDraft;
use App\Models\ClaimExpense;
use App\Models\User;
use App\Services\Claims\ClaimLetterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Passengers upload expense receipts; admins verify each one and decide what
 * is claimed. Only approved receipts reach a letter or an attachment set,
 * and no success fee is ever charged on expense reimbursement.
 */
class ClaimExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Role::findOrCreate('admin');
        Role::findOrCreate('user');
        config(['services.gemini.api_key' => null]); // deterministic templates

        $this->customer = User::factory()->create();
        $this->customer->assignRole('user');
    }

    private function claim(): Claim
    {
        return Claim::create([
            'user_id'                => $this->customer->id,
            'status'                 => Claim::STATUS_ELIGIBLE,
            'workflow_state'         => 'ready_to_file',
            'airline'                => 'Air Canada',
            'flight_number'          => 'AC1540',
            'departure_airport'      => 'YYZ',
            'arrival_airport'        => 'IAD',
            'flight_date'            => '2026-07-10',
            'passenger_name'         => 'Tenzin Hagyal',
            'flight_cancelled'       => true,
            'disruption_type'        => 'cancelled',
            'eligibility_regulation' => 'APPR',
            'eligibility_article'    => 'Section 19',
            'compensation_amount'    => '400.00',
            'compensation_currency'  => 'CAD',
        ]);
    }

    private function upload(Claim $claim, array $overrides = []): void
    {
        $this->actingAs($this->customer)
            ->post(route('user.itineraries.api.claims.expenses.store', encrypt_id($claim->id)), array_merge([
                'receipt'      => UploadedFile::fake()->create('hotel.pdf', 120, 'application/pdf'),
                'category'     => 'hotel',
                'amount'       => 180,
                'currency'     => 'CAD',
                'expense_date' => '2026-07-10',
                'description'  => 'One night after the cancellation',
            ], $overrides))
            ->assertOk();
    }

    public function test_customer_uploads_a_receipt_linked_to_the_claim(): void
    {
        $claim = $this->claim();
        $this->upload($claim);

        $expense = $claim->expenses()->first();
        $this->assertSame('hotel', $expense->category);
        $this->assertSame('180.00', $expense->amount);
        $this->assertSame('CAD', $expense->currency);
        $this->assertSame(ClaimExpense::STATUS_PENDING, $expense->status);
        $this->assertSame($this->customer->id, $expense->uploaded_by);
        $this->assertSame($claim->id, $expense->claim_id);
        Storage::disk('local')->assertExists($expense->file_path);
    }

    public function test_a_customer_cannot_touch_another_customers_receipts(): void
    {
        $claim = $this->claim();
        $this->upload($claim);

        $intruder = User::factory()->create();
        $intruder->assignRole('user');

        $this->actingAs($intruder)
            ->getJson(route('user.itineraries.api.claims.expense', [
                'claim' => encrypt_id($claim->id), 'expense' => $claim->expenses()->first()->id,
            ]))
            ->assertForbidden();
    }

    public function test_reviewed_receipts_can_no_longer_be_removed_by_the_customer(): void
    {
        $claim = $this->claim();
        $this->upload($claim);
        $expense = $claim->expenses()->first();

        // Pending: removable.
        $this->actingAs($this->customer)
            ->deleteJson(route('user.itineraries.api.claims.expenses.destroy', ['claim' => encrypt_id($claim->id), 'expense' => $expense->id]))
            ->assertOk();
        $this->assertSame(0, $claim->expenses()->count());

        // Approved: locked.
        $this->upload($claim);
        $approved = $claim->expenses()->first();
        $approved->forceFill(['status' => ClaimExpense::STATUS_APPROVED])->save();

        $this->actingAs($this->customer)
            ->deleteJson(route('user.itineraries.api.claims.expenses.destroy', ['claim' => encrypt_id($claim->id), 'expense' => $approved->id]))
            ->assertStatus(422);
    }

    public function test_admin_approves_and_rejects_receipts_with_a_reason(): void
    {
        $claim = $this->claim();
        $this->upload($claim);
        $this->upload($claim, ['receipt' => UploadedFile::fake()->image('taxi.jpg'), 'category' => 'taxi', 'amount' => 40]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$hotel, $taxi] = $claim->expenses()->get()->all();

        $component = Livewire::actingAs($admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->call('reviewExpense', $hotel->id, ClaimExpense::STATUS_APPROVED);

        // Rejection demands a customer-facing reason.
        $component->call('reviewExpense', $taxi->id, ClaimExpense::STATUS_REJECTED)
            ->assertHasErrors("expenseReason.{$taxi->id}")
            ->set("expenseReason.{$taxi->id}", 'Receipt unreadable')
            ->call('reviewExpense', $taxi->id, ClaimExpense::STATUS_REJECTED)
            ->assertHasNoErrors();

        $this->assertSame(ClaimExpense::STATUS_APPROVED, $hotel->fresh()->status);
        $this->assertSame('Receipt unreadable', $taxi->fresh()->review_reason);
        $this->assertSame($admin->id, $taxi->fresh()->reviewed_by);
        $this->assertTrue($claim->auditLogs()->where('action', 'like', 'Expense receipt approved%')->exists());
        $this->assertTrue($claim->auditLogs()->where('action', 'like', 'Expense receipt rejected%')->exists());
    }

    public function test_only_approved_receipts_are_attachable_and_downloadable(): void
    {
        $claim = $this->claim();
        $this->upload($claim);
        $expense = $claim->expenses()->first();

        // Pending: not an attachment, not resolvable as a document.
        $this->assertNull($claim->fresh()->documentPath("expense-{$expense->id}"));

        $expense->forceFill(['status' => ClaimExpense::STATUS_APPROVED])->save();
        $claim->refresh()->load('expenses');

        $this->assertSame($expense->file_path, $claim->documentPath("expense-{$expense->id}"));

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $keys = collect(Livewire::actingAs($admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->instance()->attachments())->pluck('key');

        $this->assertTrue($keys->contains("expense-{$expense->id}"));
    }

    public function test_approved_expenses_are_claimed_in_the_letter_and_totalled_separately(): void
    {
        $claim = $this->claim();
        $this->upload($claim);
        $this->upload($claim, ['receipt' => UploadedFile::fake()->image('meal.jpg'), 'category' => 'meal', 'amount' => 35]);
        $this->upload($claim, ['receipt' => UploadedFile::fake()->image('taxi.jpg'), 'category' => 'taxi', 'amount' => 40]);

        $expenses = $claim->expenses()->get();
        $expenses[0]->forceFill(['status' => ClaimExpense::STATUS_APPROVED])->save();
        $expenses[1]->forceFill(['status' => ClaimExpense::STATUS_APPROVED])->save();
        $expenses[2]->forceFill(['status' => ClaimExpense::STATUS_REJECTED, 'review_reason' => 'Unreadable'])->save();

        $claim->refresh()->load('expenses');

        // 180 + 35, the rejected 40 excluded.
        $this->assertSame(['CAD' => 215.0], $claim->approvedExpenseTotals());

        $draft = app(ClaimLetterService::class)->generate($claim, ClaimDraft::TYPE_CLAIM);

        $this->assertStringContainsString('out-of-pocket expenses', $draft['body']);
        $this->assertStringContainsString('CAD 215.00', $draft['body']);
        $this->assertStringContainsString('Hotel / accommodation', $draft['body']);
        // The rejected receipt is never mentioned.
        $this->assertStringNotContainsString('Taxi', $draft['body']);
    }

    public function test_letter_says_nothing_about_expenses_when_there_are_none(): void
    {
        $draft = app(ClaimLetterService::class)->generate($this->claim(), ClaimDraft::TYPE_CLAIM);

        $this->assertStringNotContainsString('out-of-pocket', $draft['body']);
        $this->assertStringNotContainsString('receipt', strtolower($draft['body']));
    }

    public function test_admin_records_what_the_airline_reimbursed(): void
    {
        $claim = $this->claim();
        $this->upload($claim);
        $expense = $claim->expenses()->first();
        $expense->forceFill(['status' => ClaimExpense::STATUS_APPROVED])->save();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->set("expensePaid.{$expense->id}", 150)
            ->call('recordReimbursement', $expense->id)
            ->assertHasNoErrors();

        $this->assertSame('150.00', $expense->fresh()->reimbursed_amount);
        $this->assertSame(['CAD' => 215.0 - 65.0], $claim->fresh()->load('expenses')->reimbursedExpenseTotals());
        $this->assertTrue($claim->auditLogs()->where('action', 'like', 'Expense reimbursement recorded%')->exists());
    }

    public function test_internal_notes_never_reach_the_customer(): void
    {
        $claim = $this->claim();
        $this->upload($claim);
        $expense = $claim->expenses()->first();
        $expense->forceFill([
            'status'        => ClaimExpense::STATUS_REJECTED,
            'review_reason' => 'Receipt unreadable',
            'admin_note'    => 'Customer has filed three duplicates - watch this one',
        ])->save();

        $payload = $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.claims.show', encrypt_id($claim->id)))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Receipt unreadable', $payload);
        $this->assertStringNotContainsString('watch this one', $payload);
        $this->assertStringNotContainsString('admin_note', $payload);
    }
}
