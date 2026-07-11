<?php

namespace Tests\Feature;

use App\Livewire\Admin\TripReviews\Index;
use App\Mail\GenericEmail;
use App\Models\EmailTemplate;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripEligibilityRejected;
use App\Notifications\TripEligibleForCompensation;
use App\Services\Eligibility\EligibilityEngine;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Admin Trip Reviews queue - the human side of "our team is verifying". */
class AdminTripReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Role::findOrCreate('admin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->customer = User::factory()->create();
    }

    public function test_approving_marks_the_trip_eligible_and_notifies_the_customer(): void
    {
        $trip = $this->reviewTrip();

        Livewire::actingAs($this->admin)->test(Index::class)->call('approve', $trip->id);

        $trip->refresh();
        $this->assertSame(EligibilityEngine::STATUS_ELIGIBLE, $trip->eligibility_status);
        $this->assertSame('eligible', $trip->displayStatus());
        $this->assertStringNotContainsString('Our team is verifying', $trip->eligibility_reason);
        Notification::assertSentTo($this->customer, TripEligibleForCompensation::class);

        // Accountability: who decided, when, and that it was a human.
        $this->assertSame('admin', $trip->eligibility_decision_source);
        $this->assertSame($this->admin->id, $trip->eligibility_decided_by);
        $this->assertNotNull($trip->eligibility_decided_at);
    }

    public function test_rejecting_requires_a_customer_facing_reason(): void
    {
        $trip = $this->reviewTrip();

        Livewire::actingAs($this->admin)->test(Index::class)
            ->call('reject', $trip->id)
            ->assertHasErrors('rejection_reason');

        Livewire::actingAs($this->admin)->test(Index::class)
            ->set('rejection_reason', 'The airline\'s records show you boarded this flight, so denied boarding compensation does not apply.')
            ->call('reject', $trip->id);

        $trip->refresh();
        $this->assertSame(EligibilityEngine::STATUS_REJECTED, $trip->eligibility_status);
        $this->assertSame('not_eligible', $trip->displayStatus());
        $this->assertStringContainsString('records show you boarded', $trip->eligibility_reason);

        // The customer is told the outcome, with the admin's reason verbatim.
        Notification::assertSentTo($this->customer, TripEligibilityRejected::class, function ($notification) {
            return str_contains($notification->reason, 'records show you boarded');
        });
    }

    public function test_notification_mail_renders_through_the_admin_managed_template(): void
    {
        $trip         = $this->reviewTrip();
        $notification = new TripEligibilityRejected($trip, 'Airline records show you boarded this flight.');

        // Template active (seeded by migration): placeholders swapped in.
        $mail = $notification->toMail($this->customer);
        $this->assertInstanceOf(GenericEmail::class, $mail);
        $this->assertStringContainsString($trip->flight_number, $mail->subjectLine);
        $this->assertStringContainsString('Airline records show you boarded this flight.', $mail->htmlBody);
        $this->assertStringContainsString($this->customer->name, $mail->htmlBody);

        // Deactivated template falls back to the built-in copy.
        EmailTemplate::where('slug', 'trip-eligibility-rejected')->update(['is_active' => false]);
        $this->assertInstanceOf(MailMessage::class, $notification->toMail($this->customer));
    }

    private function reviewTrip(): Trip
    {
        return Trip::create([
            'user_id'                => $this->customer->id,
            'source'                 => Trip::SOURCE_MANUAL,
            'status'                 => Trip::STATUS_PROTECTED,
            'airline'                => 'Emirates',
            'flight_number'          => 'EK29',
            'departure_airport'      => 'DXB',
            'arrival_airport'        => 'LHR',
            'departure_date'         => now()->subDay()->toDateString(),
            'flight_status'          => Trip::FLIGHT_COMPLETED,
            'monitoring_status'      => Trip::MONITORING_COMPLETED,
            'potentially_eligible'   => true,
            'reported_disruption'    => 'denied_boarding',
            'eligibility_status'     => EligibilityEngine::STATUS_REVIEW,
            'eligibility_regulation' => 'UK261',
            'eligibility_article'    => 'Articles 4 & 7',
            'eligibility_confidence' => 30,
            'eligibility_reason'     => 'Being denied boarding entitles passengers to compensation. Our team is verifying the details before confirming your claim.',
            'eligibility_evaluated_at' => now(),
        ]);
    }
}
