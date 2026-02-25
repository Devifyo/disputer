<?php

namespace App\Livewire\User\Emails;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\LetterTemplate;

class ComposeEmail extends Component
{
    use WithFileUploads;

    public $case_id = '';
    public $recipient = '';
    public $subject = '';
    public $body = '';
    public $attachments = [];

    public $showTemplateModal = false;
    public $searchQuery = '';
    
    protected $rules = [
        'recipient' => 'required|email',
        'subject' => 'required|min:3',
        'body' => 'required',
        'attachments.*' => 'max:10240',
    ];

    // FIX 1: Show templates by default
    public function getTemplatesProperty()
    {
        $query = LetterTemplate::with('category')->where('is_active', true);

        // If search is provided, filter. If not, just show latest 10.
        if (!empty($this->searchQuery)) {
            $query->where(function($q) {
                $q->where('title', 'like', '%' . $this->searchQuery . '%')
                  ->orWhereHas('category', fn($c) => $c->where('name', 'like', '%' . $this->searchQuery . '%'));
            });
        }

        return $query->latest()->limit(10)->get();
    }

    public function applyTemplate($id)
    {
        $template = LetterTemplate::find($id);
        if ($template) {
            $this->subject = $template->title;
            $this->body = $template->content;
            $this->showTemplateModal = false;
        }
    }

    public function removeAttachment($index)
    {
        array_splice($this->attachments, $index, 1);
    }

    // FIX 2: Discard Function
    public function discard()
    {
        return redirect()->route('user.emails.index');
    }

    public function send()
    {
        $this->validate();
        
        // Simulate Delay to see the "Sending..." state
        sleep(1); 

        session()->flash('message', 'Email sent successfully!');
        return redirect()->route('user.emails.index');
    }

    public function render()
    {
        return view('livewire.user.emails.compose-email');
    }
}