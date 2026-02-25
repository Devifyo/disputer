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

    // -- UI State --
    public $showModal = false;
    public $isEditMode = false;
    
    // -- Filters --
    public $search = '';
    public $filterCategory = ''; // New: Category Filter
    public $filterStatus = '';   // New: Status Filter

    // -- Form Properties --
    public $institute_id;
    public $name = '';
    public $institution_category_id = '';
    public $contact_email = '';
    public $escalation_email = '';
    public $escalation_contact_name = '';
    public $is_verified = true;

    // -- Lifecycle Hooks (Reset Pagination when filters change) --
    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterCategory() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'institution_category_id' => 'required|exists:institution_categories,id',
            'contact_email' => 'required|email|max:255',
            'escalation_email' => 'nullable|email|max:255',
            'escalation_contact_name' => 'nullable|string|max:255',
            'is_verified' => 'boolean'
        ];
    }

    #[Computed]
    public function categories() { return InstitutionCategory::orderBy('name')->get(); }

    // --- Actions ---

    public function create()
    {
        $this->resetForm();
        $this->isEditMode = false;
        $this->is_verified = true;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetForm();
        $this->isEditMode = true;

        $institute = Institution::findOrFail($id);
        $this->institute_id = $institute->id;
        $this->name = $institute->name;
        $this->institution_category_id = $institute->institution_category_id;
        $this->contact_email = $institute->contact_email;
        $this->escalation_email = $institute->escalation_email;
        $this->escalation_contact_name = $institute->escalation_contact_name;
        $this->is_verified = (bool) $institute->is_verified;

        $this->showModal = true;
    }

    public function store()
    {
        $this->validate();
        Institution::create($this->formArray());
        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Institute created!');
    }

    public function update()
    {
        $this->validate();
        Institution::findOrFail($this->institute_id)->update($this->formArray());
        $this->showModal = false;
        $this->resetForm();
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
        $this->reset(['institute_id', 'name', 'institution_category_id', 'contact_email', 'escalation_email', 'escalation_contact_name', 'is_verified']);
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
        $query = Institution::with(['category'])
            // Search Filter
            ->when($this->search, function($q) {
                $q->where(function($sub) {
                    $sub->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('contact_email', 'like', '%' . $this->search . '%');
                });
            })
            // Category Filter
            ->when($this->filterCategory, function($q) {
                $q->where('institution_category_id', $this->filterCategory);
            })
            // Status Filter
            ->when($this->filterStatus === 'verified', fn($q) => $q->where('is_verified', true))
            ->when($this->filterStatus === 'unverified', fn($q) => $q->where('is_verified', false))
            
            ->latest();

        return view('livewire.admin.institutions.index', [
            'institutions' => $query->paginate(10),
        ])->extends('layouts.admin')->section('content');
    }
}