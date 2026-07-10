<?php

namespace App\Services\Eligibility;

use App\Models\Trip;

/**
 * Everything a regulation rule needs to judge a disrupted trip, resolved
 * once by the engine: route jurisdiction, the final disruption facts, and
 * how trustworthy the underlying flight data is.
 */
class EligibilityContext
{
    public function __construct(
        public Trip $trip,
        public ?string $originCountry,       // ISO 3166-1 alpha-2, e.g. "DE"
        public ?string $destinationCountry,
        public bool $cancelled,
        public int $arrivalDelayMinutes,     // best-known arrival delay
        public bool $delayIsActual,          // true when actual_arrival is known
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
