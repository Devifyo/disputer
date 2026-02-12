<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class GeminiEmailAnalysisService
{
    protected string $apiKey;
    protected string $model = 'gemini-flash-latest'; // Can be swapped easily

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    /**
     * Main Entry Point: Analyzes an email and its attachments.
     */
    public function analyze(string $subject, string $body, array $attachments, ?string $userName = 'The User'): array
    {
        try {
            // 1. Process Attachments (Text & Images)
            $processedData = $this->processAttachments($attachments);

            // 2. Build the System Prompt
            $promptText = $this->buildPrompt($subject, $body, $processedData['text_context'], $userName);

            // 3. Prepare API Payload (Text + Images)
            $apiParts = [['text' => $promptText]];
            $apiParts = array_merge($apiParts, $processedData['media_parts']);

            // 4. Call Gemini
            return $this->callGemini($apiParts);

        } catch (\Exception $e) {
            Log::error("Gemini Service Error: " . $e->getMessage());
            return ['summary' => 'Analysis failed. Please try again later.'];
        }
    }

    /**
     * Processes files to extract text (PDF/CSV) or prepare images (JPG/PNG).
     */
    protected function processAttachments(array $attachments): array
    {
        $textContext = "";
        $mediaParts = [];

        foreach ($attachments as $file) {
            $path = $file['path'] ?? null;
            
            if (!$path || !Storage::disk('public')->exists($path)) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $fullPath = storage_path('app/public/' . $path);

            try {
                // A. Images (Visual Analysis)
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'])) {
                    $mediaParts[] = [
                        'inline_data' => [
                            'mime_type' => Storage::disk('public')->mimeType($path),
                            'data' => base64_encode(file_get_contents($fullPath))
                        ]
                    ];
                    $textContext .= "\n[Attachment: {$file['name']}] (Image attached for visual analysis)";
                } 
                // B. PDF (Text Extraction)
                elseif ($ext === 'pdf') {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($fullPath);
                    $textContext .= "\n[Attachment: {$file['name']}] PDF Text: " . mb_substr($pdf->getText(), 0, 2000);
                } 
                // C. Text Data (CSV/Logs)
                elseif (in_array($ext, ['csv', 'txt', 'log'])) {
                    $textContext .= "\n[Attachment: {$file['name']}] Data: " . mb_substr(file_get_contents($fullPath), 0, 2000);
                }
            } catch (\Exception $e) {
                Log::warning("File processing error [{$file['name']}]: " . $e->getMessage());
                $textContext .= "\n[Attachment: {$file['name']}] (Could not be processed)";
            }
        }

        return ['text_context' => $textContext, 'media_parts' => $mediaParts];
    }

    /**
     * Constructs the strict JSON instruction prompt.
     */
    protected function buildPrompt(string $subject, string $body, string $attachmentContext, string $userName): string
    {
        return "You are an AI legal assistant supporting a dispute workflow system.
            The user ({$userName}) is reviewing an email related to their dispute case.

            EMAIL CONTEXT:
            Subject: {$subject}
            Body: " . strip_tags($body) . "
            Attachments Summary: " . ($attachmentContext ?: 'None') . "

            OBJECTIVE:
            Analyze the email and attachments objectively.
            Provide structured insights to help the user understand the content and decide next steps.
            Do NOT make legal decisions.

            RETURN VALID JSON ONLY:
            {
                \"summary\": \"Brief, neutral explanation of what this email communicates.\",
                \"email_type\": \"One of: User Submission, Institution Response, Acknowledgment, Denial, Information Request, Other.\",
                \"key_entities\": {
                    \"dates\": [\"List any dates mentioned\"],
                    \"amounts\": [\"List monetary amounts mentioned\"],
                    \"reference_numbers\": [\"List case or transaction references if present\"]
                },
                \"attachment_analysis\": {
                    \"document_types_detected\": [\"Invoice, Statement, Rejection Letter, etc.\"],
                    \"important_details_extracted\": [\"Key extracted facts from attachments\"],
                    \"missing_or_unclear_items\": [\"Documents or details that appear missing or incomplete\"]
                },
                \"action_flags\": {
                    \"response_required\": true,
                    \"deadline_mentioned\": true,
                    \"explicit_rejection\": false
                },
                \"suggested_next_steps\": [\"Provide 1–3 neutral, timing-aware suggestions.\"],
                \"confidence_score\": 0.0
            }";
    }

    /**
     * Sends the request to the API and parses the response.
     */
    protected function callGemini(array $parts): array
    {
        $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
            'contents' => [['parts' => $parts]]
        ]);

        if (!$response->successful()) {
            throw new \Exception("Gemini API Error: " . $response->body());
        }

        $rawText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $cleanJson = str_replace(['```json', '```'], '', $rawText);
        $decoded = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['summary' => 'Error parsing AI insights.'];
        }

        return $decoded;
    }
}