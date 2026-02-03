<?php

namespace App\Livewire\Cases;

use Livewire\Component;
use App\Models\Institution;
use App\Models\InstitutionCategory;
use App\Models\Cases;
use App\Models\CaseTimeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Required for raw queries
use Illuminate\Support\Str;

class CreateDispute extends Component
{
    // --- WIZARD STATE ---
    public $step = 1;

    // --- STEP 1: INSTITUTION DATA ---
    public $query = '';
    public $results; // Will be initialized as Collection
    public $mode = 'search';
    public $popular = [];

    // Selection State
    public $selectedInstitutionId = null;
    public $selectedInstitutionName = '';

    // Custom Creation State
    public $customName = '';
    public $categoryId = '';
    public $customCategoryName = '';
    public $categories = [];

    // --- STEP 2: DISPUTE DETAILS DATA ---
    public $transactionDate;
    public $transactionAmount;
    public $referenceNumber;
    public $issueDescription;

    public function mount()
    {
        // 1. Initialize results as an Empty Collection (Fixes 'first() on array' error)
        $this->results = collect();

        $this->popular = Institution::where('is_verified', true)->limit(4)->get();
        $this->categories = InstitutionCategory::orderBy('name')->get();
    }

    // --- STEP 1 LOGIC ---

    public function updatedQuery()
    {
        // 2. Clean input
        $searchTerm = trim($this->query);

        if (strlen($searchTerm) >= 1) {
            $this->results = Institution::with('category')
                // 3. FORCE CASE-INSENSITIVE SEARCH
                // This converts both the column and search term to lowercase before comparing
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                ->where('is_verified', true)
                ->limit(5)
                ->get();
        } else {
            // 4. Reset to Collection (Not Array)
            $this->results = collect();
        }
    }

    public function selectExisting($id, $name)
    {
        $this->selectedInstitutionId = $id;
        $this->selectedInstitutionName = $name;
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
        $this->selectedInstitutionId = null; // null = New Institution

        $this->goToStep(2);
    }

    // --- STEP 2 LOGIC (Saving Logic) ---

    public function submitDetails()
    {
        $this->validate([
            'transactionDate' => 'required|date',
            'transactionAmount' => 'required|numeric|min:0',
            'issueDescription' => 'required|min:10',
        ]);

        DB::transaction(function () {

            // 1. Handle Institution Creation
            if (is_null($this->selectedInstitutionId)) {
                $finalCategoryId = $this->categoryId;

                if ($this->categoryId === 'other') {
                    $newCat = InstitutionCategory::firstOrCreate(
                        ['name' => ucfirst($this->customCategoryName)],
                        ['slug' => Str::slug($this->customCategoryName)]
                    );
                    $finalCategoryId = $newCat->id;
                }

                $newInst = Institution::create([
                    'name' => $this->selectedInstitutionName,
                    'institution_category_id' => $finalCategoryId,
                    'is_verified' => false,
                ]);

                $this->selectedInstitutionId = $newInst->id;
            }

            // 2. Create the CASE
            // Note: I removed 'transaction_date' etc. from here because your Cases model
            // likely only has basic fields. The details go into metadata/timeline.
            $case = Cases::create([
                'user_id' => Auth::id(),
                'institution_id' => $this->selectedInstitutionId,
                'institution_name' => $this->selectedInstitutionName,
                'case_reference_id' => strtoupper(Str::random(6)),
                'email_route_id' => (string) Str::uuid(),
                'status' => 'Active',
                'stage' => 'Drafting',
            ]);

            // 3. Create the TIMELINE (Metadata Storage)
            CaseTimeline::create([
                'case_id' => $case->id,
                'type' => 'case_created',
                'actor' => 'User',
                'description' => $this->issueDescription,
                'occurred_at' => now(),
                'metadata' => [
                    'amount' => $this->transactionAmount,
                    'transaction_date' => $this->transactionDate,
                    'reference_number' => $this->referenceNumber ?? 'N/A'
                ]
            ]);
        });

        session()->flash('message', 'Dispute Draft Created Successfully!');
        return redirect()->route('user.dashboard');
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
