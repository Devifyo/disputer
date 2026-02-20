<?php

use App\Models\EmailTemplate;
use App\Mail\GenericEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

if (!function_exists('send_dynamic_email')) {
    /**
     * Send an email using a database template.
     *
     * @param string $toEmail The recipient's email address.
     * @param string $templateSlug The slug of the template to use.
     * @param array $replacements Associative array of ['[PLACEHOLDER]' => 'Value'].
     * @return bool True on success, false on failure.
     */
    function send_dynamic_email(string $toEmail, string $templateSlug, array $replacements = []): bool
    {
        $template = EmailTemplate::where('slug', $templateSlug)->where('is_active', true)->first();

        if (!$template) {
            Log::warning("Email failed: Template '{$templateSlug}' not found or inactive.");
            return false;
        }

        // Swap placeholders in the Body
        $parsedBody = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->body
        );

        // Swap placeholders in the Subject (just in case you use them there too!)
        $parsedSubject = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->subject
        );

        try {
            Mail::to($toEmail)->send(new GenericEmail($parsedSubject, $parsedBody));
            return true;
        } catch (\Exception $e) {
            Log::error("Email failed to send to {$toEmail}: " . $e->getMessage());
            return false;
        }
    }
}