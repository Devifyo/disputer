<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class GeminiEmailAnalysisService
{
    protected string $apiKey;
    
    // The absolute best, most capable reasoning model (with built-in vision)
    protected string $model = 'gemini-2.5-pro'; 

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
            
            // Safe fallback so your UI doesn't crash on an API failure
            return [
                'summary' => "⚠️ **AI Service Currently Unavailable**\n\nThe AI model is experiencing unusually high demand. We tried connecting multiple times, but it is currently overloaded. Please wait a minute and try again.",
                'email_type' => 'Error / Unknown',
                'key_entities' => [
                    'dates' => [],
                    'amounts' => [],
                    'reference_numbers' => []
                ],
                'attachment_analysis' => [
                    'document_types_detected' => [],
                    'important_details_extracted' => [],
                    'missing_or_unclear_items' => []
                ],
                'action_flags' => [
                    'response_required' => false,
                    'deadline_mentioned' => false,
                    'explicit_rejection' => false
                ],
                'suggested_next_steps' => [
                    "Wait a few moments and click 'Analyze' again."
                ],
                'confidence_score' => 0.0
            ];
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

            $fullPath = storage_path('app/public/' . $path);
            
            $mimeType = $file['type'] ?? Storage::disk('public')->mimeType($path);

            // Extension-to-canonical-MIME map for types that get stored as application/octet-stream
            $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            $videoMimeMap = [
                'mp4'  => 'video/mp4',
                'mov'  => 'video/quicktime',
                'avi'  => 'video/x-msvideo',
                'mkv'  => 'video/x-matroska',
                'webm' => 'video/webm',
                'm4v'  => 'video/mp4',
            ];
            // If the stored MIME type is generic, derive it from the file extension
            if (!str_starts_with($mimeType, 'video/') && isset($videoMimeMap[$ext])) {
                $mimeType = $videoMimeMap[$ext];
            }

            try {
                // A. Images (Starts with 'image/' like image/jpeg, image/png)
                if (str_starts_with($mimeType, 'image/')) {
                    $mediaParts[] = [
                        'inlineData' => [
                            'mimeType' => $mimeType,
                            'data' => base64_encode(file_get_contents($fullPath))
                        ]
                    ];
                    $textContext .= "\n[Attachment: {$file['name']}] (Image attached for visual analysis)";
                }
                // B. PDF (Exact match for application/pdf)
                elseif ($mimeType === 'application/pdf') {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($fullPath);
                    $textContext .= "\n[Attachment: {$file['name']}] PDF Text: " . mb_substr($pdf->getText(), 0, 2000);
                }
                // C. Text Data (Starts with text/ like text/csv, text/plain)
                elseif (str_starts_with($mimeType, 'text/') || $mimeType === 'application/csv') {
                    $textContext .= "\n[Attachment: {$file['name']}] Data: " . mb_substr(file_get_contents($fullPath), 0, 2000);
                }
                // D. Video (detected by MIME type or extension)
                elseif (str_starts_with($mimeType, 'video/')) {
                    $fileUri = $this->uploadFileToGemini($fullPath, $mimeType, $file['name']);
                    if ($fileUri) {
                        $mediaParts[] = [
                            'fileData' => [
                                'mimeType' => $mimeType,
                                'fileUri' => $fileUri
                            ]
                        ];
                        $textContext .= "\n[Attachment: {$file['name']}] (Video attached — please describe its visual content and any relevant details visible in the footage)";
                    } else {
                        $textContext .= "\n[Attachment: {$file['name']}] (Video upload failed — could not be processed)";
                    }
                }
                // E. Unsupported
                else {
                    $textContext .= "\n[Attachment: {$file['name']}] (Unsupported file type: {$mimeType})";
                }
            } catch (\Exception $e) {
                Log::warning("File processing error [{$file['name']}]: " . $e->getMessage());
                $textContext .= "\n[Attachment: {$file['name']}] (Could not be processed)";
            }
        }

        return ['text_context' => $textContext, 'media_parts' => $mediaParts];
    }

    /**
     * Uploads a file (e.g. video) to the Gemini Files API and returns its URI.
     * Uses the resumable upload protocol required for large files.
     */
    protected function uploadFileToGemini(string $fullPath, string $mimeType, string $fileName): ?string
    {
        $fileContent = file_get_contents($fullPath);
        $fileSize    = strlen($fileContent);

        // Step 1: Initiate a resumable upload session
        $initResponse = Http::timeout(30)
            ->withHeaders([
                'X-Goog-Upload-Protocol'              => 'resumable',
                'X-Goog-Upload-Command'               => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) $fileSize,
                'X-Goog-Upload-Header-Content-Type'   => $mimeType,
                'Content-Type'                        => 'application/json',
            ])
            ->post("https://generativelanguage.googleapis.com/upload/v1beta/files?key={$this->apiKey}", [
                'file' => ['display_name' => $fileName]
            ]);

        $uploadUrl = $initResponse->header('X-Goog-Upload-URL');
        if (!$uploadUrl) {
            Log::error("Gemini Files API: failed to get upload URL for [{$fileName}]");
            return null;
        }

        // Step 2: Send the raw bytes
        $uploadResponse = Http::timeout(120)
            ->withHeaders([
                'Content-Length'         => (string) $fileSize,
                'X-Goog-Upload-Offset'   => '0',
                'X-Goog-Upload-Command'  => 'upload, finalize',
                'Content-Type'           => $mimeType,
            ])
            ->withBody($fileContent, $mimeType)
            ->post($uploadUrl);

        $fileData  = $uploadResponse->json();
        $fileUri   = $fileData['file']['uri']   ?? null;
        $fileState = $fileData['file']['state'] ?? 'UNKNOWN';
        $fileName  = $fileData['file']['name']  ?? null;

        // Step 3: Poll until ACTIVE (videos need processing time)
        $maxWait = 60;
        $waited  = 0;
        while ($fileState === 'PROCESSING' && $waited < $maxWait && $fileName) {
            sleep(3);
            $waited += 3;
            $poll      = Http::timeout(15)->get("https://generativelanguage.googleapis.com/v1beta/{$fileName}?key={$this->apiKey}");
            $fileState = $poll->json()['state'] ?? 'UNKNOWN';
            $fileUri   = $poll->json()['uri']   ?? $fileUri;
        }

        if ($fileState !== 'ACTIVE') {
            Log::warning("Gemini Files API: file not ACTIVE after {$waited}s. State: {$fileState}");
            return null;
        }

        return $fileUri;
    }

    /**
     * Constructs the strict JSON instruction prompt.
     */
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
            Attachments Summary for CURRENT email: " . ($attachmentContext ?: 'None') . "

            CRITICAL INSTRUCTION REGARDING EMAIL THREADS:
            The 'Body' text likely contains a history of previous quoted emails at the bottom (e.g., 'On [Date], [Name] wrote:').
            You must ONLY analyze the NEWEST message at the very top of the thread.
            Do NOT claim attachments are missing just because an older, quoted message at the bottom says 'I have attached...'. Ignore the historical quoted text when evaluating attachments. Focus ONLY on what the current sender is communicating right now.

            CRITICAL INSTRUCTION REGARDING VIDEO ATTACHMENTS:
            If a video file is provided, you MUST analyze its visual content — describe what is shown, identify any relevant text, reference numbers, dates, or events visible in the footage. Do NOT say the video is unsupported or cannot be analyzed. Treat it like any other evidence document.

            OBJECTIVE:
            Analyze the NEWEST email message and its attachments objectively.
            Provide structured insights to help the user understand the content and decide next steps.
            Do NOT make legal decisions.

            RETURN VALID JSON ONLY:
            {
                \"summary\": \"Brief, neutral explanation of what the NEWEST email communicates.\",
                \"email_type\": \"One of: User Submission, Institution Response, Acknowledgment, Denial, Information Request, Other.\",
                \"key_entities\": {
                    \"dates\": [\"List any dates mentioned in the newest email\"],
                    \"amounts\": [\"List monetary amounts mentioned\"],
                    \"reference_numbers\": [\"List case or transaction references if present\"]
                },
                \"attachment_analysis\": {
                    \"document_types_detected\": [\"Invoice, Statement, Rejection Letter, etc.\"],
                    \"important_details_extracted\": [\"Key extracted facts from attachments\"],
                    \"missing_or_unclear_items\": [\"Documents or details that appear missing or incomplete based ONLY on the newest message\"]
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
        // Added Timeout & Retry logic for stability
        $response = Http::timeout(60)
            ->retry(3, 2000) 
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [['parts' => $parts]]
            ]);

        if (!$response->successful()) {
            throw new \Exception("Gemini API Error: " . $response->body());
        }

        $rawText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $cleanJson = trim(str_replace(['```json', '```'], '', $rawText));
        $decoded = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to parse Gemini JSON output.");
        }

        return $decoded;
    }
}