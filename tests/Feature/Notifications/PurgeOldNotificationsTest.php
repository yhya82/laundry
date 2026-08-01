<?php

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeOldNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class); // retention_days = 90
    }

    private function makeNotification(array $overrides = []): Notification
    {
        $notification = Notification::create(array_merge([
            'recipient_type' => 'user',
            'recipient_id' => 1,
            'type' => 'test',
            'channel' => 'in_app',
            'title' => 'T',
            'message' => 'M',
            'delivery_status' => 'sent',
        ], $overrides));

        if (isset($overrides['created_at'])) {
            $notification->timestamps = false;
            $notification->forceFill(['created_at' => $overrides['created_at']])->save();
        }

        return $notification;
    }

    public function test_purges_old_read_notifications(): void
    {
        $this->makeNotification(['read_at' => now()->subDays(100), 'created_at' => now()->subDays(100)]);

        $this->artisan('app:purge-old-notifications')->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_does_not_purge_old_unread_unarchived_notifications(): void
    {
        $this->makeNotification(['created_at' => now()->subDays(100)]);

        $this->artisan('app:purge-old-notifications');

        $this->assertSame(1, Notification::count());
    }

    public function test_does_not_purge_recent_read_notifications(): void
    {
        $this->makeNotification(['read_at' => now()->subDay(), 'created_at' => now()->subDay()]);

        $this->artisan('app:purge-old-notifications');

        $this->assertSame(1, Notification::count());
    }

    public function test_purges_old_archived_notifications_even_if_unread(): void
    {
        $this->makeNotification(['archived_at' => now()->subDays(100), 'created_at' => now()->subDays(100)]);

        $this->artisan('app:purge-old-notifications');

        $this->assertSame(0, Notification::count());
    }
}
