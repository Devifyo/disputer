<?php

namespace App\Livewire\Cases;

use Livewire\Component;
use App\Models\Institution;
use App\Models\InstitutionCategory;
use App\Models\Cases;
use App\Models\CaseTimeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use App\Models\Attachment;
class CreateDispute extends Component
{   
    use WithFileUploads;
    public $step = 1;

    // Step 1 Data
    public $query = '';
    public $results;
    public $mode = 'search';
    public $popular = [];
    public $selectedInstitutionId = null;
    public $selectedInstitutionName = '';
    public $customName = '';
    public $categoryId = '';
    public $customCategoryName = '';
    public $categories = [];

    // Step 2 Data
    public $transactionDate;
    public $transactionAmount;
    public $referenceNumber;
    public $issueDescription;

    // Step 3 Data (UPDATED)
    public $generatedSubject = ''; // New Field
    public $generatedLetter = '';
    public $institutionEmail = '';
    public $attachments = [];
    public function mount()
    {
        $this->results = collect();
        $this->popular = Institution::where('is_verified', true)->limit(4)->get();
        $this->categories = InstitutionCategory::orderBy('name')->get();
    }

    // ... [Keep updatedQuery, selectExisting, enableCreateMode, submitCustom same as before] ...
    public function updatedQuery()
    {
        $searchTerm = trim($this->query);
        if (strlen($searchTerm) >= 1) {
            $this->results = Institution::with('category')
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                ->where('is_verified', true)
                ->limit(5)
                ->get();
        } else {
            $this->results = collect();
        }
    }

    public function selectExisting($id, $name)
    {
        $this->selectedInstitutionId = $id;
        $this->selectedInstitutionName = $name;
        $institution = Institution::find($id);
        if ($institution && $institution->contact_email) {
            $this->institutionEmail = $institution->contact_email;
        } else {
            $this->institutionEmail = ''; // Reset if no email exists
        }

        $this->goToStep(2);
    }

    public function enableCreateMode()
    {
        $this->mode = 'create_custom';
        $this->customName = ucfirst($this->query);
    }

    public function submitCustom()
    {
        $rules = [
            'customName' => 'required|min:2|unique:institutions,name',
            'categoryId' => 'required',
        ];

        if ($this->categoryId === 'other') {
            $rules['customCategoryName'] = 'required|min:2|unique:institution_categories,name';
        }

        $this->validate($rules, [
            'customName.unique' => 'This institution is already listed. Please search for it instead.',
            'customCategoryName.unique' => 'This industry already exists. Please select it from the dropdown.',
        ]);

        $this->customName = ucfirst(trim($this->customName));

        if($this->customCategoryName) {
            $this->customCategoryName = ucfirst(trim($this->customCategoryName));
        }

        $this->selectedInstitutionName = $this->customName;
        $this->selectedInstitutionId = null;

        $this->goToStep(2);
    }

    // --- UPDATED LOGIC ---

    public function generateReview()
    {
        $this->validate([
            'transactionDate' => 'required|date',
            'transactionAmount' => 'required|numeric|min:0',
            'issueDescription' => 'required|min:10',
            'attachments.*' => 'nullable|file|max:10240',
        ]);

        // 1. Get raw AI response
        $rawContent = $this->generateDisputeLetter();
        if ($rawContent) {
            // 2. Parse Subject and Body
            // We expect the AI to return "Subject: ... \n Body..."
            if (preg_match('/Subject:(.*?)\n(.*)/s', $rawContent, $matches)) {
                $this->generatedSubject = trim($matches[1]);
                $this->generatedLetter = trim($matches[2]);
            } else {
                // Fallback if parsing fails
                $this->generatedSubject = "Dispute regarding transaction on {$this->transactionDate}";
                $this->generatedLetter = $rawContent;
            }
        } else {
            $this->generatedSubject = "Dispute Request";
            $this->generatedLetter = "AI Generation Failed. Please write your letter here.";
        }

        $this->goToStep(3);
    }

    public function finalizeDispute()
    {
        $this->validate([
            'institutionEmail' => 'required|email',
            'generatedSubject' => 'required|min:5',
            'generatedLetter'  => 'required|min:10',
        ]);

        DB::transaction(function () {
            
            // ====================================================
            // 1. HANDLE INSTITUTION & CATEGORY LOGIC
            // ====================================================
            
            $finalCategoryId = null;

            if (is_null($this->selectedInstitutionId)) {
                // User creates a NEW Institution
                
                $finalCategoryId = $this->categoryId;

                // A. User created a completely NEW Category (e.g., "Crypto")
                if ($this->categoryId === 'other') {
                    $defaultWorkflow = config('workflow_templates.standard');

                    $newCat = InstitutionCategory::firstOrCreate(
                        ['name' => ucfirst($this->customCategoryName)],
                        [
                            'slug' => Str::slug($this->customCategoryName),
                            'workflow_config' => $defaultWorkflow, // Save Template
                            'is_verified' => false 
                        ]
                    );
                    $finalCategoryId = $newCat->id;
                }

                // B. Create the Institution
                $newInst = Institution::create([
                    'name' => $this->selectedInstitutionName,
                    'institution_category_id' => $finalCategoryId,
                    'is_verified' => false,
                ]);
                $this->selectedInstitutionId = $newInst->id;
                
            } else {
                // User selected an EXISTING Institution
                // We must look up the category ID from the existing record
                $institution = Institution::find($this->selectedInstitutionId);
                if ($institution) {
                    $finalCategoryId = $institution->institution_category_id;
                }
            }

            // ====================================================
            // 2. CALCULATE WORKFLOW DEADLINE (The Fix)
            // ====================================================

            // Try to load category config, fallback to standard template if missing
            $category = InstitutionCategory::find($finalCategoryId);
            $workflowConfig = $category->workflow_config ?? config('workflow_templates.standard');

            // Get wait days for Step 1 (Index 0). Default to 14 days if config is broken.
            $stepOneWaitDays = $workflowConfig['steps'][0]['wait_days'] ?? 14;
            $nextActionDate = now()->addDays($stepOneWaitDays);

            // ====================================================
            // 3. CREATE THE CASE
            // ====================================================

            $case = Cases::create([
                'user_id' => Auth::id(),
                'institution_id' => $this->selectedInstitutionId,
                'institution_name' => $this->selectedInstitutionName,
                'case_reference_id' => strtoupper(Str::random(6)), 
                'email_route_id' => (string) Str::uuid(), 
                'status' => \App\Enums\CaseStatus::SENT,
                'stage' => 'Sent',
                'current_workflow_step' => 1,
                'next_action_at' => $nextActionDate, // <--- SAVED CORRECTLY NOW
            ]);

            // ====================================================
            // 4. SAVE ATTACHMENTS
            // ====================================================

            if (!empty($this->attachments)) {
                foreach ($this->attachments as $file) {
                    $path = $file->store('attachments', 'public');

                    Attachment::create([
                        'case_id' => $case->id,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'ai_analysis_status' => 'pending',
                    ]);
                }
            }

            // ====================================================
            // 5. LOG TIMELINE EVENTS
            // ====================================================

            // Log Creation
            CaseTimeline::create([
                'case_id' => $case->id,
                'type' => 'case_created',            
                'actor' => 'User',                   
                'description' => "Dispute created & sent to {$this->institutionEmail}", 
                'occurred_at' => now(),              
                'metadata' => [                      
                    'amount' => $this->transactionAmount,
                    'transaction_date' => $this->transactionDate,
                    'reference_number' => $this->referenceNumber ?? 'N/A',
                    'institution_email' => $this->institutionEmail
                ]
            ]);

            // Log Email Sent
            CaseTimeline::create([
                'case_id' => $case->id,
                'type' => 'email_sent',
                'actor' => 'System',
                'description' => 'Formal dispute notice generated and logged.', 
                'occurred_at' => now()->addSecond(),
                'metadata' => [
                    'recipient' => $this->institutionEmail,
                    'subject' => $this->generatedSubject,
                    // Storing body might be heavy, consider storing snippet or file ref
                    'full_body' => $this->generatedLetter
                ]
            ]);
        });

        session()->flash('message', 'Dispute Sent Successfully!');
        return redirect()->route('user.dashboard');
    }

    public function removeAttachment($index)
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    private function generateDisputeLetter()
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            \Log::error('Gemini API Error: API Key is missing.');
            return null;
        }

        $user = Auth::user();

        $prompt = "Write a formal dispute email to {$this->selectedInstitutionName}. " .
                  "My name is {$user->name}. " .
                  "Transaction Date: {$this->transactionDate}. " .
                  "Amount: \${$this->transactionAmount}. " .
                  "Ref Number: " . ($this->referenceNumber ?? 'N/A') . ". " .
                  "Issue: {$this->issueDescription}. " .
                  "Tone: Professional and firm. " .
                  "IMPORTANT FORMATTING RULES: " .
                  "1. Start response strictly with 'Subject: [Subject Here]'. " .
                  "2. Do NOT use markdown formatting (no **bold**, no *italics*, no lists with *). " .
                  "3. Use plain text only. Use dashes (-) for lists if needed.";

        try {
            // Added timeout(30) to give the AI enough time to generate the text
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}", [
                    'contents' => [[ 'parts' => [['text' => $prompt]] ]]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            }
            
            // If the API returns a 400 or 500 error, log it so we know WHY it failed
            \Log::error('Gemini API Request Failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;

        } catch (\Exception $e) {
            // If the request times out or Laravel can't reach the internet, log it
            \Log::error('Gemini API Exception Caught', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function goToStep($step)
    {
        $this->step = $step;
    }

    public function render()
    {
        return view('livewire.cases.create-dispute');
    }
}
