<?php

namespace Tests\Feature;

use App\Livewire\Admin\FlightClaims\ClaimDetail;
use App\Mail\AirlineClaimMail;
use App\Models\Claim;
use App\Models\ClaimCorrespondence;
use App\Models\User;
use App\Services\Claims\ClaimCorrespondenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * One claims mailbox, two streams: outbound claim emails carry a per-claim
 * reply-to token and subject reference; inbound mail routes to its claim by
 * either - and everything else still becomes a customer ticket submission.
 */
class ClaimCorrespondenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
        Role::findOrCreate('admin');
        Role::findOrCreate('user');
        config(['services.inbound.reply_domain' => 'claims.unjamm.com']);
        config(['services.inbound.claims_display' => 'claims@unjamm.com']);
    }

    private function claim(): Claim
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return Claim::create([
            'user_id'           => $user->id,
            'status'            => Claim::STATUS_ELIGIBLE,
            'workflow_state'    => 'ready_to_file',
            'airline'           => 'Air Canada',
            'flight_number'     => 'AC1540',
            'departure_airport' => 'YYZ',
            'arrival_airport'   => 'IAD',
            'flight_date'       => '2026-07-10',
            'passenger_name'    => 'Tenzin Hagyal',
        ]);
    }

    public function test_outbound_email_carries_reply_token_and_reference_and_is_recorded(): void
    {
        $claim = $this->claim();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        app(ClaimCorrespondenceService::class)->send(
            $claim, 'claims@aircanada.ca', 'Compensation claim - AC1540', str_repeat('Claim body. ', 20), [], $admin->id
        );

        Mail::assertSent(AirlineClaimMail::class, function (AirlineClaimMail $mail) use ($claim) {
            return $mail->hasTo('claims@aircanada.ca')
                && $mail->hasFrom('claims@unjamm.com')
                && $mail->hasReplyTo('claims+' . strtolower($claim->reference) . '@claims.unjamm.com')
                && str_contains($mail->envelope()->subject, "[Ref: {$claim->reference}]");
        });

        $record = $claim->correspondence()->first();
        $this->assertSame(ClaimCorrespondence::DIRECTION_OUTBOUND, $record->direction);
        $this->assertSame('claims@aircanada.ca', $record->to_email);
        $this->assertTrue($claim->auditLogs()->where('action', 'like', 'Claim email sent%')->exists());
    }

    public function test_inbound_reply_routes_to_its_claim_by_token_and_alerts_admins(): void
    {
        $claim = $this->claim();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->postJson('/api/webhooks/sendgrid/claims-inbound', [
            'from'     => 'Air Canada Claims <claims@aircanada.ca>',
            'to'       => $claim->replyAddress(),
            'envelope' => json_encode(['to' => [$claim->replyAddress()]]),
            'subject'  => 'Re: Compensation claim - AC1540',
            'text'     => 'We have reviewed your claim and require further information.',
        ])->assertOk()->assertJsonStructure(['correspondence']);

        $record = $claim->correspondence()->first();
        $this->assertSame(ClaimCorrespondence::DIRECTION_INBOUND, $record->direction);
        $this->assertSame('reply_token', $record->matched_by);
        $this->assertSame('claims@aircanada.ca', $record->from_email);
        $this->assertTrue($claim->auditLogs()->where('via', 'airline')->where('action', 'like', 'Airline email received%')->exists());

        // No new claim or user was created for the airline's address.
        $this->assertSame(1, Claim::count());
    }

    public function test_inbound_reply_to_the_public_address_matches_by_subject_reference(): void
    {
        $claim = $this->claim();

        $this->postJson('/api/webhooks/sendgrid/claims-inbound', [
            'from'    => 'noreply@aircanada.ca',
            'to'      => 'claims@unjamm.com',
            'subject' => "RE: Compensation claim - AC1540 [Ref: {$claim->reference}]",
            'text'    => 'Your claim has been approved for payment.',
        ])->assertOk();

        $this->assertSame('subject_reference', $claim->correspondence()->first()->matched_by);
    }

    public function test_reply_display_splits_new_text_from_quoted_history(): void
    {
        $mail = new ClaimCorrespondence([
            'body' => "We are looking into this matter allow us sometime.\nWe will get back to you as soon as possible\n\nOn Sun, Jul 19, 2026 at 1:38 AM Unjamm Claims <claims@unjamm.com> wrote:\n\n> To Air Canada Claims Department, This email serves as a second firm\n> follow-up regarding claim reference CLM-N84APPBO.",
        ]);

        $this->assertSame("We are looking into this matter allow us sometime.\nWe will get back to you as soon as possible", $mail->newBody());
        $this->assertStringStartsWith('On Sun, Jul 19', $mail->quotedBody());

        // No quote markers: the whole body is the message.
        $plain = new ClaimCorrespondence(['body' => 'Claim approved for payment.']);
        $this->assertSame('Claim approved for payment.', $plain->newBody());
        $this->assertNull($plain->quotedBody());
    }

    public function test_sending_from_ready_to_file_files_the_claim_via_the_workflow(): void
    {
        $claim = $this->claim();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(ClaimDetail::class, ['claim' => $claim])
            ->set('to', 'claims@aircanada.ca')
            ->set('subject', 'Compensation claim - AC1540')
            ->set('body', str_repeat('Formal demand for compensation. ', 10))
            ->call('send');

        $claim->refresh();
        $this->assertContains($claim->workflow_state, ['filed', 'awaiting_response']);
        $this->assertSame('claims@aircanada.ca', $claim->filing['recipient']);
        Mail::assertSent(AirlineClaimMail::class);
    }
}
