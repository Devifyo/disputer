<?php

namespace App\Livewire\Admin\Categories;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\InstitutionCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Index extends Component
{
    use WithPagination;

    public $showModal = false;
    public $isEditMode = false;
    public $search = '';

    // Model Properties
    public $category_id;
    public $name, $slug, $fallback_escalation_email;
    public $is_verified = true;
    
    // Visual Builder Properties
    public $initial_step = '';
    public $workflow_steps = [];

    public function updatedSearch() { $this->resetPage(); }

    public function updatedName($value)
    {
        if (empty($this->slug) && !$this->isEditMode) {
            $this->slug = Str::slug($value);
        }
    }

    protected function rules()
    {
        $stepKeys = array_filter(array_column($this->workflow_steps, 'step_key'));

        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:institution_categories,slug,' . $this->category_id,
            'fallback_escalation_email' => 'nullable|email|max:255',
            'is_verified' => 'boolean',
            
            // STRICT VALIDATION: Now enforced globally by Livewire
            'initial_step' => ['required', 'string', Rule::in($stepKeys)], 
            
            'workflow_steps' => 'array|min:1',
            'workflow_steps.*.step_key' => 'required|string|distinct',  
            'workflow_steps.*.label' => 'required|string',
            'workflow_steps.*.actions' => 'array',
            'workflow_steps.*.actions.*.label' => 'required|string',
            'workflow_steps.*.actions.*.to_step' => ['required', 'string', Rule::in($stepKeys)], 
        ];
    }

    protected function messages()
    {
        return [
            'initial_step.required' => 'Please select an Initial Step before saving.',
            'initial_step.in' => 'The selected initial step is invalid or was deleted.',
            'workflow_steps.*.step_key.distinct' => 'This step key is already in use. Keys must be unique.',
            'workflow_steps.*.step_key.required' => 'A step key is required.',
            'workflow_steps.min' => 'You must define at least one step for the workflow.',
            'workflow_steps.*.actions.*.to_step.in' => 'Invalid target step. The step key may have been changed or deleted.',
            'workflow_steps.*.actions.*.to_step.required' => 'You must select a target step for this action.',
            'workflow_steps.*.actions.*.label.required' => 'Action button needs a label.',
        ];
    }

    // --- VISUAL BUILDER METHODS ---

    public function addStep()
    {
        $this->workflow_steps[] = [
            'id' => (string) Str::uuid(),
            'step_key' => '', 'label' => '', 'description' => '', 
            'status_color' => 'slate', 'icon' => 'file', 'waiting_for' => '', 
            'actions' => [], 'timeouts' => [], 'is_final' => false
        ];
    }

    public function removeStep($index)
    {
       if (count($this->workflow_steps) > 1) {
            unset($this->workflow_steps[$index]);
            $this->workflow_steps = array_values($this->workflow_steps); // Re-index
            
            // If the deleted step was the initial step, reset it to force re-selection
            $stepKeys = array_filter(array_column($this->workflow_steps, 'step_key'));
            if (!in_array($this->initial_step, $stepKeys)) {
                $this->initial_step = ''; 
            }
        } else {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'A workflow must have at least one step.']);
        }
    }

    public function addAction($stepIndex)
    {
        $this->workflow_steps[$stepIndex]['actions'][] = ['id' => (string) Str::uuid(),'key' => '', 'label' => '', 'to_step' => ''];
    }

    public function removeAction($stepIndex, $actionIndex)
    {
        unset($this->workflow_steps[$stepIndex]['actions'][$actionIndex]);
        $this->workflow_steps[$stepIndex]['actions'] = array_values($this->workflow_steps[$stepIndex]['actions']);
    }

    // --- CRUD METHODS ---

    public function create()
    {
        $this->reset(['category_id', 'name', 'slug', 'fallback_escalation_email', 'initial_step', 'workflow_steps']);
        $this->is_verified = true;
        
        $this->initial_step = 'ticket_open';
        $this->workflow_steps = [
            [   
                'id' => (string) Str::uuid(),
                'step_key' => 'ticket_open', 'label' => 'Ticket Open', 'description' => 'Awaiting initial review.',
                'status_color' => 'slate', 'icon' => 'ticket', 'waiting_for' => '', 
                'actions' => [], 'timeouts' => [], 'is_final' => false
            ]
        ];

        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $this->isEditMode = true;
        $this->category_id = $id;

        $category = InstitutionCategory::findOrFail($id);

        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->fallback_escalation_email = $category->fallback_escalation_email;
        $this->is_verified = (bool) $category->is_verified;
        
        $config = $category->workflow_config ?? [];
        $this->initial_step = $config['initial_step'] ?? '';
        $this->workflow_steps = [];
        
        foreach (($config['steps'] ?? []) as $key => $data) {
            $data['id'] = (string) Str::uuid(); // ADD THIS
            $data['step_key'] = $key;
            
            // Add UUIDs to existing actions
            $data['actions'] = $data['actions'] ?? [];
            foreach ($data['actions'] as &$action) {
                $action['id'] = (string) Str::uuid(); // ADD THIS
            }
            
            $data['timeouts'] = $data['timeouts'] ?? [];
            $data['is_final'] = $data['is_final'] ?? false;
            $this->workflow_steps[] = $data;
        }

        $this->showModal = true;
    }

    private function formatWorkflowForDatabase()
    {
        $stepsDb = [];
        foreach ($this->workflow_steps as $step) {
            $key = $step['step_key'] ?: Str::slug($step['label']);
            unset($step['step_key']);
            unset($step['id']); // REMOVE THE UI ID BEFORE SAVING

            if (!empty($step['actions'])) {
                foreach ($step['actions'] as &$action) {
                    unset($action['id']); // REMOVE THE UI ID BEFORE SAVING
                }
            } else {
                unset($step['actions']);
            }

            if (empty($step['timeouts'])) unset($step['timeouts']);
            if (!$step['is_final']) unset($step['is_final']);

            $stepsDb[$key] = array_filter($step, fn($value) => !is_null($value) && $value !== '');
        }

        return [
            'initial_step' => $this->initial_step,
            'steps' => $stepsDb
        ];
    }

    public function store()
    {   
        $this->validate(); // This now automatically checks `initial_step`

        InstitutionCategory::create([
            'name' => $this->name,
            'slug' => Str::slug($this->slug),
            'fallback_escalation_email' => $this->fallback_escalation_email,
            'is_verified' => $this->is_verified,
            'workflow_config' => $this->formatWorkflowForDatabase(),
        ]);

        $this->showModal = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Category created successfully!']);
    }

    public function update()
    {   
        $this->validate(); // This now automatically checks `initial_step`

        InstitutionCategory::findOrFail($this->category_id)->update([
            'name' => $this->name,
            'slug' => Str::slug($this->slug),
            'fallback_escalation_email' => $this->fallback_escalation_email,
            'is_verified' => $this->is_verified,
            'workflow_config' => $this->formatWorkflowForDatabase(),
        ]);

        $this->showModal = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Category updated successfully!']);
    }

    public function deleteConfirmed($id)
    {
        InstitutionCategory::findOrFail($id)->delete();
        $this->dispatch('toast', ['type' => 'warning', 'message' => 'Category moved to trash.']);
    }

    public function render()
    {
        $categories = InstitutionCategory::when($this->search, function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('slug', 'like', '%' . $this->search . '%');
            })
            ->withCount('institutions')
            ->latest()
            ->paginate(10);

        return view('livewire.admin.categories.index', compact('categories'))
               ->extends('layouts.admin')
               ->section('content');
    }
}