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

    public $transactionDate;
    public $transactionAmount;
    public $referenceNumber;
    public $issueDescription;

    // Reverted to just Subject and Letter
    public $generatedSubject = ''; 
    public $generatedLetter = '';
    
    public $institutionEmail = '';
    public $attachments = [];
    public $draftMode = 'ai';

    public function mount()
    {
        $this->results = collect();
        $this->popular = Institution::where('is_verified', true)->limit(4)->get();
        $this->categories = InstitutionCategory::orderBy('name')->get();
    }

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
        $institution = Institution::with(['category'])->find($id);
        if ($institution) {
            $initialStep = $institution->category->workflow_config['initial_step'] ?? null;
            $recipient = $initialStep ? $institution->getStepRecipient($initialStep, true) : null;

            if ($recipient) {
                $this->recipientType = $recipient['type'];   
                $this->institutionEmail = $recipient['value']; 
                $this->recipientLabel = $recipient['label']; 
            } else {
                $this->institutionEmail = $institution->contact_email ?? '';
                $this->recipientType = 'email';
                $this->recipientLabel = 'General Support';
            }
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

    public function generateReview()
    {
        // Base validation for the "hard facts" in Step 2
        $this->validate([
            'transactionDate' => 'required|date',
            'transactionAmount' => 'required|numeric|min:0',
            'attachments.*' => 'nullable|file|max:10240',
        ]);

        if ($this->draftMode === 'ai') {
            // AI Mode: Require issue description and call Gemini
            $this->validate([
                'issueDescription' => 'required|min:10',
            ]);

            $rawContent = $this->generateDisputeLetter();
            
            if ($rawContent) {
                $cleanJson = trim(preg_replace('/^```json|```$/i', '', $rawContent));
                $data = json_decode($cleanJson, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($data['subject'], $data['body'])) {
                    $this->generatedSubject = $data['subject'];
                    $this->generatedLetter = $data['body'];
                } else {
                    $this->generatedSubject = "Dispute regarding transaction on {$this->transactionDate}";
                    $this->generatedLetter = "To Whom It May Concern,\n\nI am writing to formally dispute a transaction.\n\nDetails: " . $this->issueDescription . "\n\nPlease investigate this matter.\n\nSincerely,\n" . Auth::user()->name;
                }
            } else {
                $this->generatedSubject = "Dispute Request";
                $this->generatedLetter = "AI Generation Failed. Please type your dispute details here.";
            }
        } else {
            // Manual Mode: Just clear the fields so they are empty and ready for Step 3
            $this->generatedSubject = '';
            $this->generatedLetter = '';
        }

        // Proceed to Step 3 (Review/Write step)
        $this->goToStep(3);
    }

    public function checkAndPromptContact()
    {
        $this->validate([
            'institutionEmail' => 'required|email',
            'generatedSubject' => 'required|min:5',
            'generatedLetter'  => 'required|min:10',
        ]);

        $needsPrompt = false;

        if (is_null($this->selectedInstitutionId)) {
            $needsPrompt = true; 
        } else {
            $inst = Institution::find($this->selectedInstitutionId);
            if (!$inst || empty($inst->contact_email)) {
                $needsPrompt = true; 
            }
        }

        if ($needsPrompt) {
            $this->dispatch('trigger-save-contact-prompt');
        } else {
            $this->executeFinalize(false); 
        }
    }

    public function executeFinalize($saveContact = false)
    {
        $case = null;

        DB::transaction(function () use (&$case, $saveContact) {
            
            $finalCategoryId = $this->selectedInstitutionId 
                ? Institution::find($this->selectedInstitutionId)?->institution_category_id 
                : $this->categoryId;

            if (is_null($this->selectedInstitutionId)) {
                if ($this->categoryId === 'other') {
                    $newCat = InstitutionCategory::firstOrCreate(
                        ['name' => ucfirst($this->customCategoryName)],
                        [
                            'slug' => Str::slug($this->customCategoryName),
                            'workflow_config' => config('workflow_templates.standard'),
                            'is_verified' => false 
                        ]
                    );
                    $finalCategoryId = $newCat->id;
                }

                $newInst = Institution::create([
                    'name' => $this->selectedInstitutionName,
                    'institution_category_id' => $finalCategoryId,
                    'is_internal' => false,
                    'created_by' => auth()->id(),
                    'is_verified' => false,
                ]);
                $this->selectedInstitutionId = $newInst->id;
            }

            if ($saveContact) {
                $inst = Institution::find($this->selectedInstitutionId);
                
                if ($inst && empty($inst->contact_email)) {
                    $inst->update(['contact_email' => $this->institutionEmail]);
                }

                $category = InstitutionCategory::find($finalCategoryId);
                $workflowConfig = $category->workflow_config ?? config('workflow_templates.standard');
                $stepKeys = array_keys($workflowConfig['steps'] ?? ['initial_dispute' => []]);
                $initialStepKey = $stepKeys[0] ?? 1;

                \App\Models\InstitutionContact::updateOrCreate(
                    [
                        'institution_id' => $this->selectedInstitutionId,
                        'step_key' => $initialStepKey,
                        'channel' => 'email'
                    ],
                    [
                        'contact_value' => $this->institutionEmail,
                        'is_primary' => true,
                        'department_name' => 'Initial Dispute Contact',
                        'tone' => 'formal'
                    ]
                );
            }

            $category = InstitutionCategory::find($finalCategoryId);
            $workflowConfig = $category->workflow_config ?? config('workflow_templates.standard');
            $nextActionDate = now()->addDays($workflowConfig['steps'][0]['wait_days'] ?? 14);

            $case = Cases::create([
                'user_id' => Auth::id(),
                'institution_id' => $this->selectedInstitutionId,
                'institution_name' => $this->selectedInstitutionName,
                'case_reference_id' => strtoupper(Str::random(6)), 
                'email_route_id' => (string) Str::uuid(), 
                'status' => \App\Enums\CaseStatus::SENT,
                'stage' => 'Sent',
                'current_workflow_step' => 1,
                'next_action_at' => $nextActionDate, 
            ]);

            CaseTimeline::create([
                'case_id' => $case->id,
                'type' => 'case_created',            
                'actor' => 'User',                   
                'description' => "Dispute case opened against {$this->selectedInstitutionName}", 
                'occurred_at' => now(),              
                'metadata' => [                      
                    'amount' => $this->transactionAmount,
                    'transaction_date' => $this->transactionDate,
                    'reference_number' => $this->referenceNumber ?? 'N/A',
                ]
            ]);
        });

        try {
            $emailService = app(\App\Services\SendEmailService::class);
            
            $emailService->sendAndLog(
                Auth::user(),
                $case,
                $this->institutionEmail,
                $this->generatedSubject,
                nl2br($this->generatedLetter), // Convert the single string to HTML breaks
                $this->attachments 
            );

            session()->flash('message', 'Dispute Sent Successfully!');
            
        } catch (\Exception $e) {
            \Log::error("Initial dispute email failed: " . $e->getMessage());
            session()->flash('error', 'Case created, but failed to send the email. Please check your SMTP settings and try again.');
        }

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

        // TONE LOGIC: Polite but natural. No robot/lawyer speak.
        $tone = "Polite but firm, written by a real customer. Do not sound like a robot or a lawyer.";

        // Prompt enforcing internal structure within a single body string AND human tone
        $prompt = "Write a natural, human-sounding dispute email to {$this->selectedInstitutionName} from a customer's perspective. \n" .
                  "My name is {$user->name}. \n" .
                  "Transaction Date: {$this->transactionDate}. \n" .
                  "Amount: \${$this->transactionAmount}. \n" .
                  "Ref Number: " . ($this->referenceNumber ?? 'N/A') . ". \n" .
                  "Issue: {$this->issueDescription}. \n" .
                  "Tone: {$tone} \n\n" .
                  "IMPORTANT STRICT RULES:\n" .
                  "1. Return a JSON object with EXACTLY 2 keys: 'subject' and 'body'. \n" .
                  "2. NEVER mention 'Stage 1' or any internal tracking stages. The recipient does not know what that means.\n" .
                  "3. Write exactly how a normal human would write an email to customer support. Avoid overly robotic, legalistic, or stiff AI language.\n" .
                  "4. The 'body' string MUST be structured clearly with line breaks (\\n\\n) into the following sections:\n" .
                  "   - A natural opening/salutation.\n" .
                  "   - The core details and explanation of the issue.\n" .
                  "   - The specific request/next steps required from them.\n" .
                  "   - A normal closing and sign-off.";

        try {
            // $model = 'gemini-flash-latest'; 
              $model = 'gemini-2.5-flash'; 
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            }
            
            \Log::error('Gemini API Request Failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;

        } catch (\Exception $e) {
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