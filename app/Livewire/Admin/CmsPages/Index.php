<?php

namespace App\Livewire\Admin\CmsPages;

use Livewire\Component;
use App\Models\CmsPage;

class Index extends Component
{
    public $pages;
    public ?int $editingId = null;
    public string $title = '';
    public string $content = '';

    public function mount()
    {
        $this->pages = CmsPage::all();
    }

    public function edit(int $id)
    {
        $page = CmsPage::findOrFail($id);
        $this->editingId = $id;
        $this->title     = $page->title;
        $this->content   = $page->content;
    }

    public function save()
    {
        $this->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        CmsPage::where('id', $this->editingId)->update([
            'title'           => $this->title,
            'content'         => $this->content,
            'last_updated_at' => now(),
        ]);

        $this->pages     = CmsPage::all();
        $this->editingId = null;
        $this->title     = '';
        $this->content   = '';

        $this->dispatch('notify', type: 'success', message: 'Page saved successfully.');
    }

    public function cancel()
    {
        $this->editingId = null;
        $this->title     = '';
        $this->content   = '';
    }

    public function render()
    {
        return view('livewire.admin.cms-pages.index')
            ->extends('layouts.admin')->section('content');
    }
}
