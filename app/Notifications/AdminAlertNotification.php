<?php

namespace App\Notifications;

use App\Support\Alerts\AdminAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The in-app half of an operational alert - what lights up the admin's bell.
 * The email half is admin-editable templates, sent by AdminNotifier.
 */
class AdminAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AdminAlert $alert)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind'        => $this->alert->type,
            'title'       => $this->alert->title,
            'description' => $this->alert->description,
            // The bell renders this key for every notification kind.
            'claim_url'   => $this->alert->url,
        ];
    }
}
