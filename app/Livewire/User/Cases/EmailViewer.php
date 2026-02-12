<?php

namespace App\Livewire\User\Cases;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Services\GeminiEmailAnalysisService; // Import the Service

class EmailViewer extends Component
{
    public $isOpen = false;
    public $subject = '';
    public $body = '';
    public $attachments = [];
    public $recipient_email = '';
    
    public $isAnalyzing = false;
    public $analysis = null;

    #[On('open-email')]
    public function loadEmail($subject, $body, $attachments = [], $recipient = 'Support Team')
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->attachments = $attachments;
        $this->recipient_email = $recipient;
        $this->isOpen = true;
        $this->analysis = null;
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
                
            default 
                => ['icon' => 'file', 'color' => 'text-slate-400', 'bg' => 'bg-slate-50', 'border' => 'border-slate-100'],
        };
    }

    public function render()
    {
        return view('livewire.user.cases.email-viewer');
    }
}