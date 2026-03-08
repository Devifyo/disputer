<?php

namespace App\Http\Controllers;

use App\Models\Cases;
use App\Services\AiReplyService;
use Illuminate\Http\Request;

class AiReplyController extends Controller
{
    /**
     * Main endpoint to generate the AI email reply.
     */
    public function generate(Request $request, $case_id, AiReplyService $aiService)
    {   
        try {
            $id = decrypt_id($case_id);
            $case = Cases::with('institution.category')->findOrFail($id);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid or missing Case ID'], 404);
        }

        // Gather all parameters from the request
        $params = [
            'is_escalation'  => $request->boolean('is_escalation'),
            'is_followup'    => $request->boolean('is_followup'),
            'reply_email_id' => $request->input('reply_email_id'),
            'prompt'         => $request->input('prompt', ''),
            'subject'        => $request->input('subject', ''),
            'user_name'      => $request->user()->name,
        ];

        // Call the service
        $aiResponse = $aiService->generateDraft($case, $params);

        if (!$aiResponse) {
            // Note: Our auto-retry JS will handle this gracefully on the frontend!
            return response()->json(['error' => 'Failed to generate content'], 500);
        }

        return response()->json($aiResponse);
    }
}