<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Institution, Cases, InstitutionContact};
use App\Services\{CaseService, SendEmailService};
use Barryvdh\DomPDF\Facade\Pdf;
class CaseController extends Controller
{   
    protected $caseService;
    protected $emailService;
    public function __construct(CaseService $caseService, SendEmailService $emailService)
    {
        $this->caseService = $caseService;
        $this->emailService = $emailService;
    }


    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status', 'category']);
        $cases = $this->caseService->getUserCases($filters);

        return view('user.cases.index', compact('cases'));
    }

    public function show($case_reference_id)
    {
        $case = $this->caseService->getCaseByReference($case_reference_id);
        $escalationService = new \App\Services\EscalationService();
        $escalationDetails = $escalationService->getEscalationDetails($case);
        $metadata = $this->caseService->extractCaseMetadata($case);
        
        //workflow visualization data
        $workflow = $this->caseService->getWorkflowDetails($case);
        $recipientData = $case->institution->getStepRecipient($workflow['current_step_key']);
        // Handle Email and Fallback Logic
        $recipientEmail = '';
        $recipientUrl = '';

        if ($recipientData) {
            if ($recipientData['type'] === 'email') {
                $recipientEmail = $recipientData['value'];
            } elseif ($recipientData['type'] === 'url') {
                $recipientUrl = $recipientData['value'];
                // Auto-fill the fallback email if the primary type is a URL
                $recipientEmail = $recipientData['fallback_email'] ?? '';
            }
        }

       return view('user.cases.show', compact('case', 'metadata', 'workflow', 'escalationDetails', 'recipientData','recipientEmail', 'recipientUrl'));
    }

    /**
     * Show the Institution Selection Wizard
     */
    public function createStep1()
    {   
        $popular = Institution::where('is_verified', true)->limit(4)->get();
        $categories = \App\Models\InstitutionCategory::orderBy('name')->get();

        return view('user.cases.create_wizard', compact('popular','categories'));
    }

    /**
     * API: Handle the AJAX Search
     */
    public function searchInstitutions(Request $request)
    {
        $query = $request->get('q');

        if (!$query) {
            return response()->json([]);
        }

        // Search logic using the Model you provided
        $institutions = Institution::with('category') 
            ->where('name', 'LIKE', "%{$query}%")
            ->where('is_verified', true) 
            ->limit(5)
            ->get();

        return response()->json($institutions);
    }

    public function sendEmail(Request $request, $casId)
    {   
        if (!isEmailConfigured()) {
            return back()
                ->with('error', 'Your email settings are incomplete. Please configure SMTP & IMAP in your profile.')
                ->with('smtp_missing', true);
        }

        $request->validate([
            'recipient' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'attachments' => 'array',
            'attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'is_escalation' => 'nullable|boolean',
            'save_contact' => 'nullable|in:0,1'
        ]);

        try {
            $caseId = decrypt_id($casId);
            $case = Cases::find($caseId);
        } catch (\Exception $e) {
            return back()->with('error', 'Invalid Case ID');
        }

        if(!$case){
            return back()->with('error', 'Case not found!');
        }

        // 1. CHECK INTENT
        $isEscalation = $request->boolean('is_escalation');
        $isFollowUp = $request->boolean('is_followup');
        try {
            $files = $request->file('attachments') ?? [];

            // 2. PREPARE TIMELINE OVERRIDES
            // If escalation, we tell the service to log it differently
            $timelineOverrides = [];
            $newLevel = $case->escalation_level; // Default to current

            if ($isEscalation) {
                $newLevel = $case->escalation_level + 1;
                $timelineOverrides = [
                    'type' => 'escalation_sent',
                    'description' => "Formal Escalation (Level {$newLevel}) initiated via email.",
                    'metadata' => [
                        'level' => $newLevel,
                        'escalation_intent' => true
                    ]
                ];
            }

            if ($isFollowUp) {
                $timelineOverrides = [
                    'type' => 'email_sent',
                    'description' => "Follow-up sent regarding Level {$case->escalation_level} escalation.",
                    'metadata' => [
                        'is_followup' => true,
                        'level' => $case->escalation_level
                    ]
                ];
            }

            // 3. SEND EMAIL 
            $this->emailService->sendAndLog(
                auth()->user(),
                $case,
                $request->recipient,
                $request->subject,
                $request->body,
                $files,
                null, 
                $timelineOverrides 
            );

            // ==========================================
            // NEW: SAVE CONTACT IF USER CONFIRMED
            // ==========================================
            if ($request->input('save_contact') == '1' && $case->institution_id) {
                    InstitutionContact::updateOrCreate(
                    [
                        // Match existing contact for this institution, step, and channel
                        'institution_id' => $case->institution_id,
                        'step_key' => $case->current_workflow_step,
                        'channel' => 'email'
                    ],
                    [
                        // Update or create with these values
                        'contact_value' => $request->recipient,
                        'is_primary' => true,
                        'department_name' => ucwords(str_replace('_', ' ', $case->current_workflow_step)) . ' Contact',
                        'tone' => 'firm'
                    ]
                );
            }
            // ==========================================

            // 4. UPDATE CASE STATE
            if ($isEscalation) {
                $case->timestamps = false;
                $case->update([
                    'escalation_level' => $newLevel,
                    'last_escalated_at' => now(),
                    'status' => \App\Enums\CaseStatus::ESCALATED 
                ]);
                $case->timestamps = true;
            }

            $message = $isEscalation ? 'Escalation initiated successfully.' : 'Email sent successfully.';
            return back()->with('success', $message);

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function exportPdf($id)
    {
        $realId = decrypt_id($id); 
        $case = Cases::with(['timeline.email', 'institution'])->findOrFail($realId);
        $metadata = $this->caseService->extractCaseMetadata($case);

        $publicTimeline = $case->timeline->filter(function($log) {
            return !in_array($log->type, ['Ai_guidance_workflow', 'system_suggestion', 'debug_log']);
        });

        $pdf = Pdf::loadView('user.cases.pdf', compact('case', 'metadata', 'publicTimeline'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('Dispute_Case_' . $case->case_reference_id . '.pdf');
    }

    
}
