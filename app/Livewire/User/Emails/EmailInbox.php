<?php

namespace App\Livewire\User\Emails;

use Livewire\Component;
use App\Services\EmailService;

class EmailInbox extends Component
{
    // Search & Filter State
    public $search = '';
    public $filter = 'all'; // all, unread, sent

    // Inject Service
    protected function getService()
    {
        return app(EmailService::class);
    }

    // Reset pagination or search when switching filters (Optional but good practice)
    public function updatedFilter()
    {
        $this->search = ''; 
    }

    public function render()
    {
        // Fetch data using the service
        $threads = $this->getService()->getThreads($this->search, $this->filter);

        return view('livewire.user.emails.email-inbox', [
            'threads' => $threads
        ]);
    }
}