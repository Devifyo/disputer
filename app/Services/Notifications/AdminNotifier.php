<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Services\Claims\AdminAlertRecipients;
use App\Support\Alerts\AdminAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The one place that answers "the team needs to know" - for every kind of
 * operational alert. Services raise an AdminAlert; this decides who receives
 * it (the recipients configured per alert type, falling back to the admin
 * accounts) and delivers it on every channel: email templates the admins can
 * edit, plus the in-app bell.
 *
 * Adding a channel (Slack, SMS) means changing this class only - the
 * services that raise alerts never change.
 */
class AdminNotifier
{
    public function send(AdminAlert $alert): void
    {
        $this->email($alert);
        $this->bell($alert);
    }

    /** Queued so inbound webhooks and email processing never wait on SMTP. */
    private function email(AdminAlert $alert): void
    {
        if ($alert->template === null) {
            return;
        }

        foreach (AdminAlertRecipients::for($alert->type) as $recipient) {
            dispatch(function () use ($alert, $recipient) {
                try {
                    send_dynamic_email($recipient['email'], $alert->template, [
                        '[NAME]' => $recipient['name'],
                    ] + $alert->replacements);
                } catch (Throwable $e) {
                    Log::warning('Admin alert email failed', [
                        'type'  => $alert->type,
                        'email' => $recipient['email'],
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }
    }

    /** Every admin account sees the alert in the app, whoever gets the email. */
    private function bell(AdminAlert $alert): void
    {
        $admins = User::role('admin')->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new AdminAlertNotification($alert));
        }
    }
}
