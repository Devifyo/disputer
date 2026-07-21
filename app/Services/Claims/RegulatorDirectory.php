<?php

namespace App\Services\Claims;

use App\Models\Claim;
use App\Services\Eligibility\EligibilityEngine;

/**
 * Which enforcement body hears a complaint about this claim.
 *
 * APPR, UK261 and US DOT each have a single national regulator. EU261 does
 * NOT: every member state designates its own National Enforcement Body, and
 * the competent one is decided by where the disruption happened - so an
 * Air France delay out of Frankfurt is heard by Germany's LBA, not the DGAC.
 *
 * The route decides the regulator (deterministic, auditable). AI is not used
 * to name regulators for the same reason it is not used to pick legal
 * citations: a plausible-sounding wrong authority would send a real complaint
 * to the wrong place. Where the route cannot settle it, the resolver says so
 * and the admin chooses.
 */
class RegulatorDirectory
{
    /**
     * EU/EEA National Enforcement Bodies, keyed by ISO country code.
     * [short name, full name, complaint portal]
     */
    private const NEBS = [
        'AT' => ['apf', 'Agentur für Passagier- und Fahrgastrechte', 'https://www.apf.gv.at'],
        'BE' => ['BCAA', 'FPS Mobility and Transport - Belgian Civil Aviation Authority', 'https://mobilit.belgium.be'],
        'BG' => ['DG CAA', 'Directorate General Civil Aviation Administration', 'https://www.caa.bg'],
        'HR' => ['CCAA', 'Croatian Civil Aviation Agency', 'https://www.ccaa.hr'],
        'CY' => ['DCA Cyprus', 'Department of Civil Aviation', 'https://www.mcw.gov.cy/dca'],
        'CZ' => ['ÚCL', 'Civil Aviation Authority of the Czech Republic', 'https://www.caa.cz'],
        'DK' => ['Trafikstyrelsen', 'Danish Civil Aviation and Railway Authority', 'https://www.trafikstyrelsen.dk'],
        'EE' => ['ECPTRA', 'Estonian Consumer Protection and Technical Regulatory Authority', 'https://www.ttja.ee'],
        'FI' => ['Traficom', 'Finnish Transport and Communications Agency', 'https://www.traficom.fi'],
        'FR' => ['DGAC', "Direction générale de l'aviation civile", 'https://www.ecologie.gouv.fr/aide-aux-passagers-aeriens'],
        'DE' => ['LBA', 'Luftfahrt-Bundesamt', 'https://www.lba.de'],
        'GR' => ['HCAA', 'Hellenic Civil Aviation Authority', 'https://www.hcaa.gr'],
        'HU' => ['BFKH', 'Budapest Capital Government Office - Consumer Protection', 'https://www.kormanyhivatal.hu'],
        'IE' => ['CAR', 'Commission for Aviation Regulation', 'https://www.flightrights.ie'],
        'IT' => ['ENAC', "Ente Nazionale per l'Aviazione Civile", 'https://www.enac.gov.it'],
        'LV' => ['CRPC', 'Consumer Rights Protection Centre', 'https://www.ptac.gov.lv'],
        'LT' => ['LTSA', 'Lithuanian Transport Safety Administration', 'https://ltsa.lrv.lt'],
        'LU' => ['DAC', "Direction de l'Aviation Civile", 'https://dac.gouvernement.lu'],
        'MT' => ['MCCAA', 'Malta Competition and Consumer Affairs Authority', 'https://mccaa.org.mt'],
        'NL' => ['ILT', 'Inspectie Leefomgeving en Transport', 'https://www.ilent.nl'],
        'PL' => ['ULC', 'Civil Aviation Authority of Poland', 'https://www.gov.pl/web/ulc'],
        'PT' => ['ANAC', 'Autoridade Nacional da Aviação Civil', 'https://www.anac.pt'],
        'RO' => ['AACR', 'Romanian Civil Aeronautical Authority', 'https://www.caa.ro'],
        'SK' => ['DÚ SR', 'Transport Authority of the Slovak Republic', 'https://letectvo.nsat.sk'],
        'SI' => ['CAA Slovenia', 'Civil Aviation Agency of the Republic of Slovenia', 'https://www.caa.si'],
        'ES' => ['AESA', 'Agencia Estatal de Seguridad Aérea', 'https://www.seguridadaerea.gob.es'],
        'SE' => ['Transportstyrelsen', 'Swedish Transport Agency', 'https://www.transportstyrelsen.se'],
        'IS' => ['ICETRA', 'Icelandic Transport Authority', 'https://www.icetra.is'],
        'NO' => ['Transportklagenemnda', 'Norwegian Transport Complaint Board', 'https://transportklagenemnda.no'],
        'CH' => ['FOCA', 'Federal Office of Civil Aviation', 'https://www.bazl.admin.ch'],
    ];

    /** Regimes with a single national regulator. */
    private const SINGLE_BODY = [
        'APPR'   => ['CTA', 'Canadian Transportation Agency', 'https://otc-cta.gc.ca/eng/air-travel-complaint'],
        'UK261'  => ['CAA', 'UK Civil Aviation Authority - Passenger Advice and Complaints Team', 'https://www.caa.co.uk/passengers'],
        'US_DOT' => ['US DOT', "US Department of Transportation - Office of Aviation Consumer Protection", 'https://www.transportation.gov/airconsumer/file-consumer-complaint'],
    ];

    /**
     * The competent regulator for a claim.
     *
     * @return array{code: string, name: string, url: string, country: ?string, reason: string, confident: bool}
     */
    public static function for(Claim $claim): array
    {
        $regulation = strtoupper((string) $claim->eligibility_regulation);

        if (isset(self::SINGLE_BODY[$regulation])) {
            [$code, $name, $url] = self::SINGLE_BODY[$regulation];

            return [
                'code'      => $code,
                'name'      => $name,
                'url'       => $url,
                'country'   => match ($regulation) { 'APPR' => 'CA', 'UK261' => 'GB', default => 'US' },
                'reason'    => "{$regulation} is enforced nationally by the {$code}.",
                'confident' => true,
            ];
        }

        if ($regulation === 'EU261') {
            return self::neb($claim);
        }

        return self::unresolved('No regulation on the claim yet - the Eligibility Engine has not decided it.');
    }

    /**
     * EU261: the NEB of the member state where the incident occurred. The
     * departure airport governs when it is in the EU/EEA; for a flight INTO
     * the EU on an EU carrier the arrival state's NEB is competent.
     */
    private static function neb(Claim $claim): array
    {
        $engine    = app(EligibilityEngine::class);
        $countries = (array) config('eligibility.eu261_countries', []);

        $origin      = $engine->countryOf($claim->departure_airport);
        $destination = $engine->countryOf($claim->arrival_airport);

        if ($origin && in_array($origin, $countries, true)) {
            return self::fromCountry($origin, sprintf(
                'The flight departed %s (%s), so that state\'s National Enforcement Body is competent under EU261.',
                $claim->departure_airport, $origin
            ));
        }

        if ($destination && in_array($destination, $countries, true)) {
            return self::fromCountry($destination, sprintf(
                'The flight departed outside the EU/EEA and arrived in %s (%s), so the arrival state\'s NEB is competent when the carrier is an EU carrier.',
                $claim->arrival_airport, $destination
            ));
        }

        return self::unresolved(
            'Neither airport is in the EU/EEA, so no National Enforcement Body follows from the route - confirm which state is competent before filing.'
        );
    }

    private static function fromCountry(string $country, string $reason): array
    {
        if (!isset(self::NEBS[$country])) {
            return self::unresolved("No National Enforcement Body on file for {$country} - confirm the competent authority before filing.");
        }

        [$code, $name, $url] = self::NEBS[$country];

        return [
            'code'      => $code,
            'name'      => $name,
            'url'       => $url,
            'country'   => $country,
            'reason'    => $reason,
            'confident' => true,
        ];
    }

    private static function unresolved(string $reason): array
    {
        return [
            'code'      => '',
            'name'      => '',
            'url'       => '',
            'country'   => null,
            'reason'    => $reason,
            'confident' => false,
        ];
    }
}
