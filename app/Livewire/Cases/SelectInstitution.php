<?php

namespace App\Livewire\Cases;

use Livewire\Component;
use App\Models\Institution;
use App\Models\InstitutionCategory;

class SelectInstitution extends Component
{
    // Search State
    public $query = '';
    public $results = [];

    // Mode State: 'search' or 'create'
    public $mode = 'search';

    // Create New State
    public $customName = '';
    public $categoryId = '';
    public $customCategoryName = '';

    // Data for dropdowns
    public $popular = [];
    public $categories = [];

    public function mount()
    {
        // Load initial data
        $this->popular = Institution::where('is_verified', true)->limit(4)->get();
        $this->categories = InstitutionCategory::orderBy('name')->get();
    }

    // Real-time Search Hook
    public function updatedQuery()
    {
        if (strlen($this->query) >= 1) {
            $this->results = Institution::with('category')
                ->where('name', 'LIKE', '%' . $this->query . '%')
                ->where('is_verified', true)
                ->limit(5)
                ->get();
        } else {
            $this->results = [];
        }
    }

    // Action: User clicks "Create Custom"
    public function enableCreateMode()
    {
        $this->mode = 'create';
        $this->customName = $this->query; // Pre-fill name
        $this->query = ''; // Clear search
    }

    // Action: User cancels creation
    public function cancelCreateMode()
    {
        $this->mode = 'search';
        $this->customName = '';
    }

    // Action: Select Existing Institution
    public function selectInstitution($id)
    {
        // Redirect to Step 2 with ID
        return redirect()->route('user.cases.create.step2', ['institution_id' => $id]);
    }

    // Action: Submit New Institution
    public function submitCustom()
    {
        $this->validate([
            'customName' => 'required|min:2',
            'categoryId' => 'required',
            'customCategoryName' => 'required_if:categoryId,other',
        ]);

        // Redirect to Step 2 with Custom Data
        return redirect()->route('user.cases.create.step2', [
            'custom_name' => $this->customName,
            'category_id' => $this->categoryId,
            'custom_category_name' => $this->customCategoryName,
        ]);
    }

    public function render()
    {
        return view('livewire.cases.select-institution');
    }
}
