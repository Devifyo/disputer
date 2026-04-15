<?php

namespace App\Livewire\User\Cases;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Services\GeminiEmailAnalysisService; // Import the Service
use App\Models\Email;
class EmailViewer extends Component
{
    public $isOpen = false;
    public $subject = '';
    public $body = '';
    public $attachments = [];
    public $recipient_email = '';
    
    public $isAnalyzing = false;
    public $analysis = null;
    public $emailId = '';
    public $caseId = '';
    // 1. ADD THESE NEW PROPERTIES
    public $direction = 'outbound'; 
    #[On('open-email')]
    public function loadEmail(
        $emailId = null, 
        $caseId = null, 
        $subject = 'No Subject', 
        $body = '', 
        $direction = 'outbound', 
        $attachments = [], 
        $recipient = 'Support Team'
    ) {   
        // 1. Assign the automatically mapped variables to the component properties
        $this->emailId = $emailId;
        $this->caseId = $caseId;
        $this->subject = $subject;
        $this->body = $body;
        $this->attachments = $attachments;
        $this->recipient_email = $recipient;
        $this->direction = $direction;
        
        $this->isOpen = true;
        $this->analysis = null;

        // 2. Handle the database read/create logic
        if ($this->emailId) {
            $email = Email::find($this->emailId);
            
            if ($email) {
                // The email exists! Check if it's unread.
                if (!$email->is_read) {
                    $email->update(['is_read' => true]);
                    $this->dispatch('email-read-state-changed', emailId: $this->emailId);
                }
            } else {
                // The email is missing! Create it safely using the caseId.
                if ($this->caseId) {
                    Email::create([
                        'id' => $this->emailId,
                        'case_id' => $this->caseId,
                        'direction' => $this->direction,
                        'sender_email' => $this->direction === 'inbound' ? $this->recipient_email : 'user_fallback@example.com',
                        'recipient_email' => $this->direction === 'inbound' ? 'system@example.com' : $this->recipient_email,
                        'subject' => $this->subject,
                        'body_html' => $this->body,
                        'is_read' => true
                    ]);
                    
                    $this->dispatch('email-read-state-changed', emailId: $this->emailId);
                }
            }
        }
    }

    public function close()
    {
        $this->isOpen = false;
        $this->reset(['subject', 'body', 'attachments', 'recipient_email', 'analysis', 'isAnalyzing']);
    }

    /**
     * Analyze using the Service
     */
    public function analyze(GeminiEmailAnalysisService $aiService)
    {
        $this->isAnalyzing = true;
        $this->analysis = null;

        $userName = auth()->check() ? auth()->user()->name : 'The User';
        // dd($this->subject,
        //     $this->body,
        //     $this->attachments,
        //     $userName);
        // The Service handles everything
        $this->analysis = $aiService->analyze(
            $this->subject,
            $this->body,
            $this->attachments,
            $userName
        );
        
        $this->isAnalyzing = false;
    }

    public function getFileVisuals($filename)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match(true) {
            in_array($ext, ['pdf']) 
                => ['icon' => 'file-text', 'color' => 'text-rose-500', 'bg' => 'bg-rose-50', 'border' => 'border-rose-100'],
                
            in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic']) 
                => ['icon' => 'image', 'color' => 'text-blue-500', 'bg' => 'bg-blue-50', 'border' => 'border-blue-100'],
                
            in_array($ext, ['xls', 'xlsx', 'csv', 'numbers']) 
                => ['icon' => 'table', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50', 'border' => 'border-emerald-100'],
                
            in_array($ext, ['doc', 'docx', 'txt', 'rtf'])
                => ['icon' => 'file-type-2', 'color' => 'text-indigo-500', 'bg' => 'bg-indigo-50', 'border' => 'border-indigo-100'],

            in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm'])
                => ['icon' => 'video', 'color' => 'text-violet-500', 'bg' => 'bg-violet-50', 'border' => 'border-violet-100'],

            default
                => ['icon' => 'file', 'color' => 'text-slate-400', 'bg' => 'bg-slate-50', 'border' => 'border-slate-100'],
        };
    }

    public function render()
    {
        return view('livewire.user.cases.email-viewer');
    }
}