<?php

namespace App\Livewire\Admin\SuccessStories;

use App\Models\SuccessStory;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $filterStatus = ''; // 'published' or 'pending'
    
    // Modal State
    public $showStoryModal = false;
    public $selectedStory = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    public function togglePublish($id)
    {
        $story = SuccessStory::findOrFail($id);
        $story->is_published = !$story->is_published;
        $story->save();
        
        session()->flash('success', 'Story status updated successfully.');
    }

    public function viewStory($id)
    {
        $this->selectedStory = SuccessStory::findOrFail($id);
        $this->showStoryModal = true;
    }

    public function delete($id)
    {
        $story = SuccessStory::findOrFail($id);
        
        // Optionally delete associated files from storage here if needed
        
        $story->delete();
        $this->showStoryModal = false;
        
        session()->flash('success', 'Success story deleted.');
    }

    public function render()
    {
        $query = SuccessStory::query()->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('story', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterStatus === 'published') {
            $query->where('is_published', true);
        } elseif ($this->filterStatus === 'pending') {
            $query->where('is_published', false);
        }

        return view('livewire.admin.success-stories.index', [
            'stories' => $query->paginate(10)
        ])->extends('layouts.admin')->section('content');
    }
}