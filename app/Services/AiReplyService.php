<?php

namespace App\Services;

use App\Models\Cases;
use App\Models\Email;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiReplyService
{
    /**
     * Main method to generate the AI draft.
     */
    public function generateDraft(Cases $case, array $params): ?array
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            Log::error('Gemini API Key missing.');
            return null;
        }

        $isEscalationReq = $params['is_escalation'] ?? false;
        $isFollowUpReq = $params['is_followup'] ?? false;
        $replyEmailId = $params['reply_email_id'] ?? null;
        $isReply = !empty($replyEmailId);
        $userPrompt = $params['prompt'] ?? '';
        $existingSubject = $params['subject'] ?? '';
        $userName = $params['user_name'] ?? 'Customer';

        // 1. Build Thread History Context
        $threadHistory = $this->buildConversationHistory($case, $isFollowUpReq, $isReply, $replyEmailId);

        // 2. Resolve Step Key
        $currentStepKey = $this->resolveCurrentStepKey($case);

        // 3. Determine Tone and Context Guidelines
        $strategy = $this->determineToneAndContext($case, $currentStepKey, $isEscalationReq, $isFollowUpReq, $isReply);

        // 4. Build the strict AI Prompt
        $systemInstruction = $this->buildPrompt(
            $case, 
            $strategy['context'], 
            $strategy['tone'], 
            $currentStepKey, 
            $userPrompt, 
            $existingSubject, 
            $userName,
            $threadHistory
        );

        // 5. Call Gemini API
        return $this->callGeminiApi($systemInstruction, $apiKey);
    }

    private function buildConversationHistory(Cases $case, bool $isFollowUp, bool $isReply, ?int $replyEmailId): string
    {
        $historyText = "";

        if ($isFollowUp) {
            $lastSent = Email::where('case_id', $case->id)->where('direction', 'outbound')->latest()->first();
            if ($lastSent) {
                $body = $this->cleanEmailBody($lastSent);
                $historyText = "PREVIOUS EMAIL SENT BY USER (You are writing a follow-up to this message):\nSubject: {$lastSent->subject}\nBody: {$body}\n";
            }
        } elseif ($isReply && $replyEmailId) {
            $replyTo = Email::where('id', $replyEmailId)->where('case_id', $case->id)->first();
            if ($replyTo) {
                $body = $this->cleanEmailBody($replyTo);
                $historyText = "EMAIL RECEIVED FROM INSTITUTION (You are replying directly to this message):\nSubject: {$replyTo->subject}\nBody: {$body}\n";
            }
        } else {
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

    private function cleanEmailBody(Email $email): string
    {
        $text = !empty($email->body_text) ? $email->body_text : strip_tags($email->body_html ?? '');
        return trim(preg_replace("/\s+/", " ", $text));
    }

    private function resolveCurrentStepKey(Cases $case): string
    {
        $workflowConfig = $case->institution->category->workflow_config ?? [];
        $dbValue = $case->current_workflow_step;
        return (empty($dbValue) || !isset($workflowConfig['steps'][$dbValue])) 
            ? ($workflowConfig['initial_step'] ?? 'draft') 
            : $dbValue;
    }

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

        return ['tone' => $finalTone, 'context' => $context];
    }

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
                   "   - Paragraph 1 (Opening): A standard, natural greeting.\n" .
                   "   - Paragraph 2 (Body): Plainly state the facts of the dispute and respond to the Thread Data provided.\n" .
                   "   - Paragraph 3 (Request/Next Steps): Clearly and directly state exactly what action you want them to take right now.\n" .
                   "   - Paragraph 4 (Closing): A standard human sign-off (e.g., Best regards, {$userName}).\n" .
                   "5. NEVER mention internal terms like 'Stage 1', '{$stepKey}', 'Workflow', or 'Escalation Level'.";

        return $prompt;
    }

    private function callGeminiApi(string $systemInstruction, string $apiKey): ?array
    {
        try {
            // $model = 'gemini-flash-latest'; 
            $model = 'gemini-2.5-flash';
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $response = Http::timeout(30)->retry(3, 1000)->post($endpoint, [
                'contents' => [['parts' => [['text' => $systemInstruction]]]],
                'generationConfig' => ['responseMimeType' => 'application/json']
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $jsonString = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                if ($jsonString) {
                    $parsed = json_decode($jsonString, true);
                    if (isset($parsed['body'])) {
                        return ['subject' => $parsed['subject'] ?? '', 'text' => $parsed['body']];
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