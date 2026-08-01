<?php

namespace App\Livewire\Notifications;

use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Self-service — any authenticated user manages their own channels, no
 * permission gate needed (this only ever touches the acting user's own
 * owner_type='user'/owner_id row, never another user's).
 */
class Preferences extends Component
{
    /** @var array<string, bool> */
    public array $channels = [];

    private const AVAILABLE = ['in_app', 'email', 'sms', 'whatsapp'];

    public function mount(): void
    {
        $existing = NotificationPreference::where('owner_type', 'user')
            ->where('owner_id', Auth::id())
            ->pluck('enabled', 'channel');

        foreach (self::AVAILABLE as $channel) {
            // No row yet = on by default, matching notification_preferences.enabled's own DB default.
            $this->channels[$channel] = $existing[$channel] ?? true;
        }
    }

    public function save(): void
    {
        foreach ($this->channels as $channel => $enabled) {
            NotificationPreference::updateOrCreate(
                ['owner_type' => 'user', 'owner_id' => Auth::id(), 'channel' => $channel],
                ['enabled' => $enabled],
            );
        }

        $this->dispatch('notify', type: 'success', message: 'Notification preferences saved.');
    }

    public function render()
    {
        return view('livewire.notifications.preferences')->title('Notification Preferences');
    }
}
