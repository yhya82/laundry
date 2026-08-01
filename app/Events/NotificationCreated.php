<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Drives the notification bell's live badge — see IMPLEMENTATION_PLAN.md
 * Phase 12's exit criteria ("not just Echo-session-local state"). Only
 * ever dispatched for channel='in_app' rows (the only channel with a UI
 * to update); email/sms/whatsapp rows are delivery bookkeeping, not
 * broadcast. Reuses the private per-user channel already authorized in
 * routes/channels.php (`App.Models.User.{id}`, stock Laravel default) —
 * no new channel definition needed. Public `branding`/`settings` channels
 * from Phase 3 don't fit here since notifications are recipient-specific,
 * not broadcast to every visitor.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->notification->recipient_id)];
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'type' => $this->notification->type,
        ];
    }
}
