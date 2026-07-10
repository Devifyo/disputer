<?php

/*
|--------------------------------------------------------------------------
| Eligibility Engine — air passenger rights regulations
|--------------------------------------------------------------------------
| Jurisdictions and thresholds used to evaluate whether a disrupted,
| monitored trip qualifies for compensation. The confidence threshold's
| runtime value is admin-managed (settings table); the value here is the
| default until an admin changes it.
*/

return [

    // Verdicts with a confidence below this are automatically rejected.
    // Admin-managed via Settings → Trip Eligibility (settings key
    // "eligibility.confidence_threshold"); this is only the default.
    'default_confidence_threshold' => 70,

    // "ai" evaluates with Gemini (rules as automatic fallback); "rules"
    // skips AI entirely.
    'evaluator' => env('ELIGIBILITY_EVALUATOR', 'ai'),
    'ai_model'  => env('ELIGIBILITY_AI_MODEL', 'gemini-2.5-flash'),

    // EU261 applies to departures from these countries (EU-27 + EEA + CH,
    // where Regulation (EC) No 261/2004 is applied).
    'eu261_countries' => [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
        'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
        'SI', 'ES', 'SE', 'IS', 'NO', 'CH',
    ],

    'uk_countries' => ['GB'],
    'canada'       => ['CA'],
    'usa'          => ['US'],

    // Minimum arrival delay (minutes) for compensation under each regime.
    'delay_thresholds' => [
        'eu261' => 180,
        'uk261' => 180,
        'appr'  => 180,
    ],
];
