<?php

namespace App\Livewire\Admin\Support;

use App\Models\SupportMessage;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $filter = 'all'; // all, unread, read, resolved
    
    // Modal State
    public $showModal = false;
    public $selectedMessage = null;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilter()
    {
        $this->resetPage();
    }

    public function openMessage($id)
    {
        $message = SupportMessage::findOrFail($id);
        
        // If it's a new/unread message, mark it as read when opened
        if ($message->status === 'new') {
            $message->update(['status' => 'read']);
        }

        $this->selectedMessage = $message;
        $this->showModal = true;
    }

    public function closeMessage()
    {
        $this->showModal = false;
        $this->selectedMessage = null;
    }

    public function markAsResolved($id)
    {
        $message = SupportMessage::findOrFail($id);
        $message->update(['status' => 'resolved']);
        
        if ($this->selectedMessage && $this->selectedMessage->id === $id) {
            $this->selectedMessage->status = 'resolved';
            $this->closeMessage();
        }
    }

    public function render()
    {
        $messages = SupportMessage::query()
            ->when($this->search, function ($query) {
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('message', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filter !== 'all', function ($query) {
                if ($this->filter === 'unread') {
                    $query->where('status', 'new');
                } elseif ($this->filter === 'read') {
                    $query->where('status', 'read');
                } elseif ($this->filter === 'resolved') {
                    $query->where('status', 'resolved');
                }
            })
            ->latest()
            ->paginate(15);

        return view('livewire.admin.support.index', [
            'messages' => $messages
        ])->extends('layouts.admin')->section('content');
    }
}