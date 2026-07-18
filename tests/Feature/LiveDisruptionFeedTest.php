<?php

namespace Tests\Feature;

use App\Services\Marketing\LiveDisruptionFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Landing page live board: FlightAware-fed, cached, never empty. */
class LiveDisruptionFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.flightaware.api_key' => 'test-key']);
    }

    private function fakeDepartures(): void
    {
        Http::fake([
            '*/airports/*/flights/departures*' => Http::response(['departures' => [
                ['ident_iata' => 'AC856', 'cancelled' => true, 'arrival_delay' => 0, 'destination' => ['code_iata' => 'LHR']],
                ['ident_iata' => 'AC102', 'cancelled' => false, 'arrival_delay' => 200 * 60, 'destination' => ['code_iata' => 'YVR']],
                ['ident_iata' => 'AC104', 'cancelled' => false, 'arrival_delay' => 20 * 60, 'destination' => ['code_iata' => 'YVR']], // on time-ish: excluded
                ['ident' => 'N7965M', 'cancelled' => true, 'destination' => ['code_iata' => 'TEB']],                                  // private: excluded
            ]], 200),
        ]);
    }

    public function test_scan_maps_disrupted_flights_and_prices_by_regime(): void
    {
        $this->fakeDepartures();

        $rows = app(LiveDisruptionFeedService::class)->rows();

        $rows = collect($rows);

        $this->assertSame('AC 856', $rows->firstWhere('status', 'CANCELLED')['flight']);
        $this->assertSame('400CAD', $rows->firstWhere('route', 'YYZ -> YVR')['pay'] ?? null);
        $this->assertNull($rows->firstWhere('flight', 'AC 104'));
        $this->assertNull($rows->firstWhere('flight', 'N7965M'));
    }

    public function test_one_scan_serves_repeat_requests_from_cache(): void
    {
        $this->fakeDepartures();

        app(LiveDisruptionFeedService::class)->rows();
        app(LiveDisruptionFeedService::class)->rows();
        app(LiveDisruptionFeedService::class)->rows();

        // 6 airports scanned exactly once - not once per request.
        Http::assertSentCount(6);
    }

    public function test_feed_endpoint_serves_rows_and_never_breaks_without_flightaware(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->getJson(route('live-disruptions'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['flight', 'route', 'status', 'pay']]]);

        // Even with no API key at all, the sample pool keeps the board alive.
        config(['services.flightaware.api_key' => null]);
        $this->getJson(route('live-disruptions'))->assertOk()->assertJsonCount(6, 'data');
    }
}
