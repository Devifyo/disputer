<?php

namespace App\Services;

use App\Models\DisputeCase;
use App\Models\User;
use App\Models\UserEmailConfig;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailService
{
    /**
     * Send an email using the user's custom SMTP settings and log it to the case timeline.
     *
     * @param User $user
     * @param DisputeCase $case
     * @param string $recipient
     * @param string $subject
     * @param string $body
     * @return void
     * @throws \Exception
     */
    public function sendAndLog(User $user, DisputeCase $case, string $recipient, string $subject, string $body)
    {
        // 1. Retrieve User's SMTP Configuration
        $emailConfig = UserEmailConfig::where('user_id', $user->id)->first();

        if (!$emailConfig) {
            throw new \Exception('SMTP settings not found. Please configure your email settings in your profile.');
        }

        // 2. Configure the Mailer Dynamically
        $this->configureMailer($emailConfig);

        // 3. Send the Email
        try {
            Mail::raw($body, function ($message) use ($recipient, $subject, $emailConfig) {
                $message->to($recipient)
                        ->subject($subject)
                        ->from($emailConfig->from_email, $emailConfig->from_name ?? 'Dispute Manager');
            });
        } catch (\Exception $e) {
            // Log the raw error for debugging but throw a clean message to the user
            Log::error("Custom SMTP Error for User {$user->id}: " . $e->getMessage());
            throw new \Exception("Failed to send email via your SMTP server: " . $e->getMessage());
        }

        // 4. Log Activity to Case Timeline
        $case->timeline()->create([
            'type' => 'email_sent',
            'readable_type' => 'Email Sent',
            'description' => "Sent email to {$recipient}",
            'occurred_at' => now(),
            'metadata' => [
                'subject' => $subject,
                'recipient' => $recipient,
                'full_body' => $body,
                'sent_via' => 'custom_smtp',
                'smtp_host' => $emailConfig->smtp_host // Optional: log which server was used
            ]
        ]);
    }

    /**
     * Overrides the default Laravel Mail config with the user's settings.
     */
    private function configureMailer(UserEmailConfig $config)
    {
        // Set the transport configuration
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $config->smtp_host);
        Config::set('mail.mailers.smtp.port', $config->smtp_port);
        Config::set('mail.mailers.smtp.encryption', $config->smtp_encryption);
        Config::set('mail.mailers.smtp.username', $config->smtp_username);
        
        // The accessor in the model automatically decrypts this
        Config::set('mail.mailers.smtp.password', $config->smtp_password);

        // Set global "From" address for this request
        Config::set('mail.from.address', $config->from_email);
        Config::set('mail.from.name', $config->from_name);

        // CRITICAL: Purge the existing mailer instance so Laravel builds a new one with our new config
        // If you miss this, Laravel will keep using the previous connection (e.g. Mailtrap or your env defaults)
        Mail::purge('smtp');
    }
}