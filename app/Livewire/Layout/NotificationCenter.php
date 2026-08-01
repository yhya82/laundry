<?php

namespace App\Livewire\Layout;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Wired for real in Phase 12 — mount() no longer just renders whatever
 * existed at page-load; a new NotificationCreated broadcast (private
 * per-user channel, see app/Events/NotificationCreated.php) pushes a live
 * re-render, closing the "not just Echo-session-local state" exit
 * criteria: since the count is recomputed from the notifications table on
 * every listener fire (not incremented client-side), a page refresh
 * always agrees with what the badge showed a moment before.
 */
class NotificationCenter extends Component
{
    /**
     * Livewire's dynamic-channel-name idiom — the channel name has to
     * include the authenticated user's id, which #[On(...)] attributes
     * can't express (they're resolved statically, not per-instance).
     *
     * @return array<string, string>
     */
    protected function getListeners()
    {
        return [
            'echo-private:App.Models.User.'.Auth::id().',NotificationCreated' => '$refresh',
        ];
    }

    public function unreadCount(): int
    {
        return Notification::where('recipient_type', 'user')
            ->where('recipient_id', Auth::id())
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->count();
    }

    public function recent()
    {
        return Notification::where('recipient_type', 'user')
            ->where('recipient_id', Auth::id())
            ->where('channel', 'in_app')
            ->whereNull('archived_at')
            ->latest()
            ->limit(8)
            ->get();
    }

    public function markAsRead(int $notificationId): void
    {
        Notification::where('id', $notificationId)
            ->where('recipient_type', 'user')
            ->where('recipient_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        Notification::where('recipient_type', 'user')
            ->where('recipient_id', Auth::id())
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function render()
    {
        return view('livewire.layout.notification-center', [
            'unreadCount' => $this->unreadCount(),
            'notifications' => $this->recent(),
        ]);
    }
}
