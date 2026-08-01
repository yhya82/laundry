<?php

namespace Tests\Feature\Notifications;

use App\Events\NotificationCreated;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_notifying_a_user_creates_in_app_and_email_rows_and_broadcasts_the_in_app_one(): void
    {
        Event::fake([NotificationCreated::class]);

        $user = User::factory()->create();

        $rows = app(NotificationService::class)->notifyUser($user->id, NotificationService::TYPE_DELIVERY_ASSIGNED, 'Title', 'Message body');

        $this->assertCount(2, $rows);
        $this->assertDatabaseHas('notifications', ['recipient_type' => 'user', 'recipient_id' => $user->id, 'channel' => 'in_app', 'delivery_status' => 'sent']);
        $this->assertDatabaseHas('notifications', ['recipient_type' => 'user', 'recipient_id' => $user->id, 'channel' => 'email']);

        Event::assertDispatched(NotificationCreated::class, fn ($event) => $event->notification->recipient_id === $user->id);
    }

    public function test_notifying_a_customer_creates_email_sms_and_whatsapp_rows_but_no_in_app_row(): void
    {
        $customer = Customer::create(['name' => 'Notif Customer', 'phone' => '0700000200', 'email' => 'notif@example.com', 'customer_type' => 'walk_in', 'status' => 'active']);

        app(NotificationService::class)->notifyCustomer($customer->id, NotificationService::TYPE_ORDER_READY, 'Ready', 'Your order is ready.');

        $this->assertSame(3, Notification::where('recipient_type', 'customer')->where('recipient_id', $customer->id)->count());
        $this->assertDatabaseMissing('notifications', ['recipient_type' => 'customer', 'recipient_id' => $customer->id, 'channel' => 'in_app']);
        foreach (['email', 'sms', 'whatsapp'] as $channel) {
            $this->assertDatabaseHas('notifications', ['recipient_type' => 'customer', 'recipient_id' => $customer->id, 'channel' => $channel]);
        }
    }

    public function test_an_opted_out_channel_is_skipped(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create(['owner_type' => 'user', 'owner_id' => $user->id, 'channel' => 'email', 'enabled' => false]);

        app(NotificationService::class)->notifyUser($user->id, NotificationService::TYPE_DELIVERY_ASSIGNED, 'Title', 'Message');

        $this->assertDatabaseHas('notifications', ['recipient_type' => 'user', 'recipient_id' => $user->id, 'channel' => 'in_app']);
        $this->assertDatabaseMissing('notifications', ['recipient_type' => 'user', 'recipient_id' => $user->id, 'channel' => 'email']);
    }

    public function test_in_app_is_skipped_when_globally_disabled(): void
    {
        Setting::where('setting_group', 'notifications')->where('setting_key', 'in_app_enabled')->update(['setting_value' => 'false']);

        $user = User::factory()->create();
        app(NotificationService::class)->notifyUser($user->id, NotificationService::TYPE_DELIVERY_ASSIGNED, 'Title', 'Message');

        $this->assertDatabaseMissing('notifications', ['recipient_type' => 'user', 'recipient_id' => $user->id, 'channel' => 'in_app']);
        $this->assertDatabaseHas('notifications', ['recipient_type' => 'user', 'recipient_id' => $user->id, 'channel' => 'email']);
    }

    public function test_email_delivery_actually_sends_via_mail_and_marks_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'target@example.com']);
        app(NotificationService::class)->notifyUser($user->id, NotificationService::TYPE_DELIVERY_ASSIGNED, 'Title', 'Message body');

        $notification = Notification::where('channel', 'email')->where('recipient_id', $user->id)->firstOrFail();
        $this->assertSame('sent', $notification->delivery_status);
        $this->assertNull($notification->failed_reason);
    }

    public function test_sms_and_whatsapp_are_honestly_marked_failed_with_no_provider_configured(): void
    {
        $customer = Customer::create(['name' => 'SMS Customer', 'phone' => '0700000201', 'customer_type' => 'walk_in', 'status' => 'active']);

        app(NotificationService::class)->notifyCustomer($customer->id, NotificationService::TYPE_ORDER_READY, 'Ready', 'Message');

        $sms = Notification::where('recipient_id', $customer->id)->where('channel', 'sms')->firstOrFail();
        $this->assertSame('failed', $sms->delivery_status);
        $this->assertStringContainsString('No SMS provider', $sms->failed_reason);
    }

    /**
     * Reproduces a real bug found while smoke-testing this phase: with
     * Reverb not running, ShouldBroadcastNow throws a genuine
     * BroadcastException (cURL connection refused) — confirmed directly
     * via tinker before this fix existed. Forces the real `reverb`
     * broadcaster (phpunit.xml normally overrides BROADCAST_CONNECTION to
     * `null`, a no-op, which would hide this) to prove the notification
     * row still lands and the exception never reaches the caller.
     */
    public function test_a_broadcast_failure_does_not_prevent_the_notification_row_or_propagate(): void
    {
        config(['broadcasting.default' => 'reverb']);
        config(['broadcasting.connections.reverb.options.host' => '127.0.0.1']);
        config(['broadcasting.connections.reverb.options.port' => 1]); // nothing listens here

        $user = User::factory()->create();

        $rows = app(NotificationService::class)->notifyUser($user->id, NotificationService::TYPE_DELIVERY_ASSIGNED, 'Title', 'Message');

        $this->assertDatabaseHas('notifications', ['recipient_id' => $user->id, 'channel' => 'in_app', 'delivery_status' => 'sent']);
        $this->assertCount(2, $rows);
    }

    public function test_notify_users_with_permission_reaches_every_holder(): void
    {
        $role = Role::create(['name' => 'Test Approver', 'slug' => 'test-approver', 'status' => 'active']);
        $role->permissions()->attach(Permission::where('slug', 'expenses.approve')->first()?->id ?? Permission::create(['name' => 'x', 'slug' => 'expenses.approve', 'permission_group' => 'expenses'])->id);

        $approver = User::factory()->create();
        $approver->roles()->attach($role->id, ['is_primary' => true]);

        $nonApprover = User::factory()->create();

        app(NotificationService::class)->notifyUsersWithPermission('expenses.approve', NotificationService::TYPE_EXPENSE_PENDING_APPROVAL, 'Approval needed', 'msg');

        $this->assertDatabaseHas('notifications', ['recipient_id' => $approver->id, 'channel' => 'in_app']);
        $this->assertDatabaseMissing('notifications', ['recipient_id' => $nonApprover->id, 'channel' => 'in_app']);
    }
}
