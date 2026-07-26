<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\Itinerary;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ItineraryParserService
{
    private const MODEL = 'gemini-2.5-flash';

    /**
     * Parse a stored itinerary PDF: extract text, ask Gemini for structured
     * data, and persist flights + passengers. Runs synchronously.
     */
    public function parse(Itinerary $itinerary): bool
    {
        $itinerary->update(['status' => Itinerary::STATUS_PROCESSING, 'parse_error' => null]);

        try {
            if (!Storage::exists($itinerary->file_path)) {
                return $this->fail($itinerary, 'Uploaded file could not be found on disk.');
            }

            $bytes = Storage::get($itinerary->file_path);
            $mime  = $itinerary->mime_type ?: 'application/pdf';

            // 1. Best-effort raw text — only for PDFs (helps Gemini + useful for debugging).
            //    Images are read directly by Gemini's multimodal model.
            $rawText = str_contains($mime, 'pdf') ? $this->extractText($itinerary->file_path) : null;

            // 2. Structured extraction via Gemini (multimodal: reads the PDF or photo directly).
            $data = $this->extractStructured($bytes, $mime, $rawText);

            if ($data === null) {
                return $this->fail($itinerary, 'The document could not be read. Please upload a clear PDF or photo of your flight itinerary.', $rawText);
            }

            $this->persist($itinerary, $data, $rawText);

            return true;
        } catch (\Throwable $e) {
            Log::error('Itinerary parsing failed', ['id' => $itinerary->id, 'error' => $e->getMessage()]);
            return $this->fail($itinerary, 'An unexpected error occurred while processing the document.');
        }
    }

    /**
     * Parse an itinerary from raw email text (inline-forwarded confirmations
     * that have no PDF/image attachment). Runs synchronously.
     */
    public function parseFromText(Itinerary $itinerary, string $text): bool
    {
        $itinerary->update(['status' => Itinerary::STATUS_PROCESSING, 'parse_error' => null]);

        try {
            $text = trim($text);
            if ($text === '') {
                return $this->fail($itinerary, 'The forwarded email did not contain any itinerary text.');
            }

            $data = $this->extractStructuredFromText($text);
            if ($data === null) {
                return $this->fail($itinerary, 'We could not read a flight itinerary from the forwarded email.', $text);
            }

            $this->persist($itinerary, $data, $text);
            return true;
        } catch (\Throwable $e) {
            Log::error('Itinerary text parsing failed', ['id' => $itinerary->id, 'error' => $e->getMessage()]);
            return $this->fail($itinerary, 'An unexpected error occurred while processing the forwarded email.');
        }
    }

    private function extractStructuredFromText(string $text): ?array
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            Log::error('Gemini API key missing — cannot parse itinerary.');
            return null;
        }

        $prompt = "You are a flight itinerary parser for an air-passenger compensation service.\n"
            . "Read the following forwarded flight itinerary / booking confirmation email and extract its details.\n\n"
            . "Rules:\n"
            . "- Extract EVERY flight segment (outbound, return, connections) as separate entries, in travel order.\n"
            . "- Extract EVERY passenger named on the booking.\n"
            . "- Airport codes must be 3-letter IATA codes. Convert city/airport names to IATA codes.\n"
            . "- Dates/times must be ISO 8601 local time YYYY-MM-DDTHH:MM (or YYYY-MM-DD if no time). Empty string if absent.\n"
            . "- flight_number includes the carrier prefix (e.g. BA249). Do not invent data.\n"
            . "Return ONLY the structured object.\n\nEmail content:\n\"\"\"\n"
            . mb_substr($text, 0, 20000) . "\n\"\"\"";

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . self::MODEL . ":generateContent?key={$apiKey}";

        $response = Http::timeout(90)
            ->retry(2, 2000, function ($e) {
                if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
                    return true;
                }
                if ($e instanceof \Illuminate\Http\Client\RequestException) {
                    return in_array($e->response->status(), [429, 503]);
                }
                return false;
            }, throw: false)
            ->post($endpoint, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema'   => $this->itinerarySchema(),
                    'thinkingConfig'   => ['thinkingBudget' => 0],
                ],
            ]);

        if (!$response->successful()) {
            Log::error('Gemini itinerary (text) request failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $json = $response->json('candidates.0.content.parts.0.text');
        $parsed = $json ? json_decode($json, true) : null;
        return is_array($parsed) ? $parsed : null;
    }

    private function extractText(string $path): ?string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile(Storage::path($path));
            $text = trim($pdf->getText());
            return $text !== '' ? mb_substr($text, 0, 20000) : null;
        } catch (\Throwable $e) {
            Log::warning('PDF text extraction failed', ['path' => $path, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractStructured(string $bytes, string $mime, ?string $rawText): ?array
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            Log::error('Gemini API key missing — cannot parse itinerary.');
            return null;
        }

        $prompt = <<<'PROMPT'
You are a flight itinerary parser for an air-passenger compensation service.
Read the attached flight itinerary / booking confirmation (it may be a PDF or a photo of a document, ticket or boarding pass) and extract its details.

Rules:
- Extract EVERY flight segment in the itinerary (outbound, return, and any connections) as separate entries, in travel order.
- Extract EVERY passenger named on the booking.
- Airport codes must be 3-letter IATA codes (e.g. LHR, JFK). If only a city/airport name is shown, convert it to its IATA code.
- Dates and times must be ISO 8601 local time in the format YYYY-MM-DDTHH:MM (24-hour). If a time is unknown, use YYYY-MM-DD. If a value is not present, use an empty string.
- "airline" is the operating airline's common name (e.g. "British Airways"). "flight_number" includes the carrier prefix (e.g. "BA249").
- Do not invent data. Leave fields empty if the document does not contain them.
Return ONLY the structured object.
PROMPT;

        if ($rawText) {
            $prompt .= "\n\nExtracted text from the document (use as a supplement to the attached file):\n\"\"\"\n" . $rawText . "\n\"\"\"";
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . self::MODEL . ":generateContent?key={$apiKey}";

        $schema = $this->itinerarySchema();

        $response = Http::timeout(90)
            ->retry(2, 2000, function ($e) {
                if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
                    return true;
                }
                if ($e instanceof \Illuminate\Http\Client\RequestException) {
                    return in_array($e->response->status(), [429, 503]);
                }
                return false;
            }, throw: false)
            ->post($endpoint, [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => [
                            'mime_type' => $mime,
                            'data'      => base64_encode($bytes),
                        ]],
                    ],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema'   => $schema,
                    'thinkingConfig'   => ['thinkingBudget' => 0],
                ],
            ]);

        if (!$response->successful()) {
            Log::error('Gemini itinerary request failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $json = $response->json('candidates.0.content.parts.0.text');
        if (!$json) {
            return null;
        }

        $parsed = json_decode($json, true);
        return is_array($parsed) ? $parsed : null;
    }

    private function itinerarySchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'booking_reference' => ['type' => 'STRING'],
                'airline'           => ['type' => 'STRING'],
                'flights' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'airline'            => ['type' => 'STRING'],
                            'flight_number'      => ['type' => 'STRING'],
                            'departure_airport'  => ['type' => 'STRING'],
                            'arrival_airport'    => ['type' => 'STRING'],
                            'departure_datetime' => ['type' => 'STRING'],
                            'arrival_datetime'   => ['type' => 'STRING'],
                            'cabin_class'        => ['type' => 'STRING'],
                        ],
                    ],
                ],
                'passengers' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'full_name'     => ['type' => 'STRING'],
                            'type'          => ['type' => 'STRING'],
                            'ticket_number' => ['type' => 'STRING'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function persist(Itinerary $itinerary, array $data, ?string $rawText): void
    {
        $flights    = is_array($data['flights'] ?? null) ? $data['flights'] : [];
        $passengers = is_array($data['passengers'] ?? null) ? $data['passengers'] : [];

        $itinerary->fill([
            'status'            => Itinerary::STATUS_PARSED,
            'parsed_at'         => now(),
            'parse_error'       => null,
            'booking_reference' => $this->clean($data['booking_reference'] ?? null),
            'primary_airline'   => $this->clean($data['airline'] ?? null)
                ?: $this->clean($flights[0]['airline'] ?? null),
            'raw_text'          => $rawText,
            'parsed_raw'        => $data,
        ])->save();

        // Replace any previously parsed rows (supports re-parsing).
        $itinerary->flights()->delete();
        $itinerary->passengers()->delete();

        foreach (array_values($flights) as $i => $flight) {
            $itinerary->flights()->create([
                'sequence'          => $i,
                'airline'           => $this->clean($flight['airline'] ?? null),
                'flight_number'     => $this->clean($flight['flight_number'] ?? null),
                'departure_airport' => $this->cleanCode($flight['departure_airport'] ?? null),
                'arrival_airport'   => $this->cleanCode($flight['arrival_airport'] ?? null),
                'departure_at'      => $this->toDateTime($flight['departure_datetime'] ?? null),
                'arrival_at'        => $this->toDateTime($flight['arrival_datetime'] ?? null),
                'cabin_class'       => $this->clean($flight['cabin_class'] ?? null),
            ]);
        }

        foreach ($passengers as $passenger) {
            $name = $this->clean($passenger['full_name'] ?? null);
            if (!$name) {
                continue;
            }
            $itinerary->passengers()->create([
                'full_name'     => $name,
                'type'          => $this->clean($passenger['type'] ?? null),
                'ticket_number' => $this->clean($passenger['ticket_number'] ?? null),
            ]);
        }

        // One claim placeholder per passenger, linked to this itinerary.
        $itinerary->load('passengers');
        Claim::ensureForItinerary($itinerary);
    }

    private function fail(Itinerary $itinerary, string $message, ?string $rawText = null): bool
    {
        $itinerary->update([
            'status'      => Itinerary::STATUS_FAILED,
            'parse_error' => $message,
            'raw_text'    => $rawText ?? $itinerary->raw_text,
        ]);
        return false;
    }

    private function clean($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function cleanCode($value): ?string
    {
        $value = $this->clean($value);
        if (!$value) {
            return null;
        }
        return preg_match('/^[A-Za-z]{3}$/', $value) ? strtoupper($value) : $value;
    }

    private function toDateTime($value): ?Carbon
    {
        $value = $this->clean($value);
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
