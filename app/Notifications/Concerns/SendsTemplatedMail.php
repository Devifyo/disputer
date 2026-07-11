<?php

namespace App\Notifications\Concerns;

use App\Mail\GenericEmail;
use App\Models\EmailTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Renders a notification's mail through the admin-managed email templates
 * (Admin → Templates), falling back to the notification's built-in copy
 * when the template is missing or deactivated.
 */
trait SendsTemplatedMail
{
    protected function templatedMail(object $notifiable, string $slug, array $replacements, MailMessage $fallback): MailMessage|Mailable
    {
        $template = EmailTemplate::where('slug', $slug)->where('is_active', true)->first();

        if (!$template) {
            return $fallback;
        }

        $swap = fn (string $text) => str_replace(array_keys($replacements), array_values($replacements), $text);

        return (new GenericEmail($swap($template->subject), $swap($template->body)))
            ->to($notifiable->routeNotificationFor('mail') ?: $notifiable->email);
    }
}
