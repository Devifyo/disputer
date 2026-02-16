<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Institution, Cases}; // Import your Model
use App\Services\CaseService;
use App\Services\SendEmailService;
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
        
        // NEW: Get workflow visualization data
        $workflow = $this->caseService->getWorkflowDetails($case);

       return view('user.cases.show', compact('case', 'metadata', 'workflow', 'escalationDetails'));
    }

    /**
     * Step 1: Show the Institution Selection Wizard
     */
    public function createStep1()
    {   
        // Pass popular institutions for the "Quick Pick" section
        $popular = Institution::where('is_verified', true)->limit(4)->get();

        // 2. All Categories (for when user creates a custom institution)
        // We pluck them to make a simple dropdown list
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
        $institutions = Institution::with('category') // Eager load category
            ->where('name', 'LIKE', "%{$query}%")
            ->where('is_verified', true) // Only show verified ones in search
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
            'is_escalation' => 'nullable|boolean' // Validate as boolean (accepts "1", "0", "true", "false")
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

            // 3. SEND EMAIL (Pass overrides to avoid duplicate logs)
            $this->emailService->sendAndLog(
                auth()->user(),
                $case,
                $request->recipient,
                $request->subject,
                $request->body,
                $files,
                null, // Parent Email (null for new emails)
                $timelineOverrides // <--- Pass the config here
            );

            // 4. UPDATE CASE STATE (Business Logic)
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
}
