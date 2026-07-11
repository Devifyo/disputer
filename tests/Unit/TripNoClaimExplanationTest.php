<?php

namespace Tests\Unit;

use App\Models\Trip;
use Tests\TestCase;

/** Trip::noClaimExplanation() - why a finished trip has no claim. */
class TripNoClaimExplanationTest extends TestCase
{
    public function test_on_time_completed_flight_says_there_is_nothing_to_claim(): void
    {
        $this->assertStringContainsString('arrived on time', $this->trip(delay: 5)->noClaimExplanation());
    }

    public function test_sub_threshold_delay_explains_the_three_hour_rule(): void
    {
        $reason = $this->trip(delay: 45)->noClaimExplanation();

        $this->assertStringContainsString('45 minutes late', $reason);
        $this->assertStringContainsString('3 hours', $reason);
    }

    public function test_no_explanation_while_the_flight_is_still_live(): void
    {
        $trip = $this->trip(delay: 45);
        $trip->monitoring_status = Trip::MONITORING_ACTIVE;
        $trip->flight_status = Trip::FLIGHT_DELAYED;

        $this->assertNull($trip->noClaimExplanation());
    }

    public function test_no_explanation_for_disrupted_trips_awaiting_a_verdict(): void
    {
        $trip = $this->trip(delay: 240);
        $trip->potentially_eligible = true;

        $this->assertNull($trip->noClaimExplanation());
    }

    private function trip(int $delay): Trip
    {
        $trip = new Trip();
        $trip->forceFill([
            'monitoring_status'     => Trip::MONITORING_COMPLETED,
            'flight_status'         => Trip::FLIGHT_COMPLETED,
            'potentially_eligible'  => false,
            'arrival_delay_minutes' => $delay,
        ]);

        return $trip;
    }
}
