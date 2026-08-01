<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Notifications\Preferences;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_all_channels_enabled_with_no_existing_rows(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Preferences::class)
            ->assertSet('channels.in_app', true)
            ->assertSet('channels.email', true)
            ->assertSet('channels.sms', true)
            ->assertSet('channels.whatsapp', true);
    }

    public function test_can_disable_a_channel(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Preferences::class)
            ->set('channels.sms', false)
            ->call('save');

        $this->assertDatabaseHas('notification_preferences', [
            'owner_type' => 'user', 'owner_id' => $user->id, 'channel' => 'sms', 'enabled' => false,
        ]);
    }

    public function test_only_affects_the_acting_users_own_preferences(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        NotificationPreference::create(['owner_type' => 'user', 'owner_id' => $other->id, 'channel' => 'email', 'enabled' => false]);

        Livewire::actingAs($user)
            ->test(Preferences::class)
            ->call('save');

        $this->assertDatabaseHas('notification_preferences', ['owner_id' => $other->id, 'channel' => 'email', 'enabled' => false]);
        $this->assertDatabaseHas('notification_preferences', ['owner_id' => $user->id, 'channel' => 'email', 'enabled' => true]);
    }
}
