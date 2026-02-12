<?php

namespace App\Livewire\User\Cases;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
class EmailViewer extends Component
{
    public $isOpen = false;
    public $subject = '';
    public $body = '';
    public $attachments = [];
    
    // AI State
    public $isAnalyzing = false;
    public $analysis = null;

    protected $listeners = ['open-email' => 'loadEmail'];

    /**
     * Listen for the 'open-email' event from the timeline
     */
    #[On('open-email')]
    public function loadEmail($subject, $body, $attachments = [])
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->attachments = $attachments;
        
        $this->analysis = null; // Reset analysis for new email
        $this->isOpen = true;
    }

    /**
     * AI Analysis specifically for this email
     */
    public function analyze()
    {   
        $this->isAnalyzing = true;
        $this->analysis = null;

        $apiKey = config('services.gemini.api_key');
        $attachmentContext = collect($this->attachments)->pluck('name')->implode(', ');

        $prompt = "You are a legal assistant. Analyze this email. 
                   Subject: {$this->subject}. 
                   Body: " . strip_tags($this->body) . " 
                   Files: " . ($attachmentContext ?: 'None') . ". 
                   Instruction: Summarize risks and required user actions. Max 60 words. Plain text.";

        try {
            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]]
            ]);

            $this->analysis = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'No insights found.';
        } catch (\Exception $e) {
            Log::error("Email Analysis Error: " . $e->getMessage());
            $this->analysis = "Error connecting to AI assistant.";
        }

        $this->isAnalyzing = false;
    }

    public function close()
    {
        $this->isOpen = false;
        $this->reset(['subject', 'body', 'attachments', 'analysis', 'isAnalyzing']);
    }

    public function render()
    {
        return view('livewire.user.cases.email-viewer');
    }
}