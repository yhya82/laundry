<?php

namespace App\Livewire\Layout;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Shell only per Phase 3 — no module fires a business notification yet.
 * Event -> row -> live badge update is wired for real in Phase 12, once
 * something actually calls into it.
 */
class NotificationCenter extends Component
{
    public function unreadCount(): int
    {
        return Notification::where('recipient_type', 'user')
            ->where('recipient_id', Auth::id())
            ->whereNull('read_at')
            ->count();
    }

    public function recent()
    {
        return Notification::where('recipient_type', 'user')
            ->where('recipient_id', Auth::id())
            ->latest()
            ->limit(8)
            ->get();
    }

    public function render()
    {
        return view('livewire.layout.notification-center', [
            'unreadCount' => $this->unreadCount(),
            'notifications' => $this->recent(),
        ]);
    }
}
