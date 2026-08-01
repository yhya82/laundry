<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * The application-side equivalent of the plan's referenced
 * evt_purge_old_notifications MySQL EVENT — no such EVENT actually exists
 * anywhere in this repo's migrations (confirmed: zero `CREATE EVENT`
 * statements), so this owns purging outright rather than duplicating a
 * DB-side job that was never built. Only read-or-archived notifications
 * are eligible — an unread, unarchived notification is never purged
 * regardless of age, so nothing a user hasn't seen yet can silently vanish.
 */
class PurgeOldNotifications extends Command
{
    protected $signature = 'app:purge-old-notifications';

    protected $description = 'Delete read or archived notifications older than the configured retention period.';

    public function handle(): int
    {
        $days = (int) (Setting::where('setting_group', 'notifications')
            ->where('setting_key', 'retention_days')
            ->value('setting_value') ?? 90);

        $count = Notification::where('created_at', '<', now()->subDays($days))
            ->where(fn ($q) => $q->whereNotNull('read_at')->orWhereNotNull('archived_at'))
            ->delete();

        $this->components->info("Purged {$count} notification(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
