<?php

namespace App\Livewire\Admin\Plans;

use App\Models\Plan;
use App\Services\PlanService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    
    // Modal State
    public $showModal = false;
    public $isEditMode = false;

    // Form Fields
    public $plan_id;
    public $name = '';
    public $slug = '';
    public $type = 'recurring_yearly';
    public $case_limit = '';
    public $price = '';
    public $currency = 'USD';
    public $features = '';
    public $is_active = true;

    public function updatedName($value)
    {
        if (!$this->isEditMode) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->reset(['plan_id', 'name', 'slug', 'type', 'case_limit', 'price', 'currency', 'features', 'is_active']);
        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $plan = Plan::findOrFail($id);
        
        $this->plan_id = $plan->id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->type = $plan->type;
        $this->case_limit = $plan->case_limit;
        $this->price = $plan->price;
        $this->currency = $plan->currency;
        $this->is_active = $plan->is_active;
        $this->features = is_array($plan->features) ? implode("\n", $plan->features) : '';
        
        $this->isEditMode = true;
        $this->showModal = true;
    }

    // INJECT THE SERVICE HERE
    public function store(PlanService $planService)
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug',
            'type' => 'required|in:recurring_yearly,one_time',
            'case_limit' => 'nullable|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ]);

        $featuresArray = array_values(array_filter(array_map('trim', explode("\n", $this->features))));

        // Use the service!
        $planService->createPlan([
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'case_limit' => $this->case_limit === '' ? null : $this->case_limit,
            'price' => $this->price,
            'currency' => $this->currency,
            'features' => empty($featuresArray) ? null : $featuresArray,
            'is_active' => $this->is_active,
        ]);

        $this->showModal = false;
        session()->flash('success', 'Plan created and synced with Stripe!');
    }

    // INJECT THE SERVICE HERE
    public function update(PlanService $planService)
    {
        $plan = Plan::findOrFail($this->plan_id);

        $this->validate([
            'name' => 'required|string|max:255',
            // Notice: We completely removed 'slug' validation here because we won't accept user input for it
            'type' => 'required|in:recurring_yearly,one_time',
            'case_limit' => 'nullable|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ]);

        $featuresArray = array_values(array_filter(array_map('trim', explode("\n", $this->features))));

        // Use the service!
        $planService->updatePlan($plan, [
            'name' => $this->name,
            'slug' => $plan->slug, // Force it to use the existing DB slug
            'type' => $this->type,
            'case_limit' => $this->case_limit === '' ? null : $this->case_limit,
            'price' => $this->price,
            'currency' => $this->currency,
            'features' => empty($featuresArray) ? null : $featuresArray,
            'is_active' => $this->is_active,
        ]);

        $this->showModal = false;
        session()->flash('success', 'Plan updated successfully!');
    }

    // INJECT THE SERVICE HERE
    public function delete($id, PlanService $planService)
    {
        $plan = Plan::findOrFail($id);
        
        // Use the service!
        $planService->deletePlan($plan);
        
        session()->flash('success', 'Plan removed locally and archived in Stripe.');
    }

    public function toggleStatus($id)
    {
        $plan = Plan::findOrFail($id);
        $plan->is_active = !$plan->is_active;
        $plan->save();
        
        // Optional: If you want toggle to sync to stripe immediately, call the service here too.
    }

    public function render()
    {
        $plans = Plan::where('stripe_mode', config('app.stripe_mode', 'test'))->query()
            ->when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('type', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);

        return view('livewire.admin.plans.index', [
            'plans' => $plans
        ])->extends('layouts.admin')->section('content');
    }
}