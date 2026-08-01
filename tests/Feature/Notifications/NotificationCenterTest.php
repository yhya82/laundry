<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Layout\NotificationCenter;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_count_only_counts_in_app_unarchived_unread_for_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Notification::create(['recipient_type' => 'user', 'recipient_id' => $user->id, 'type' => 't', 'channel' => 'in_app', 'title' => 'A', 'message' => 'A', 'delivery_status' => 'sent']);
        Notification::create(['recipient_type' => 'user', 'recipient_id' => $user->id, 'type' => 't', 'channel' => 'email', 'title' => 'B', 'message' => 'B', 'delivery_status' => 'sent']);
        Notification::create(['recipient_type' => 'user', 'recipient_id' => $user->id, 'type' => 't', 'channel' => 'in_app', 'title' => 'C', 'message' => 'C', 'delivery_status' => 'sent', 'read_at' => now()]);
        Notification::create(['recipient_type' => 'user', 'recipient_id' => $other->id, 'type' => 't', 'channel' => 'in_app', 'title' => 'D', 'message' => 'D', 'delivery_status' => 'sent']);

        Livewire::actingAs($user)
            ->test(NotificationCenter::class)
            ->assertViewHas('unreadCount', 1);
    }

    public function test_mark_as_read_updates_only_that_notification(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create(['recipient_type' => 'user', 'recipient_id' => $user->id, 'type' => 't', 'channel' => 'in_app', 'title' => 'A', 'message' => 'A', 'delivery_status' => 'sent']);

        Livewire::actingAs($user)
            ->test(NotificationCenter::class)
            ->call('markAsRead', $notification->id)
            ->assertViewHas('unreadCount', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        $user = User::factory()->create();
        Notification::create(['recipient_type' => 'user', 'recipient_id' => $user->id, 'type' => 't', 'channel' => 'in_app', 'title' => 'A', 'message' => 'A', 'delivery_status' => 'sent']);
        Notification::create(['recipient_type' => 'user', 'recipient_id' => $user->id, 'type' => 't', 'channel' => 'in_app', 'title' => 'B', 'message' => 'B', 'delivery_status' => 'sent']);

        Livewire::actingAs($user)
            ->test(NotificationCenter::class)
            ->call('markAllAsRead')
            ->assertViewHas('unreadCount', 0);
    }

    public function test_archived_notifications_are_excluded_from_the_recent_list(): void
    {
        $user = User::factory()->create();
        Notification::create(['recipient_type' => 'user', 'recipient_id' => $user->id, 'type' => 't', 'channel' => 'in_app', 'title' => 'Archived', 'message' => 'A', 'delivery_status' => 'sent', 'archived_at' => now()]);

        Livewire::actingAs($user)
            ->test(NotificationCenter::class)
            ->assertDontSee('Archived');
    }
}
