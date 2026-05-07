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

        // 3. Detect hard-stop language in the institution's latest email — overrides all other tone logic
        $hardStop = $this->detectHardStop($case, $replyEmailId);

        // 4. Determine Tone and Context Guidelines (skipped if hard-stop detected)
        $strategy = $hardStop
            ? $this->hardStopStrategy()
            : $this->determineToneAndContext($case, $currentStepKey, $isEscalationReq, $isFollowUpReq, $isReply);

        // 5. Build the strict AI Prompt
        $systemInstruction = $this->buildPrompt(
            $case,
            $strategy['context'],
            $strategy['tone'],
            $currentStepKey,
            $userPrompt,
            $existingSubject,
            $userName,
            $threadHistory,
            $hardStop
        );

        // 6. Process any uploaded attachments into Gemini inline_data parts
        $attachmentParts = $this->buildAttachmentParts($params['attachments'] ?? []);

        if (!empty($attachmentParts)) {
            $systemInstruction .= "\n6. ATTACHED DOCUMENTS: The user has attached files to this email. Read them carefully and reference specific details — amounts, dates, reference numbers, names — where relevant to strengthen the email.";
        }

        // 7. Call Gemini API
        return $this->callGeminiApi($systemInstruction, $apiKey, $attachmentParts);
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

    private function detectHardStop(Cases $case, ?int $replyEmailId): bool
    {
        $triggers = [
            'final notice', 'final decision', 'final response', 'final answer', 'final determination',
            'no longer respond', 'will not respond', 'cannot respond further', 'will not be responding',
            'unable to assist further', 'unable to help further', 'unable to engage further',
            'matter closed', 'case closed', 'file closed', 'consider this matter closed', 'this matter is closed',
            'our decision is final', 'our position is final', 'position stands', 'decision stands',
            'will not be reconsidered', 'will not reconsider', 'no further action',
            'referred to collections', 'sent to collections', 'debt collection', 'collections agency',
            'legal action', 'legal proceedings', 'commence proceedings', 'litigation',
            'no further correspondence', 'no further communication', 'exhausted all options',
            'write to the ombudsman', 'contact the ombudsman',
        ];

        $emailsToCheck = collect();

        // Always check the specific email being replied to
        if ($replyEmailId) {
            $specific = Email::where('id', $replyEmailId)->where('case_id', $case->id)->first();
            if ($specific) {
                $emailsToCheck->push($specific);
            }
        }

        // Also check the most recent inbound email regardless
        $lastInbound = Email::where('case_id', $case->id)
            ->where('direction', 'inbound')
            ->latest()
            ->first();

        if ($lastInbound) {
            $emailsToCheck->push($lastInbound);
        }

        foreach ($emailsToCheck as $email) {
            $text = strtolower($this->cleanEmailBody($email));
            foreach ($triggers as $trigger) {
                if (str_contains($text, $trigger)) {
                    Log::info('Hard-stop trigger detected', ['case_id' => $case->id, 'trigger' => $trigger]);
                    return true;
                }
            }
        }

        return false;
    }

    private function hardStopStrategy(): array
    {
        return [
            'tone' => "Cold, extremely formal, and legally precise. Zero warmth. Write as though a solicitor is dictating. Every sentence has a deliberate purpose. No filler, no pleasantries beyond a one-line formal greeting.",
            'context' =>
                "HARD STOP: The institution has issued what appears to be a final refusal, a closure notice, a collections threat, or has stated they will no longer respond. " .
                "You must NOT negotiate, apologise, ask clarifying questions, or show any willingness to continue the current dialogue. " .
                "Draft a formal escalation notice structured as follows:\n" .
                "  1. Acknowledge their final position in a single, cold sentence.\n" .
                "  2. State clearly that this matter is now being formally escalated to the appropriate external authority — the exact body (regulator, statutory ombudsperson, court, or tribunal) is dictated by the Category Fence further down in this prompt.\n" .
                "  3. Reference specific facts from the thread (dates, amounts, case references, their exact stated position).\n" .
                "  4. State that all correspondence is being preserved as evidence for the formal complaint.\n" .
                "  5. Give them one final opportunity to resolve within 48 hours before the complaint is filed — state this as a deadline, not a request.",
        ];
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
            $context = "This email is a formal escalation. Prior attempts to resolve the matter through standard channels have not produced a remedy. Briefly state what is being sought based on the history and request a final, written determination on the matter. The exact escalation target (regulator, ombudsperson, court, tribunal) and the framing of the request are governed by the Category Fence further down in this prompt — follow it.";
        } elseif ($isFollowUpReq) {
            $baseTone = $dbTone ?? "firm and direct";
            $context = "This is a follow-up email. The previous correspondence in the thread history has not been addressed. Request a status update in firm but appropriate terms — the appropriate register (assertive consumer vs. formal civic) is governed by the Category Fence further down in this prompt.";
        } else {
            $baseTone = $dbTone ?? "polite, direct, and factual";
            $context = "This is a standard communication regarding a dispute. Use the conversation history provided to advance the case naturally.";
        }

        $finalTone = ucfirst($baseTone) . ". Write exactly like a normal person typing an email. Use plain, everyday English. Be ruthlessly professional. NO flowery apologies, NO overly emotional venting, and NO corporate AI jargon.";

        return ['tone' => $finalTone, 'context' => $context];
    }

    private function buildPrompt(Cases $case, string $context, string $tone, string $stepKey, string $userPrompt, string $existingSubject, string $userName, string $threadHistory, bool $hardStop = false): string
    {
        $categoryName = $case->institution->category->name ?? 'Unknown';

        $prompt = "You are drafting an email to {$case->institution_name} on behalf of a real person named {$userName}.\n" .
                  "Case Reference: {$case->case_reference_id}.\n" .
                  "Institution Category: {$categoryName}.\n" .
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
                   "2. 'subject': Write a firm, formal subject line that signals escalation (e.g., 'Formal Escalation Notice – Case Ref {$case->case_reference_id}').\n" .
                   "3. 'body': This is the email message. Write it as plain text only — NO markdown whatsoever. No asterisks (*), no underscores (_), no hash symbols (#), no bullet points, no bold, no italic. Use \\n\\n for paragraph breaks. DO NOT use literal labels like '[Opening]'.\n";

        if ($hardStop) {
            $prompt .=
                "4. *** HARD STOP PROTOCOL — THIS OVERRIDES ALL OTHER STRUCTURAL INSTRUCTIONS ***\n" .
                "   The institution has issued a final refusal, closure notice, collections threat, or declared they will no longer respond.\n" .
                "   YOU MUST NOT: negotiate, apologise, ask clarifying questions, or express any desire to continue the current dialogue.\n" .
                "   The 'body' MUST follow this exact 5-part structure as separate paragraphs:\n" .
                "   - Paragraph 1: One cold, formal sentence acknowledging their stated final position.\n" .
                "   - Paragraph 2: State that this matter is being formally escalated, and name the appropriate external authority for this institution type — selected per the Category Fence below (a regulator, statutory ombudsperson, court, or tribunal — never a private-sector ombudsman if the institution is a government body).\n" .
                "   - Paragraph 3: Cite specific facts from the Thread Data — exact dates, amounts, reference numbers, and the institution's own words — that will form the basis of the complaint.\n" .
                "   - Paragraph 4: State that all correspondence is being preserved as evidence and will be submitted with the formal complaint.\n" .
                "   - Paragraph 5: Give them a final 48-hour deadline to resolve the matter before the complaint is filed. State this as a deadline, not a request. Then a cold formal sign-off ({$userName}).\n" .
                "5. NEVER mention internal terms like 'Stage 1', '{$stepKey}', 'Workflow', or 'Escalation Level'.\n";
        } else {
            $prompt .=
                "4. The 'body' MUST seamlessly flow through these 4 structural parts as separate paragraphs:\n" .
                "   - Paragraph 1 (Opening): A standard, natural greeting.\n" .
                "   - Paragraph 2 (Body): Plainly state the facts of the dispute and respond to the Thread Data provided.\n" .
                "   - Paragraph 3 (Request/Next Steps): Clearly and directly state exactly what action you want them to take right now.\n" .
                "   - Paragraph 4 (Closing): A standard human sign-off (e.g., Best regards, {$userName}).\n" .
                "5. NEVER mention internal terms like 'Stage 1', '{$stepKey}', 'Workflow', or 'Escalation Level'.\n";
        }

        $prompt .= "\n" . $this->categoryFence($categoryName);

        return $prompt;
    }

    /**
     * Category Fence — final, authoritative tone/register guardrails.
     * The AI inspects the Institution Category at the top of the prompt and
     * picks the correct register (Law-Abiding Citizen vs. Fierce Consumer Advocate)
     * based on whether the institution is a government body or a commercial provider.
     */
    private function categoryFence(string $categoryName): string
    {
        return
            "=== CATEGORY FENCE (FINAL, AUTHORITATIVE — OVERRIDES ANY CONFLICTING TONE ABOVE) ===\n" .
            "Look at the Institution Category at the top of this prompt (\"{$categoryName}\") and decide whether this institution is a GOVERNMENT BODY or a COMMERCIAL PROVIDER. Then apply the matching fence below. Never mix the two registers.\n\n" .
            "IF GOVERNMENT BODY (e.g. a municipality, city, town, county, federal/provincial/state agency, public authority, tax authority, or any taxpayer-funded institution):\n" .
            "  - Persona: a Law-Abiding Citizen exercising their statutory rights. Strictly formal, procedural, deferential to due process. No commercial-consumer combativeness.\n" .
            "  - DO NOT use phrases like \"standard support has failed\", \"your support team\", \"customer service\", \"I demand a final ruling\", or anything that frames the institution as a private vendor. Government bodies do not have \"support teams\" and the citizen does not \"demand\" — they invoke or request under statute.\n" .
            "  - REQUIRED PHRASING SUBSTITUTIONS (apply these even if the Context above suggested otherwise — never use the left-hand wording in a government letter):\n" .
            "      • Instead of \"This is a formal escalation\" or \"This email is a formal escalation\" → write \"I am writing regarding this claim\".\n" .
            "      • Instead of \"full reimbursement has not been issued\", \"payment has not been made\", or similar vendor-style accusations → write \"the claim remains unresolved\".\n" .
            "      • Instead of \"the appropriate tribunal\", \"the relevant tribunal\", or any vague tribunal reference → write \"Small Claims Court\" (preferred), or \"available legal remedies\" if the specific court is genuinely unclear.\n" .
            "      These substitutions are mandatory; do not paraphrase your way back into the disallowed phrasing.\n" .
            "  - Frame requests as a citizen invoking rights under the relevant statute, bylaw, or regulation that governs this body (e.g., the applicable Municipal Act for a city, the governing legislation or charter for an agency). If you do not know the exact statute, refer to it generically as \"the applicable governing legislation\" rather than inventing a name.\n" .
            "  - When escalating, refer to the correct legal channel for a public body — Small Claims Court, judicial review, or a statutory ombudsperson where one demonstrably exists for that body. NEVER escalate a government dispute to a private-sector \"ombudsman\".\n\n" .
            "IF COMMERCIAL PROVIDER (e.g. bank, airline, telecom, ISP, insurance, fintech, or any private business):\n" .
            "  - Persona: a Fierce Consumer Advocate. Keep the assertive, firm tone established above.\n" .
            "  - Cite the consumer-protection regulation that applies to that industry (e.g., air passenger protection regulations for airlines, banking conduct rules for banks, telecom regulator rules for ISPs). If you do not know the exact regulation, refer to it generically as \"the applicable consumer-protection regulations for this industry\" rather than inventing a name.\n" .
            "  - When escalating, name the correct industry ombudsman or regulator for that sector (e.g., the relevant transportation regulator for airlines, the financial ombudsman for banks).\n\n" .
            "If the category is genuinely ambiguous, default to the more formal Law-Abiding Citizen register — it is safer to be too formal than to address a public body as a vendor.";
    }

    private function buildAttachmentParts(array $files): array
    {
        $parts = [];

        // MIME types Gemini can read natively
        $inlineTypes = [
            'application/pdf',
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        ];
        $textTypes = ['text/plain', 'text/csv'];

        foreach ($files as $file) {
            if (!($file instanceof \Illuminate\Http\UploadedFile)) {
                continue;
            }

            $mime = $file->getMimeType();
            $size = $file->getSize();

            // Skip files over 8 MB to stay well under Gemini's inline_data limit
            if ($size > 8 * 1024 * 1024) {
                Log::warning('AI attachment skipped (too large)', ['file' => $file->getClientOriginalName(), 'size' => $size]);
                continue;
            }

            if (in_array($mime, $textTypes)) {
                $content = file_get_contents($file->getRealPath());
                $parts[] = ['text' => "ATTACHED FILE ({$file->getClientOriginalName()}):\n{$content}"];
            } elseif (in_array($mime, $inlineTypes)) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mime,
                        'data'      => base64_encode(file_get_contents($file->getRealPath())),
                    ],
                ];
            }
            // Unsupported types (DOCX, XLSX, etc.) are silently skipped
        }

        return $parts;
    }

    private function callGeminiApi(string $systemInstruction, string $apiKey, array $attachmentParts = []): ?array
    {
        try {
            $model    = 'gemini-2.5-flash';
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(60)
                ->retry(3, 2000, function (\Exception $e, $request) {
                    // Retry on connection errors OR Gemini rate-limit (429) / overload (503)
                    if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
                        return true;
                    }
                    if ($e instanceof \Illuminate\Http\Client\RequestException) {
                        return in_array($e->response->status(), [429, 503]);
                    }
                    return false;
                }, throw: false)
                ->post($endpoint, [
                    'contents'         => [['parts' => array_merge([['text' => $systemInstruction]], $attachmentParts)]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'thinkingConfig'   => ['thinkingBudget' => 0],
                    ],
                ]);

            if ($response->successful()) {
                $data       = $response->json();
                $jsonString = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

                if ($jsonString) {
                    $parsed = json_decode($jsonString, true);
                    if (isset($parsed['body'])) {
                        $body = preg_replace('/\*{1,3}([^*]+)\*{1,3}/', '$1', $parsed['body']);
                        $body = preg_replace('/_{1,2}([^_]+)_{1,2}/', '$1', $body);
                        return ['subject' => $parsed['subject'] ?? '', 'text' => $body];
                    }
                    Log::warning('Gemini response missing body key', ['parsed' => $parsed]);
                }
            }

            Log::error('Gemini API Request Failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('Gemini API Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }
}