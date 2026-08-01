<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * General-purpose counterpart to BrandingUpdated (Phase 3) — any settings
 * change propagates live per §3.2/§16's "real-time settings updates" goal.
 * No consumer beyond the sidebar's business_name case exists yet (that one
 * still uses BrandingUpdated specifically, proven end-to-end in Phase 3);
 * this exists so the mechanism is in place for future settings-driven UI
 * (e.g. express_enabled toggling the POS terminal in Phase 6) without
 * requiring another broadcasting round-trip to be invented later.
 */
class SettingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $group,
        public string $key,
        public string $value,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('settings')];
    }

    public function broadcastAs(): string
    {
        return 'SettingUpdated';
    }
}
