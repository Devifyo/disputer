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
        
        $metadata = $this->caseService->extractCaseMetadata($case);
        
        // NEW: Get workflow visualization data
        $workflow = $this->caseService->getWorkflowDetails($case);

        return view('user.cases.show', compact('case', 'metadata', 'workflow'));
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

    public function sendEmail(Request $request, Cases $case)
    {   

        if (!isEmailConfigured()) {
        return back()
            ->with('error', 'Your email settings are incomplete. Please configure SMTP & IMAP in your profile.')
            ->with('smtp_missing', true); // Optional: forces the alert to show if not already visible
       }
        $request->validate([
            'recipient' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'attachments' => 'array',
            'attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240', // 10MB Max per file
        ]);

        try {
            // Handle Multiple Attachments
            $files = $request->file('attachments') ?? [];

            $this->emailService->sendAndLog(
                auth()->user(),
                $case,
                $request->recipient,
                $request->subject,
                $request->body,
                $files
            );

            return back()->with('success', 'Email sent successfully via your SMTP server.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
