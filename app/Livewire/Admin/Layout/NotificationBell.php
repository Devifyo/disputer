<?php

namespace App\Livewire\Admin\Layout;

use Livewire\Component;

/** The admin's in-app notification bell - unread badge, dropdown, mark read. */
class NotificationBell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = !$this->open;

        if ($this->open) {
            auth()->user()->unreadNotifications->markAsRead();
        }
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.admin.layout.notification-bell', [
            'unread'        => $user->unreadNotifications()->count(),
            'notifications' => $this->open
                ? $user->notifications()->latest()->limit(15)->get()
                : collect(),
        ]);
    }
}
