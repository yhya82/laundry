<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Phase 3's Reverb proof-of-wiring: an Administrator changing the business
 * name should update the sidebar (and, once built, the login page/receipts/
 * reports) with no page refresh — see MASTER_SPECIFICATION.md §3.2.
 * Broadcast on a public channel: the business name isn't sensitive, and it's
 * shown to unauthenticated visitors on the login page too.
 */
class BrandingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $businessName) {}

    public function broadcastOn(): array
    {
        return [new Channel('branding')];
    }

    /**
     * Without this, Laravel broadcasts under the fully-qualified class name
     * (App\Events\BrandingUpdated) by default — confirmed via a raw
     * WebSocket listener during Phase 3 verification. Livewire's
     * #[On('echo:branding,BrandingUpdated')] listener (Sidebar.php) expects
     * the short name, so this has to be explicit, not left to the default.
     */
    public function broadcastAs(): string
    {
        return 'BrandingUpdated';
    }
}
