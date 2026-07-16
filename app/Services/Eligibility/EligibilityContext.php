<?php

namespace App\Services\Eligibility;

use Illuminate\Support\Carbon;

/**
 * Everything the evaluators need to judge a disrupted flight, resolved once
 * by the caller: route jurisdiction, the final disruption facts, and how
 * trustworthy the underlying data is. Model-agnostic - built from a Trip
 * (live monitoring) or a Claim (past flight).
 */
class EligibilityContext
{
    public function __construct(
        public string $ref,                  // audit label, e.g. "trip:12" / "claim:5"
        public ?string $airline,
        public ?string $flightNumber,
        public ?Carbon $flightDate,
        public ?string $departureAirport,
        public ?string $arrivalAirport,
        public ?string $originCountry,       // ISO 3166-1 alpha-2, e.g. "DE"
        public ?string $destinationCountry,
        public bool $cancelled,
        public int $arrivalDelayMinutes,     // best-known arrival delay
        public bool $delayIsActual,          // true when actual arrival is verified
        public bool $factsVerified = true,   // false when FlightAware couldn't confirm the flight
        public bool $diverted = false,
        public bool $didNotTravel = false,  // cancelled + passenger chose a refund over rebooking
        public ?string $reportedDisruption = null, // denied_boarding | downgrade | missed_connection | delayed | cancelled | schedule_change | returned_to_origin | other
        public array $reportAnswers = [],          // [{question, answer}] from the report funnel
    ) {
    }

    public function originIn(array $countries): bool
    {
        return $this->originCountry !== null && in_array($this->originCountry, $countries, true);
    }

    public function destinationIn(array $countries): bool
    {
        return $this->destinationCountry !== null && in_array($this->destinationCountry, $countries, true);
    }
}
