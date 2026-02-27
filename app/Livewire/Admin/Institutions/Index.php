<?php

namespace App\Livewire\Admin\Institutions;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\Institution;
use App\Models\InstitutionCategory;

class Index extends Component
{
    use WithPagination;

    // -- UI State & Filters --
    public $showModal = false;
    public $isEditMode = false;
    public $search = '';
    public $filterCategory = '';
    public $filterStatus = '';

    // -- Form Properties (Restored Escalation) --
    public $institute_id;
    public $name = '';
    public $institution_category_id = '';
    public $contact_email = '';
    public $escalation_email = '';
    public $escalation_contact_name = '';
    public $is_verified = true;
    
    // -- Dynamic Contacts Array --
    public array $contacts = [];

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterCategory() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }
    
    // When category changes, reset contacts to avoid invalid step keys
    public function updatedInstitutionCategoryId() { $this->contacts = []; }

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'institution_category_id' => 'required|exists:institution_categories,id',
            'contact_email' => 'required|email|max:255',
            'escalation_email' => 'nullable|email|max:255',
            'escalation_contact_name' => 'nullable|string|max:255',
            'is_verified' => 'boolean',
            
            // Validate contacts against step_key instead of stage integer
            'contacts' => 'array',
            'contacts.*.step_key' => 'required|string',
            'contacts.*.department_name' => 'required|string|max:255',
            'contacts.*.channel' => 'required|in:email,url,portal,phone',
            'contacts.*.contact_value' => 'required|string|max:255',
        ];
    }

    #[Computed]
    public function categories() { return InstitutionCategory::orderBy('name')->get(); }

    // --- NEW: Dynamically fetch steps from the selected category's JSON ---
    #[Computed]
    public function availableSteps()
    {
        if (!$this->institution_category_id) return [];
        
        $category = InstitutionCategory::find($this->institution_category_id);
        if (!$category || empty($category->workflow_config['steps'])) return [];

        $steps = [];
        foreach ($category->workflow_config['steps'] as $key => $stepData) {
            // Exclude final resolution steps from needing a contact
            if (!isset($stepData['is_final']) || !$stepData['is_final']) {
                $steps[$key] = $stepData['label'] ?? $key;
            }
        }
        return $steps;
    }

    public function addContact()
    {
        $this->contacts[] = [
            'step_key' => '',
            'department_name' => '',
            'channel' => 'email',
            'contact_value' => '',
        ];
    }

    public function removeContact($index)
    {
        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetForm();
        $this->isEditMode = true;

        $institute = Institution::with('contacts')->findOrFail($id);
        $this->institute_id = $institute->id;
        $this->name = $institute->name;
        $this->institution_category_id = $institute->institution_category_id;
        $this->contact_email = $institute->contact_email;
        $this->escalation_email = $institute->escalation_email;
        $this->escalation_contact_name = $institute->escalation_contact_name;
        $this->is_verified = (bool) $institute->is_verified;
        
        $this->contacts = $institute->contacts->toArray();
        $this->showModal = true;
    }

    public function store()
    {
        $this->validate();
        $institution = Institution::create($this->formArray());
        if (!empty($this->contacts)) $institution->contacts()->createMany($this->contacts);
        
        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: 'Institute created!');
    }

    public function update()
    {
        $this->validate();
        $institution = Institution::findOrFail($this->institute_id);
        $institution->update($this->formArray());

        $institution->contacts()->forceDelete(); 
        if (!empty($this->contacts)) $institution->contacts()->createMany($this->contacts);

        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: 'Institute updated!');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function deleteConfirmed($id)
    {
        Institution::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'warning', message: 'Institute deleted.');
    }

    public function toggleVerified($id)
    {
        $inst = Institution::findOrFail($id);
        $inst->update(['is_verified' => !$inst->is_verified]);
    }

    private function resetForm()
    {
        $this->reset(['institute_id', 'name', 'institution_category_id', 'contact_email', 'escalation_email', 'escalation_contact_name', 'is_verified', 'contacts']);
        $this->resetValidation();
    }

    private function formArray()
    {
        return [
            'name' => $this->name,
            'institution_category_id' => $this->institution_category_id,
            'contact_email' => $this->contact_email,
            'escalation_email' => $this->escalation_email,
            'escalation_contact_name' => $this->escalation_contact_name,
            'is_verified' => $this->is_verified,
        ];
    }

    public function render()
    {
        $query = Institution::with(['category', 'contacts'])
            ->when($this->search, function($q) {
                $q->where(function($sub) {
                    $sub->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('contact_email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterCategory, fn($q) => $q->where('institution_category_id', $this->filterCategory))
            ->when($this->filterStatus === 'verified', fn($q) => $q->where('is_verified', true))
            ->when($this->filterStatus === 'unverified', fn($q) => $q->where('is_verified', false))
            ->latest();

        return view('livewire.admin.institutions.index', [
            'institutions' => $query->paginate(10),
        ])->extends('layouts.admin')->section('content');
    }
}