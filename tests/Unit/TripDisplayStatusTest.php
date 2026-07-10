<?php

namespace Tests\Unit;

use App\Models\Trip;
use Tests\TestCase;

/**
 * Trip::displayStatus() — the dashboard status shown for a monitored trip.
 */
class TripDisplayStatusTest extends TestCase
{
    /** @dataProvider statusProvider */
    public function test_display_status_mapping(array $attributes, string $expected): void
    {
        $trip = new Trip();
        $trip->forceFill($attributes);

        $this->assertSame($expected, $trip->displayStatus());
    }

    public static function statusProvider(): array
    {
        return [
            'new trip, not yet registered' => [
                ['monitoring_status' => Trip::MONITORING_PENDING],
                'scheduled',
            ],
            'registered, no live data yet' => [
                ['monitoring_status' => Trip::MONITORING_ACTIVE, 'flight_status' => Trip::FLIGHT_SCHEDULED],
                'monitoring',
            ],
            'tracking on time' => [
                ['monitoring_status' => Trip::MONITORING_ACTIVE, 'flight_status' => Trip::FLIGHT_ON_TIME],
                'on_time',
            ],
            'minor delay' => [
                ['monitoring_status' => Trip::MONITORING_ACTIVE, 'flight_status' => Trip::FLIGHT_DELAYED],
                'delayed',
            ],
            'cancelled without eligibility' => [
                ['monitoring_status' => Trip::MONITORING_COMPLETED, 'flight_status' => Trip::FLIGHT_CANCELLED],
                'cancelled',
            ],
            'landed uneventfully' => [
                ['monitoring_status' => Trip::MONITORING_COMPLETED, 'flight_status' => Trip::FLIGHT_COMPLETED],
                'completed',
            ],
            'disrupted, flight still live' => [
                ['monitoring_status' => Trip::MONITORING_ACTIVE, 'flight_status' => Trip::FLIGHT_DELAYED, 'potentially_eligible' => true],
                'potentially_eligible',
            ],
            'disrupted and flight over' => [
                ['monitoring_status' => Trip::MONITORING_COMPLETED, 'flight_status' => Trip::FLIGHT_COMPLETED, 'potentially_eligible' => true],
                'eligibility_review_pending',
            ],
        ];
    }
}
