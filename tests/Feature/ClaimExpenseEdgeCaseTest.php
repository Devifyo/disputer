<?php

namespace Tests\Feature;

use App\Livewire\Admin\FlightClaims\ClaimDetail;
use App\Mail\AirlineClaimMail;
use App\Models\Claim;
use App\Models\ClaimDraft;
use App\Models\ClaimExpense;
use App\Models\User;
use App\Services\Claims\ClaimLetterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Expense receipts: validation, access control, money edge cases and the
 * outbound integration - everything that decides whether a receipt can be
 * trusted, claimed, or seen by the wrong person.
 */
class ClaimExpenseEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        Role::findOrCreate('admin');
        Role::findOrCreate('user');
        config(['services.gemini.api_key' => null]);

        $this->customer = User::factory()->create();
        $this->customer->assignRole('user');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function claim(?User $owner = null): Claim
    {
        return Claim::create([
            'user_id'                => ($owner ?? $this->customer)->id,
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

    private function uploadReceipt(Claim $claim, array $payload, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->customer)
            ->postJson(route('user.itineraries.api.claims.expenses.store', encrypt_id($claim->id)), $payload);
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'receipt'  => UploadedFile::fake()->create('hotel.pdf', 100, 'application/pdf'),
            'category' => 'hotel',
            'amount'   => 180,
            'currency' => 'CAD',
        ], $overrides);
    }

    private function receipt(Claim $claim, array $attributes = []): ClaimExpense
    {
        return $claim->expenses()->create(array_merge([
            'uploaded_by'       => $this->customer->id,
            'category'          => 'hotel',
            'amount'            => 180,
            'currency'          => 'CAD',
            'file_path'         => 'claims/x/receipt.pdf',
            'original_filename' => 'receipt.pdf',
            'status'            => ClaimExpense::STATUS_APPROVED,
        ], $attributes));
    }

    // ── Validation ──────────────────────────────────────────

    public function test_receipt_file_is_required(): void
    {
        $this->uploadReceipt($this->claim(), ['category' => 'hotel'])->assertJsonValidationErrors('receipt');
    }

    public function test_executables_and_oversized_files_are_refused(): void
    {
        $claim = $this->claim();

        $this->uploadReceipt($claim, $this->valid(['receipt' => UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload')]))
            ->assertJsonValidationErrors('receipt');

        // 12MB ceiling.
        $this->uploadReceipt($claim, $this->valid(['receipt' => UploadedFile::fake()->create('huge.pdf', 13000, 'application/pdf')]))
            ->assertJsonValidationErrors('receipt');

        $this->assertSame(0, $claim->expenses()->count());
    }

    public function test_category_must_be_one_we_offer(): void
    {
        $this->uploadReceipt($this->claim(), $this->valid(['category' => 'bribes']))->assertJsonValidationErrors('category');
    }

    public function test_amounts_and_dates_are_sanity_checked(): void
    {
        $claim = $this->claim();

        $this->uploadReceipt($claim, $this->valid(['amount' => -50]))->assertJsonValidationErrors('amount');
        $this->uploadReceipt($claim, $this->valid(['expense_date' => now()->addWeek()->toDateString()]))->assertJsonValidationErrors('expense_date');
        $this->uploadReceipt($claim, $this->valid(['currency' => 'CANADIAN']))->assertJsonValidationErrors('currency');
    }

    public function test_receipt_without_an_amount_is_accepted_but_not_totalled(): void
    {
        $claim = $this->claim();

        // A passenger who does not know the amount can still submit evidence.
        $this->uploadReceipt($claim, ['receipt' => UploadedFile::fake()->image('meal.jpg'), 'category' => 'meal'])->assertOk();

        $claim->expenses()->first()->forceFill(['status' => ClaimExpense::STATUS_APPROVED])->save();
        $claim->refresh()->load('expenses');

        $this->assertSame([], $claim->approvedExpenseTotals());
        $this->assertSame(1, $claim->expenses()->count());
    }

    public function test_upload_limit_is_enforced(): void
    {
        $claim = $this->claim();

        for ($i = 0; $i < 30; $i++) {
            $this->receipt($claim, ['status' => ClaimExpense::STATUS_PENDING]);
        }

        $this->uploadReceipt($claim, $this->valid())->assertStatus(422);
        $this->assertSame(30, $claim->expenses()->count());
    }

    // ── Access control ──────────────────────────────────────

    public function test_guests_cannot_reach_expense_endpoints(): void
    {
        $claim   = $this->claim();
        $expense = $this->receipt($claim);

        $this->postJson(route('user.itineraries.api.claims.expenses.store', encrypt_id($claim->id)), $this->valid())
            ->assertUnauthorized();
        $this->getJson(route('user.itineraries.api.claims.expense', ['claim' => encrypt_id($claim->id), 'expense' => $expense->id]))
            ->assertUnauthorized();
    }

    public function test_a_customer_cannot_upload_to_or_delete_from_another_claim(): void
    {
        $intruder = User::factory()->create();
        $intruder->assignRole('user');

        $claim   = $this->claim();
        $expense = $this->receipt($claim, ['status' => ClaimExpense::STATUS_PENDING]);

        $this->uploadReceipt($claim, $this->valid(), $intruder)->assertForbidden();
        $this->actingAs($intruder)
            ->deleteJson(route('user.itineraries.api.claims.expenses.destroy', ['claim' => encrypt_id($claim->id), 'expense' => $expense->id]))
            ->assertForbidden();

        $this->assertSame(1, $claim->expenses()->count());
    }

    public function test_a_receipt_from_another_claim_cannot_be_read_through_this_one(): void
    {
        $mine    = $this->claim();
        $someone = User::factory()->create();
        $someone->assignRole('user');
        $theirs  = $this->claim($someone);
        $foreign = $this->receipt($theirs);

        // Own claim id + someone else's expense id.
        $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.claims.expense', ['claim' => encrypt_id($mine->id), 'expense' => $foreign->id]))
            ->assertNotFound();
    }

    public function test_pending_receipts_are_not_downloadable_through_the_admin_route(): void
    {
        $claim   = $this->claim();
        $pending = $this->receipt($claim, ['status' => ClaimExpense::STATUS_PENDING]);

        $this->actingAs($this->admin)
            ->get(route('admin.flight-claims.claims.document', ['claim' => $claim, 'key' => "expense-{$pending->id}"]))
            ->assertNotFound();
    }

    // ── Review lifecycle ────────────────────────────────────

    public function test_reversing_an_approval_pulls_the_receipt_out_of_the_attachment_set(): void
    {
        $claim   = $this->claim();
        $expense = $this->receipt($claim, ['status' => ClaimExpense::STATUS_PENDING]);

        $component = Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->call('reviewExpense', $expense->id, ClaimExpense::STATUS_APPROVED);

        $this->assertContains("expense-{$expense->id}", $component->get('attached'));

        // Second thoughts: reject it after approving.
        $component->set("expenseReason.{$expense->id}", 'Duplicate of the hotel receipt')
            ->call('reviewExpense', $expense->id, ClaimExpense::STATUS_REJECTED);

        $this->assertNotContains("expense-{$expense->id}", $component->get('attached'));
        $this->assertSame(ClaimExpense::STATUS_REJECTED, $expense->fresh()->status);
        $this->assertNull($claim->fresh()->documentPath("expense-{$expense->id}"));
    }

    public function test_rejection_reason_is_length_checked(): void
    {
        $claim   = $this->claim();
        $expense = $this->receipt($claim, ['status' => ClaimExpense::STATUS_PENDING]);

        Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->set("expenseReason.{$expense->id}", 'no')
            ->call('reviewExpense', $expense->id, ClaimExpense::STATUS_REJECTED)
            ->assertHasErrors("expenseReason.{$expense->id}");

        $this->assertSame(ClaimExpense::STATUS_PENDING, $expense->fresh()->status);
    }

    public function test_reimbursement_must_be_a_sane_number(): void
    {
        $claim   = $this->claim();
        $expense = $this->receipt($claim);

        Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->set("expensePaid.{$expense->id}", 'lots')
            ->call('recordReimbursement', $expense->id)
            ->assertHasErrors("expensePaid.{$expense->id}");

        $this->assertNull($expense->fresh()->reimbursed_amount);
    }

    public function test_partial_reimbursement_is_recorded_faithfully(): void
    {
        $claim = $this->claim();
        $hotel = $this->receipt($claim, ['amount' => 180]);
        $meal  = $this->receipt($claim, ['category' => 'meal', 'amount' => 35]);

        Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->set("expensePaid.{$hotel->id}", 120)
            ->call('recordReimbursement', $hotel->id)
            ->set("expensePaid.{$meal->id}", 0)
            ->call('recordReimbursement', $meal->id);

        $claim->refresh()->load('expenses');

        $this->assertSame(['CAD' => 215.0], $claim->approvedExpenseTotals());
        $this->assertSame(['CAD' => 120.0], $claim->reimbursedExpenseTotals());
    }

    // ── Money ───────────────────────────────────────────────

    public function test_mixed_currencies_are_totalled_separately_not_added_up(): void
    {
        $claim = $this->claim();
        $this->receipt($claim, ['amount' => 180, 'currency' => 'CAD']);
        $this->receipt($claim, ['amount' => 50, 'currency' => 'EUR', 'category' => 'meal']);
        $this->receipt($claim, ['amount' => 20, 'currency' => 'EUR', 'category' => 'taxi']);

        $claim->refresh()->load('expenses');

        $this->assertSame(['CAD' => 180.0, 'EUR' => 70.0], $claim->approvedExpenseTotals());
        $this->assertSame('CAD 180.00 + EUR 70.00', Claim::formatTotals($claim->approvedExpenseTotals()));
    }

    public function test_currencyless_receipt_falls_back_to_the_claim_currency(): void
    {
        $claim = $this->claim();
        $this->receipt($claim, ['amount' => 60, 'currency' => null]);

        $this->assertSame(['CAD' => 60.0], $claim->refresh()->load('expenses')->approvedExpenseTotals());
    }

    public function test_expense_reimbursement_is_excluded_from_the_success_fee_base(): void
    {
        $claim = $this->claim();
        $this->receipt($claim, ['amount' => 500]);
        $claim->refresh()->load('expenses');

        $component = Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim]);

        // The fee base is compensation only (CAD 400), never compensation +
        // the CAD 500 of receipts.
        $this->assertSame(400.0, $component->viewData('gross'));
        $this->assertSame(['CAD' => 500.0], $claim->approvedExpenseTotals());
    }

    // ── Outbound integration ────────────────────────────────

    public function test_approved_receipt_is_physically_attached_to_the_airline_email(): void
    {
        $claim = $this->claim();
        Storage::disk('local')->put('claims/x/hotel-receipt.pdf', '%PDF-1.4 receipt');
        $expense = $this->receipt($claim, ['file_path' => 'claims/x/hotel-receipt.pdf', 'original_filename' => 'hotel-receipt.pdf']);

        Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->set('to', 'claims@aircanada.ca')
            ->set('subject', 'Compensation claim - AC1540')
            ->set('body', str_repeat('Formal demand for compensation. ', 10))
            ->set('attached', ["expense-{$expense->id}"])
            ->call('send')
            ->assertHasNoErrors();

        Mail::assertSent(AirlineClaimMail::class, function (AirlineClaimMail $mail) {
            return count($mail->files) === 1
                && str_contains($mail->files[0]['name'], 'Receipt')
                && $mail->files[0]['path'] === 'claims/x/hotel-receipt.pdf';
        });
    }

    public function test_attachment_filenames_describe_the_receipt_without_mangling_the_amount(): void
    {
        $claim = $this->claim();
        Storage::disk('local')->put('claims/x/img_4821.pdf', '%PDF-1.4');

        // The customer's own filename says nothing to an airline.
        $expense = $this->receipt($claim, [
            'file_path' => 'claims/x/img_4821.pdf', 'original_filename' => 'IMG_4821.pdf',
            'amount' => 180, 'currency' => 'CAD',
        ]);

        Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->set('to', 'claims@aircanada.ca')
            ->set('subject', 'Compensation claim - AC1540')
            ->set('body', str_repeat('Formal demand for compensation. ', 10))
            ->set('attached', ["expense-{$expense->id}"])
            ->call('send');

        Mail::assertSent(AirlineClaimMail::class, function (AirlineClaimMail $mail) {
            $name = $mail->files[0]['name'] ?? '';

            // CAD 180.00 must not become "18000" - slugging the amount would
            // tell the airline the receipt is worth a hundred times more.
            return str_contains($name, 'Receipt-hotel-accommodation')
                && str_contains($name, 'CAD180.00')
                && !str_contains($name, '18000')
                && !str_contains($name, 'IMG_4821');
        });
    }

    public function test_power_of_attorney_filename_names_the_passenger(): void
    {
        $claim = $this->claim();
        Storage::disk('local')->put('claims/x/poa.pdf', '%PDF-1.4');
        $signer = $claim->signers()->create([
            'name' => 'Tenzin Hagyal', 'email' => 'tenzin@example.com',
            'status' => 'signed', 'poa_path' => 'claims/x/poa.pdf',
        ]);

        Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->set('to', 'claims@aircanada.ca')
            ->set('subject', 'Compensation claim - AC1540')
            ->set('body', str_repeat('Formal demand for compensation. ', 10))
            ->set('attached', ["poa-{$signer->id}"])
            ->call('send');

        // signs_for is empty here - the signer's own name must stand in
        // rather than the generic "passenger".
        Mail::assertSent(AirlineClaimMail::class, fn (AirlineClaimMail $mail) =>
            ($mail->files[0]['name'] ?? '') === 'Power-of-Attorney-tenzin-hagyal.pdf');
    }

    public function test_rejected_receipt_cannot_be_smuggled_into_an_email(): void
    {
        $claim    = $this->claim();
        Storage::disk('local')->put('claims/x/taxi.pdf', '%PDF-1.4');
        $rejected = $this->receipt($claim, [
            'status' => ClaimExpense::STATUS_REJECTED, 'file_path' => 'claims/x/taxi.pdf', 'review_reason' => 'Unreadable',
        ]);

        Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim])
            ->set('to', 'claims@aircanada.ca')
            ->set('subject', 'Compensation claim - AC1540')
            ->set('body', str_repeat('Formal demand for compensation. ', 10))
            ->set('attached', ["expense-{$rejected->id}"])
            ->call('send');

        // The key resolves to nothing, so no file goes out.
        Mail::assertSent(AirlineClaimMail::class, fn (AirlineClaimMail $mail) => $mail->files === []);
    }

    public function test_follow_up_and_regulator_drafts_also_carry_the_expense_demand(): void
    {
        $claim = $this->claim();
        $this->receipt($claim, ['amount' => 180]);
        $claim->refresh()->load('expenses');

        // A follow-up needs an initial claim draft on file first.
        $claim->drafts()->create([
            'type' => ClaimDraft::TYPE_CLAIM, 'version' => 1,
            'subject' => 'Initial claim', 'body' => 'Initial claim body', 'generated_by' => 'template',
        ]);

        $letters   = app(ClaimLetterService::class);
        $regulator = $letters->generate($claim, ClaimDraft::TYPE_REGULATOR);

        $this->assertStringContainsString('CAD 400.00', $regulator['body']);

        $facts = $letters->generate($claim, ClaimDraft::TYPE_CLAIM);
        $this->assertStringContainsString('CAD 180.00', $facts['body']);
    }

    public function test_deleting_a_pending_receipt_removes_the_stored_file(): void
    {
        $claim = $this->claim();
        $this->uploadReceipt($claim, $this->valid())->assertOk();

        $expense = $claim->expenses()->first();
        Storage::disk('local')->assertExists($expense->file_path);

        $this->actingAs($this->customer)
            ->deleteJson(route('user.itineraries.api.claims.expenses.destroy', ['claim' => encrypt_id($claim->id), 'expense' => $expense->id]))
            ->assertOk();

        Storage::disk('local')->assertMissing($expense->file_path);
    }

    public function test_receipts_are_removed_with_their_claim(): void
    {
        $claim = $this->claim();
        $this->receipt($claim);

        $claim->forceDelete();

        $this->assertSame(0, ClaimExpense::count());
    }
}
