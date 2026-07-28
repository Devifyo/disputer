<?php

namespace App\Support\Alerts;

/**
 * One operational alert for the team, independent of how it is delivered.
 * Services describe WHAT happened; AdminNotifier decides who hears about it
 * and through which channels.
 */
final class AdminAlert
{
    /**
     * @param string $type         One of AdminAlertRecipients::TYPE_* - decides who is subscribed.
     * @param string $title        Headline: the in-app bell entry and the email subject line.
     * @param string $description  One or two sentences of context for the bell.
     * @param string $url          Where the alert leads in the admin.
     * @param string|null $template Admin-editable email template slug; null = in-app only.
     * @param array<string, string> $replacements Placeholders for that template ([NAME] is added per recipient).
     */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $description,
        public readonly string $url,
        public readonly ?string $template = null,
        public readonly array $replacements = [],
    ) {
    }
}
