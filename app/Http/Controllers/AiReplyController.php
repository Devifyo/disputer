<?php

namespace App\Http\Controllers;

use App\Models\Cases;
use App\Models\Email;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiReplyController extends Controller
{
    /**
     * Main endpoint to generate the AI email reply.
     */
    public function generate(Request $request, $case_id)
    {   
        // 1. Fetch Case securely
        try {
            $id = decrypt_id($case_id);
            $case = Cases::with('institution.category')->findOrFail($id);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid or missing Case ID'], 404);
        }

        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'API Key missing'], 500);
        }

        $isEscalationReq = $request->boolean('is_escalation');
        $isFollowUpReq = $request->boolean('is_followup');
        $isReply = $request->filled('reply_email_id');
        $userPrompt = $request->input('prompt', '');
        $existingSubject = $request->input('subject', '');

        // 2. Build Thread History Context
        $threadHistory = $this->buildConversationHistory($case, $request, $isFollowUpReq, $isReply);

        // 3. Resolve Step Key
        $currentStepKey = $this->resolveCurrentStepKey($case);

        // 4. Determine Tone and Context Guidelines
        $strategy = $this->determineToneAndContext($case, $currentStepKey, $isEscalationReq, $isFollowUpReq, $isReply);

        // 5. Build the strict AI Prompt
        $systemInstruction = $this->buildPrompt(
            $case, 
            $strategy['context'], 
            $strategy['tone'], 
            $currentStepKey, 
            $userPrompt, 
            $existingSubject, 
            $request->user()->name,
            $threadHistory
        );

        // 6. Call Gemini API
        $aiResponse = $this->callGeminiApi($systemInstruction, $apiKey);

        if (!$aiResponse) {
            return response()->json(['error' => 'Failed to generate content'], 500);
        }

        return response()->json($aiResponse);
    }

    /**
     * Builds a clean text summary of previous emails based on the action type.
     */
    private function buildConversationHistory(Cases $case, Request $request, bool $isFollowUp, bool $isReply): string
    {
        $historyText = "";

        if ($isFollowUp) {
            // ONLY fetch the last email sent by the user to follow up on
            $lastSent = Email::where('case_id', $case->id)->where('direction', 'outbound')->latest()->first();
            if ($lastSent) {
                $body = $this->cleanEmailBody($lastSent);
                $historyText = "PREVIOUS EMAIL SENT BY USER (You are writing a follow-up to this message):\nSubject: {$lastSent->subject}\nBody: {$body}\n";
            }
        } elseif ($isReply) {
            // ONLY fetch the specific email we are replying to
            $replyTo = Email::where('id', $request->input('reply_email_id'))->where('case_id', $case->id)->first();
            if ($replyTo) {
                $body = $this->cleanEmailBody($replyTo);
                $historyText = "EMAIL RECEIVED FROM INSTITUTION (You are replying directly to this message):\nSubject: {$replyTo->subject}\nBody: {$body}\n";
            }
        } else {
            // GENERAL DRAFT: Fetch the last 4 emails in the thread for full context
            $emails = Email::where('case_id', $case->id)->latest()->take(4)->get()->reverse();
            if ($emails->isNotEmpty()) {
                $historyText = "RECENT CONVERSATION HISTORY (Use this for context, do not repeat it):\n";
                foreach($emails as $email) {
                    $dir = $email->direction === 'inbound' ? 'Received from Institution' : 'Sent by User';
                    $body = $this->cleanEmailBody($email);
                    $historyText .= "[{$dir}] Subject: {$email->subject} | Body: {$body}\n---\n";
                }
            }
        }

        return $historyText;
    }

    /**
     * Helper to safely extract and shrink email text to save tokens.
     */
    private function cleanEmailBody(Email $email): string
    {
        $text = !empty($email->body_text) ? $email->body_text : strip_tags($email->body_html ?? '');
        return trim(preg_replace("/\s+/", " ", $text)); // Shrinks massive spaces/newlines into single spaces
    }

    /**
     * Mirrors the Livewire logic to securely determine the exact step key string.
     */
    private function resolveCurrentStepKey(Cases $case): string
    {
        $workflowConfig = $case->institution->category->workflow_config ?? [];
        $dbValue = $case->current_workflow_step;
        $initialStep = $workflowConfig['initial_step'] ?? 'draft';

        if (empty($dbValue) || !isset($workflowConfig['steps'][$dbValue])) {
            return $initialStep;
        }

        return $dbValue;
    }

    /**
     * Determines the exact context and tone needed based on the stage and flags.
     */
    private function determineToneAndContext(Cases $case, string $stepKey, bool $isEscalationReq, bool $isFollowUpReq, bool $isReply): array
    {
        $escalation = $case->escalation_level ?? 0;
        $contact = $case->institution->contacts()->where('step_key', $stepKey)->orderBy('is_primary', 'desc')->first();
        $dbTone = $contact ? $contact->tone : null;

        if ($isReply) {
            $baseTone = $dbTone ?? "professional, direct, and cooperative but firm";
            $context = "This is a direct reply to the institution's recent email. Address their specific points or requests clearly based on the provided thread history.";
        } elseif ($escalation > 0 || $isEscalationReq) {
            $baseTone = $dbTone ?? "cold, factual, and strictly professional";
            $context = "This email is a formal escalation to higher management or an ombudsman. Standard support failed. Briefly state what is owed based on the history and demand a final ruling and immediate intervention.";
        } elseif ($isFollowUpReq) {
            $baseTone = $dbTone ?? "firm and direct";
            $context = "This is a follow-up email. You are annoyed because they ignored the previous email provided in the history. Demand a status update.";
        } else {
            $baseTone = $dbTone ?? "polite, direct, and factual";
            $context = "This is a standard communication regarding a dispute. Use the conversation history provided to advance the case naturally.";
        }

        $finalTone = ucfirst($baseTone) . ". Write exactly like a normal person typing an email. Use plain, everyday English. Be ruthlessly professional. NO flowery apologies, NO overly emotional venting, and NO corporate AI jargon.";

        return [
            'tone' => $finalTone,
            'context' => $context
        ];
    }

    /**
     * Constructs the rigid prompt forcing Gemini to format strictly but write naturally.
     */
    private function buildPrompt(Cases $case, string $context, string $tone, string $stepKey, string $userPrompt, string $existingSubject, string $userName, string $threadHistory): string
    {
        $prompt = "You are drafting an email to {$case->institution_name} on behalf of a real person named {$userName}.\n" .
                  "Case Reference: {$case->case_reference_id}.\n" .
                  "Context: {$context}\n" .
                  "Tone: {$tone}\n\n";

        if (!empty($threadHistory)) {
            $prompt .= "=== THREAD DATA ===\n{$threadHistory}\n===================\n\n";
        }

        if (!empty($userPrompt)) {
            $prompt .= "User's specific instruction: \"{$userPrompt}\"\n\n";
        }

        $prompt .= "STRICT INSTRUCTIONS:\n" .
                   "1. Output ONLY a valid JSON object with exactly 2 keys: 'subject' and 'body'.\n" .
                   "2. 'subject': Write a concise, natural subject line (improve '{$existingSubject}' if provided).\n" .
                   "3. 'body': This is the email message. DO NOT use literal labels like '[Opening]' or '**Body**'. Write it as standard paragraphs formatted with \\n\\n for line breaks.\n" .
                   "4. The 'body' MUST seamlessly flow through these 4 structural parts as separate paragraphs:\n" .
                   "   - Paragraph 1 (Opening): A standard, natural greeting (e.g., Dear Customer Support, or Dear {$case->institution_name} Team,).\n" .
                   "   - Paragraph 2 (Body): Plainly state the facts of the dispute and respond to the Thread Data provided.\n" .
                   "   - Paragraph 3 (Request/Next Steps): Clearly and directly state exactly what action you want them to take right now.\n" .
                   "   - Paragraph 4 (Closing): A standard human sign-off (e.g., Best regards, {$userName} or Sincerely, {$userName}).\n" .
                   "5. NEVER mention internal terms like 'Stage 1', '{$stepKey}', 'Workflow', or 'Escalation Level'.";

        return $prompt;
    }

    /**
     * Executes the HTTP request to the Gemini API and parses the JSON.
     */
    private function callGeminiApi(string $systemInstruction, string $apiKey): ?array
    {
        try {
            $model = 'gemini-flash-latest'; 
            
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemInstruction]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $jsonString = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                if ($jsonString) {
                    $parsed = json_decode($jsonString, true);
                    if (isset($parsed['body'])) {
                        return [
                            'subject' => $parsed['subject'] ?? '',
                            'text' => $parsed['body']
                        ];
                    }
                }
            }
            
            Log::error('Gemini API Request Failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;

        } catch (\Exception $e) {
            Log::error('Gemini API Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }
}