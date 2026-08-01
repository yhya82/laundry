<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers a single already-created `notifications` row over its
 * non-in_app channel and records the outcome back onto that row
 * (delivery_status/failed_reason) — never re-creates or duplicates the
 * row, that already happened in NotificationService::notify().
 *
 * No SMS/WhatsApp provider is installed in this environment (confirmed:
 * no Twilio/Vonage/similar in composer.json) — those two channels are
 * marked 'failed' with an honest failed_reason rather than silently
 * pretending to send, the same way MAIL_MAILER=log already makes email a
 * local-only stand-in rather than real delivery in this environment.
 */
class DeliverExternalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $notificationId) {}

    public function handle(): void
    {
        $notification = Notification::find($this->notificationId);

        if (! $notification || $notification->delivery_status !== 'pending') {
            return;
        }

        match ($notification->channel) {
            'email' => $this->deliverEmail($notification),
            'sms', 'whatsapp' => $notification->update([
                'delivery_status' => 'failed',
                'failed_reason' => 'No '.strtoupper($notification->channel).' provider is configured in this environment.',
            ]),
            default => null,
        };
    }

    private function deliverEmail(Notification $notification): void
    {
        $email = $this->recipientEmail($notification);

        if (! $email) {
            $notification->update(['delivery_status' => 'failed', 'failed_reason' => 'Recipient has no email address on file.']);

            return;
        }

        try {
            Mail::raw($notification->message, function ($mail) use ($notification, $email) {
                $mail->to($email)->subject($notification->title);
            });

            $notification->update(['delivery_status' => 'sent']);
        } catch (\Throwable $e) {
            Log::warning('Notification email delivery failed', ['notification_id' => $notification->id, 'error' => $e->getMessage()]);
            $notification->update(['delivery_status' => 'failed', 'failed_reason' => $e->getMessage()]);
        }
    }

    private function recipientEmail(Notification $notification): ?string
    {
        return $notification->recipient_type === 'user'
            ? User::find($notification->recipient_id)?->email
            : Customer::find($notification->recipient_id)?->email;
    }
}
