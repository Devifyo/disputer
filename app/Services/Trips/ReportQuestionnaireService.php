<?php

namespace App\Services\Trips;

use App\Models\Trip;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Adaptive intake funnel for disruption reports: after every answer, Gemini
 * picks the single most useful next question (or decides it has enough),
 * so the flow branches on what the passenger actually said. The curated
 * sets below serve the questions sequentially when AI is unavailable.
 */
class ReportQuestionnaireService
{
    private const TYPES         = ['choice', 'text'];
    private const MAX_QUESTIONS = 5;

    /**
     * The next funnel step given the answers so far.
     *
     * @param array<array{question: string, answer: string}> $answers
     * @return array{done: bool, question: ?array, documents: ?array}
     */
    public function nextQuestion(Trip $trip, string $type, array $answers): array
    {
        if (count($answers) >= self::MAX_QUESTIONS) {
            return $this->finished($type);
        }

        try {
            $next = $this->generateWithAi($trip, $type, $answers);
        } catch (Throwable) {
            $next = $this->fallbackNext($type, $answers);
        }

        // Never let the funnel end after a single question.
        if ($next === null && count($answers) < 2) {
            $next = $this->fallbackNext($type, $answers)
                ?? ['question' => 'Is there anything else about what happened that our team should know?', 'type' => 'text', 'options' => []];
        }

        return $next === null
            ? $this->finished($type)
            : ['done' => false, 'question' => $next, 'documents' => null];
    }

    private function finished(string $type): array
    {
        return ['done' => true, 'question' => null, 'documents' => $this->documentStep($type)];
    }

    // ── AI generation ───────────────────────────────────────

    /** Returns the next question, or null when the AI decides it has enough. */
    private function generateWithAi(Trip $trip, string $type, array $answers): ?array
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            throw new RuntimeException('Gemini not configured.');
        }

        $model    = config('eligibility.ai_model', 'gemini-2.5-flash');
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(15)->retry(1, 250, throw: false)->post($endpoint, [
            'contents'         => [['parts' => [['text' => $this->prompt($trip, $type, $answers)]]]],
            'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json'],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Gemini request failed.');
        }

        $raw     = $response->json('candidates.0.content.parts.0.text') ?? '';
        $decoded = json_decode(trim(str_replace(['```json', '```'], '', $raw)), true);

        if (!is_array($decoded) || !is_bool($decoded['done'] ?? null)) {
            throw new RuntimeException('Invalid questionnaire step from Gemini.');
        }

        return $decoded['done'] ? null : $this->validateQuestion($decoded['question'] ?? null);
    }

    private function prompt(Trip $trip, string $type, array $answers): string
    {
        $case    = str_replace('_', ' ', $type);
        $history = $answers
            ? "Answers so far:\n" . json_encode($answers, JSON_PRETTY_PRINT)
            : 'No questions asked yet - this will be the first question.';

        return <<<PROMPT
You are an air passenger rights claims intake assistant. A passenger on flight {$trip->flight_number} from {$trip->departure_airport} to {$trip->arrival_airport} on {$trip->departure_date?->toDateString()} is reporting: "{$case}".

{$history}

Decide the SINGLE most useful next question, adapting to the answers above (e.g. if they volunteered their seat, ask what they were promised instead of continuing the involuntary-denial track). The answers determine eligibility under EU261, UK261, APPR (Canada) and US DOT rules. Plain language, no legal jargon, never repeat a question already asked, no questions about facts we already know (flight, route, date). Aim for 3-5 questions in total; when the answers above are already enough to assess the case, stop.

Respond with ONLY one of these JSON shapes, no markdown:
{"done":false,"question":{"question":"Did you volunteer to give up your seat?","type":"choice","options":["No - I was denied against my will","Yes - I volunteered"]}}
{"done":true}

"type" must be "choice" (with 2-5 options) or "text".
PROMPT;
    }

    private function validateQuestion(mixed $item): array
    {
        if (!is_array($item)
            || !is_string($item['question'] ?? null) || trim($item['question']) === ''
            || !in_array($item['type'] ?? null, self::TYPES, true)
        ) {
            throw new RuntimeException('Invalid question item.');
        }

        $options = array_values(array_filter((array) ($item['options'] ?? []), 'is_string'));
        if ($item['type'] === 'choice' && (count($options) < 2 || count($options) > 5)) {
            throw new RuntimeException('Invalid choice options.');
        }

        return [
            'question' => mb_substr(trim($item['question']), 0, 200),
            'type'     => $item['type'],
            'options'  => $item['type'] === 'choice' ? array_map(fn ($o) => mb_substr($o, 0, 120), $options) : [],
        ];
    }

    // ── Curated fallbacks (served sequentially) ─────────────

    private function fallbackNext(string $type, array $answers): ?array
    {
        $set = $this->fallbackQuestions($type);

        return $set[count($answers)] ?? null;
    }

    private function fallbackQuestions(string $type): array
    {
        $sets = [
            'denied_boarding' => [
                ['question' => 'Did you check in and arrive at the gate before boarding closed?', 'type' => 'choice', 'options' => ['Yes', 'No', 'Not sure']],
                ['question' => 'Did you volunteer to give up your seat?', 'type' => 'choice', 'options' => ['No - I was denied against my will', 'Yes - I volunteered']],
                ['question' => 'What reason did the airline give?', 'type' => 'choice', 'options' => ['Overbooking', 'Aircraft change', 'Documents/security', 'No reason given', 'Other']],
                ['question' => 'What did the airline offer you (rebooking, vouchers, refund)?', 'type' => 'text'],
            ],
            'downgrade' => [
                ['question' => 'Which cabin class did you book?', 'type' => 'choice', 'options' => ['First', 'Business', 'Premium Economy', 'Economy']],
                ['question' => 'Which cabin class were you seated in?', 'type' => 'choice', 'options' => ['Business', 'Premium Economy', 'Economy']],
                ['question' => 'Did the airline offer you any refund or compensation for the downgrade?', 'type' => 'text'],
            ],
            'missed_connection' => [
                ['question' => 'Were both flights on a single booking/ticket?', 'type' => 'choice', 'options' => ['Yes - one booking', 'No - separate bookings', 'Not sure']],
                ['question' => 'How late did you finally arrive at your destination?', 'type' => 'choice', 'options' => ['Under 3 hours', '3-4 hours', 'More than 4 hours', 'I never arrived / travelled next day']],
                ['question' => 'Did the airline rebook you, and how long did you wait?', 'type' => 'text'],
            ],
            'other' => [
                ['question' => 'When did the problem happen?', 'type' => 'choice', 'options' => ['Before departure', 'At the gate', 'During the flight', 'After landing']],
                ['question' => 'Describe what happened in your own words.', 'type' => 'text'],
                ['question' => 'What did the airline say or offer, if anything?', 'type' => 'text'],
            ],
        ];

        return $sets[$type] ?? $sets['other'];
    }

    /** The fixed final step: what to upload, with concrete examples. */
    private function documentStep(string $type): array
    {
        $examples = [
            'denied_boarding'   => ['Your boarding pass or check-in confirmation', 'Rebooking confirmation or the new boarding pass', 'Any written communication from the airline (email, SMS, denial slip)'],
            'downgrade'         => ['Your original booking confirmation showing the booked class', 'The boarding pass showing the class you actually flew', 'Any refund or voucher offer from the airline'],
            'missed_connection' => ['Your full itinerary showing both flights on one booking', 'Boarding passes for both flights (or the rebooked flight)', 'Rebooking confirmation or delay confirmation from the airline'],
            'other'             => ['Your booking confirmation or ticket', 'Photos or documents showing what happened', 'Any communication from the airline about the issue'],
        ];

        return [
            'title'    => 'Supporting documents',
            'note'     => 'Documents massively strengthen your case - our team uses them to verify your report with the airline.',
            'examples' => $examples[$type] ?? $examples['other'],
        ];
    }
}
