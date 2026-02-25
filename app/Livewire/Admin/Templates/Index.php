<?php

namespace App\Livewire\Admin\Templates;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\LetterTemplate;
use App\Models\InstitutionCategory;
use Illuminate\Support\Str;

class Index extends Component
{
    use WithPagination;

    public $showModal = false;
    public $isEditMode = false;
    
    // Filters
    public $search = '';
    public $category_filter = '';

    // Model Properties
    public $template_id;
    public $institution_category_id;
    public $title, $slug, $description, $content, $icon, $color;
    public $is_active = true;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedCategoryFilter() { $this->resetPage(); }

    public function updatedTitle($value)
    {
        if (empty($this->slug) && !$this->isEditMode) {
            $this->slug = Str::slug($value);
        }
    }

    protected function rules()
    {
        return [
            'institution_category_id' => 'required|exists:institution_categories,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:letter_templates,slug,' . $this->template_id,
            'description' => 'required|string|max:500',
            'content' => 'required|string',
            'icon' => 'required|string|max:50',
            'color' => 'required|string|max:50',
            'is_active' => 'boolean',
        ];
    }

    public function create()
    {
        $this->reset(['template_id', 'institution_category_id', 'title', 'slug', 'description', 'content']);
        $this->icon = 'file-text';
        $this->color = 'blue';
        $this->is_active = true;

        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $this->isEditMode = true;
        $this->template_id = $id;

        $template = LetterTemplate::findOrFail($id);

        $this->institution_category_id = $template->institution_category_id;
        $this->title = $template->title;
        $this->slug = $template->slug;
        $this->description = $template->description;
        $this->content = $template->content;
        $this->icon = $template->icon;
        $this->color = $template->color;
        $this->is_active = (bool) $template->is_active;

        $this->showModal = true;
    }

    public function store()
    {   
        $this->validate();

        LetterTemplate::create([
            'institution_category_id' => $this->institution_category_id,
            'title' => $this->title,
            'slug' => Str::slug($this->slug),
            'description' => $this->description,
            'content' => $this->content,
            'icon' => $this->icon,
            'color' => $this->color,
            'is_active' => $this->is_active,
        ]);

        $this->showModal = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Template created successfully!']);
    }

    public function update()
    {   
        $this->validate();

        LetterTemplate::findOrFail($this->template_id)->update([
            'institution_category_id' => $this->institution_category_id,
            'title' => $this->title,
            'slug' => Str::slug($this->slug),
            'description' => $this->description,
            'content' => $this->content,
            'icon' => $this->icon,
            'color' => $this->color,
            'is_active' => $this->is_active,
        ]);

        $this->showModal = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Template updated successfully!']);
    }

    public function deleteConfirmed($id)
    {
        LetterTemplate::findOrFail($id)->delete();
        $this->dispatch('toast', ['type' => 'warning', 'message' => 'Template deleted.']);
    }

    public function render()
    {
        $templates = LetterTemplate::with('category')
            ->when($this->search, function($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->when($this->category_filter, function($q) {
                $q->where('institution_category_id', $this->category_filter);
            })
            ->latest()
            ->paginate(10);

        $categories = InstitutionCategory::orderBy('name')->get();

        return view('livewire.admin.templates.index', compact('templates', 'categories'))
               ->extends('layouts.admin')
               ->section('content');
    }
}