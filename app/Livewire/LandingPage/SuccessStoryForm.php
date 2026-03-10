<?php

namespace App\Livewire\LandingPage;

use App\Models\SuccessStory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

class SuccessStoryForm extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public $first_name = '';

    #[Validate('nullable|email|max:255')]
    public $email = '';

    // Added a sensible minimum length so you don't get 1-word spam submissions
    #[Validate('required|string|min:10')]
    public $story = '';

    // Validates each file in the array individually (Max 10MB per file)
    #[Validate(['media_files.*' => 'nullable|file|mimes:png,jpg,jpeg,pdf|max:10240'])]
    public $media_files = [];

    /**
     * Removes a specific file from the array before submission.
     */
    public function removeFile(int $index): void
    {
        array_splice($this->media_files, $index, 1);
    }

    /**
     * Handles the form submission, file storage, and database creation.
     */
    public function submit(): void
    {
        $this->validate();

        $uploadedPaths = [];

        // Safely iterate and store each uploaded file
        if (!empty($this->media_files)) {
            foreach ($this->media_files as $file) {
                // Stores in storage/app/public/success-stories
                $uploadedPaths[] = $file->store('success-stories', 'public');
            }
        }

        // Save the record to the database
        SuccessStory::create([
            // Auth::id() elegantly returns the ID if logged in, or null if a guest
            'user_id'     => Auth::id(), 
            'first_name'  => $this->first_name,
            'email'       => $this->email,
            'story'       => $this->story,
            'media_files' => !empty($uploadedPaths) ? $uploadedPaths : null,
        ]);

        // Clear the form fields entirely
        $this->reset(['first_name', 'email', 'story', 'media_files']);

        // Dispatch event to close the Alpine.js modal and trigger the success alert
        $this->dispatch('story-submitted');
    }

    public function render()
    {
        return view('livewire.landing-page.success-story-form');
    }
}