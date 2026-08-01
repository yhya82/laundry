<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Jobs\DeliverExternalNotification;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * The single entry point every module's service calls to raise a
 * notification — see IMPLEMENTATION_PLAN.md Phase 12's "event -> notification
 * wiring" task. No §3.5 type taxonomy exists anywhere in this repo (confirmed:
 * `notifications.type` carries no CHECK constraint, and no seeder/comment
 * enumerates business event strings) — the TYPE_* constants below are this
 * build's own taxonomy, inferred from what Phases 6-11 actually implemented,
 * not ported from a spec.
 *
 * One logical event fans out to one `notifications` row per channel that's
 * actually enabled for that recipient (chk_notifications_channel: in_app,
 * email, sms, whatsapp) — never a single multi-channel row, since the
 * schema models channel as a per-row column. Staff (`recipient_type=user`)
 * default to in_app+email, since only they have a session to view the
 * in-app bell in; customers default to email+sms+whatsapp, since this
 * build has no customer-facing portal for an in_app row to ever be seen in.
 */
class NotificationService
{
    public const TYPE_ORDER_READY = 'order_ready';

    public const TYPE_ORDER_CANCELLED = 'order_cancelled';

    public const TYPE_PAYMENT_RECEIVED = 'payment_received';

    public const TYPE_REFUND_PROCESSED = 'refund_processed';

    public const TYPE_DAMAGE_RESOLVED = 'damage_resolved';

    public const TYPE_DAMAGE_REJECTED = 'damage_rejected';

    public const TYPE_DELIVERY_ASSIGNED = 'delivery_assigned';

    public const TYPE_DELIVERY_OUT_FOR_DELIVERY = 'delivery_out_for_delivery';

    public const TYPE_DELIVERY_DELIVERED = 'delivery_delivered';

    public const TYPE_DELIVERY_FAILED = 'delivery_failed';

    public const TYPE_EXPENSE_PENDING_APPROVAL = 'expense_pending_approval';

    public const TYPE_EXPENSE_APPROVED = 'expense_approved';

    public const TYPE_EXPENSE_REJECTED = 'expense_rejected';

    public const TYPE_COLLECTION_SCHEDULED = 'collection_scheduled';

    private const STAFF_CHANNELS = ['in_app', 'email'];

    private const CUSTOMER_CHANNELS = ['email', 'sms', 'whatsapp'];

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function notifyUser(int $userId, string $type, string $title, string $message, ?array $data = null): Collection
    {
        return $this->fanOut('user', $userId, $type, $title, $message, $data, self::STAFF_CHANNELS);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function notifyCustomer(int $customerId, string $type, string $title, string $message, ?array $data = null): Collection
    {
        return $this->fanOut('customer', $customerId, $type, $title, $message, $data, self::CUSTOMER_CHANNELS);
    }

    /**
     * Every user holding the given permission — used for approval-style
     * notifications (e.g. "an expense needs your approval") that don't
     * have one single fixed recipient.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function notifyUsersWithPermission(string $permissionSlug, string $type, string $title, string $message, ?array $data = null): void
    {
        User::whereHas('roles.permissions', fn ($q) => $q->where('slug', $permissionSlug))
            ->get()
            ->each(fn (User $user) => $this->notifyUser($user->id, $type, $title, $message, $data));
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<int, string>  $defaultChannels
     */
    private function fanOut(string $recipientType, int $recipientId, string $type, string $title, string $message, ?array $data, array $defaultChannels): Collection
    {
        return collect($defaultChannels)
            ->filter(fn ($channel) => $this->isChannelEnabled($recipientType, $recipientId, $channel))
            ->map(function (string $channel) use ($recipientType, $recipientId, $type, $title, $message, $data) {
                $notification = Notification::create([
                    'recipient_type' => $recipientType,
                    'recipient_id' => $recipientId,
                    'type' => $type,
                    'channel' => $channel,
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                    'delivery_status' => $channel === 'in_app' ? 'sent' : 'pending',
                ]);

                if ($channel === 'in_app') {
                    $this->broadcastSafely($notification);
                } else {
                    DeliverExternalNotification::dispatch($notification->id);
                }

                return $notification;
            });
    }

    private function isChannelEnabled(string $ownerType, int $ownerId, string $channel): bool
    {
        if ($channel === 'in_app' && ! $this->inAppGloballyEnabled()) {
            return false;
        }

        $preference = NotificationPreference::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('channel', $channel)
            ->first();

        // No row yet = not opted out — notification_preferences.enabled
        // defaults to true at the schema level, so an owner who's never
        // touched their preferences is "on" for every channel.
        return $preference?->enabled ?? true;
    }

    private function inAppGloballyEnabled(): bool
    {
        return (Setting::where('setting_group', 'notifications')
            ->where('setting_key', 'in_app_enabled')
            ->value('setting_value') ?? 'true') === 'true';
    }

    /**
     * ShouldBroadcastNow dispatches synchronously and throws if Reverb is
     * unreachable (confirmed directly: a plain cURL-connection-refused
     * BroadcastException) — real-time delivery must never be able to fail
     * the business operation that triggered it (recording a payment,
     * advancing an order stage, ...). The `notifications` row is already
     * committed by the time this runs, so a lost live push just means the
     * bell updates on next page load instead of instantly — degraded, not
     * broken.
     */
    private function broadcastSafely(Notification $notification): void
    {
        try {
            NotificationCreated::dispatch($notification);
        } catch (\Throwable $e) {
            Log::warning('Notification broadcast failed', ['notification_id' => $notification->id, 'error' => $e->getMessage()]);
        }
    }
}
