<?php

namespace App\Services;

use App\Models\DamageEvidence;
use App\Models\DamageReport;
use App\Models\LaundryOrder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Owns the damage-report lifecycle: report -> (under_review) -> approved
 * (resolution plan + compensation split set, still mutable) -> resolved
 * (compensation actually disbursed, then locked by trg_damage_reports_
 * prevent_resolved_tamper) or -> rejected. Store credit compensation is
 * only ever written through StoreCreditService — never an ad-hoc balance
 * update — per IMPLEMENTATION_PLAN.md Phase 9's exit criteria.
 */
class DamageService
{
    public function __construct(private NotificationService $notificationService) {}

    /**
     * @param  array<int, array{laundry_order_item_id: int, damage_type_id: int, severity: string, description: ?string}>  $items
     */
    public function createReport(LaundryOrder $order, string $description, array $items, User $actor): DamageReport
    {
        if ($order->status === 'cancelled') {
            // trg_damage_reports_check_order_status (BR-045) is the real
            // authority — this pre-check just keeps the failure readable.
            throw ValidationException::withMessages([
                'description' => 'Damage cannot be reported against a cancelled order.',
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'At least one damaged item must be selected.']);
        }

        // trg_damage_items_check_scope (BR-046) is the real authority —
        // this pre-check keeps a mismatched item ID from surfacing as a
        // raw SQLSTATE mid-transaction.
        $validItemIds = $order->loadMissing('packages.items')->packages->pluck('items')->flatten()->pluck('id')->all();
        foreach ($items as $item) {
            if (! in_array($item['laundry_order_item_id'], $validItemIds, true)) {
                throw ValidationException::withMessages([
                    'items' => 'One of the selected items does not belong to this order.',
                ]);
            }
        }

        return DB::transaction(function () use ($order, $description, $items, $actor) {
            $report = DamageReport::create([
                'laundry_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'status' => 'reported',
                'description' => $description,
                'created_by' => $actor->id,
            ]);

            foreach ($items as $item) {
                $report->items()->create([
                    'laundry_order_item_id' => $item['laundry_order_item_id'],
                    'damage_type_id' => $item['damage_type_id'],
                    'severity' => $item['severity'],
                    'description' => $item['description'] ?: null,
                ]);
            }

            return $report->fresh('items');
        });
    }

    public function addEvidence(DamageReport $report, UploadedFile $file, User $actor): DamageEvidence
    {
        $path = $file->store("damage-evidence/{$report->id}", 'public');

        return $report->evidence()->create([
            'file_path' => $path,
            'uploaded_by' => $actor->id,
        ]);
    }

    public function deleteEvidence(DamageEvidence $evidence): void
    {
        Storage::disk('public')->delete($evidence->file_path);
        $evidence->delete();
    }

    public function markUnderReview(DamageReport $report): DamageReport
    {
        $this->assertStatusIn($report, ['reported', 'under_review'], 'moved to review');

        $report->update(['status' => 'under_review']);

        return $report->fresh();
    }

    /**
     * Sets the resolution plan and compensation split. Deliberately does
     * NOT credit store credit yet — the report stays mutable (BR-063 only
     * locks once status = 'resolved'), so a mistaken split can still be
     * corrected by calling this again before resolve().
     */
    public function approve(DamageReport $report, string $resolutionType, string $cashAmount, string $creditAmount, User $actor): DamageReport
    {
        $this->assertStatusIn($report, ['reported', 'under_review', 'approved'], 'approved');

        if (bccomp($cashAmount, '0', 2) < 0 || bccomp($creditAmount, '0', 2) < 0) {
            throw ValidationException::withMessages(['cashAmount' => 'Compensation amounts cannot be negative.']);
        }

        $report->update([
            'status' => 'approved',
            'resolution_type' => $resolutionType,
            'cash_compensation_amount' => $cashAmount,
            'store_credit_compensation_amount' => $creditAmount,
            'approved_by' => $actor->id,
        ]);

        return $report->fresh();
    }

    public function reject(DamageReport $report, User $actor): DamageReport
    {
        $this->assertStatusIn($report, ['reported', 'under_review'], 'rejected');

        $report->update([
            'status' => 'rejected',
            'approved_by' => $actor->id,
        ]);

        $fresh = $report->fresh();

        $this->notificationService->notifyCustomer(
            $fresh->customer_id,
            NotificationService::TYPE_DAMAGE_REJECTED,
            'Damage claim rejected',
            "Your damage report on order {$fresh->laundryOrder->order_number} was not approved for compensation.",
            ['damage_report_id' => $fresh->id],
        );

        return $fresh;
    }

    /**
     * The moment compensation is actually disbursed. Cash compensation is
     * just recorded here — handing it over is an offline/manual step this
     * system doesn't model further. Store credit compensation drives a
     * real StoreCreditService::credit() call, landing a genuine
     * store_credit_transactions row rather than a direct balance write.
     */
    public function resolve(DamageReport $report, User $actor, StoreCreditService $storeCreditService): DamageReport
    {
        if ($report->status !== 'approved') {
            throw new RuntimeException('A damage report must be approved with a resolution plan before it can be resolved.');
        }

        return DB::transaction(function () use ($report, $actor, $storeCreditService) {
            if (bccomp((string) $report->store_credit_compensation_amount, '0', 2) > 0) {
                $storeCreditService->credit(
                    $report->customer,
                    (string) $report->store_credit_compensation_amount,
                    'damage_compensation',
                    $actor,
                    'damage_reports',
                    $report->id,
                    "Compensation for damage report against order {$report->laundryOrder->order_number}",
                );
            }

            $report->update(['status' => 'resolved']);

            $fresh = $report->fresh();

            $this->notificationService->notifyCustomer(
                $fresh->customer_id,
                NotificationService::TYPE_DAMAGE_RESOLVED,
                'Damage claim resolved',
                "Your damage report on order {$fresh->laundryOrder->order_number} has been resolved ({$fresh->resolution_type}).",
                ['damage_report_id' => $fresh->id],
            );

            return $fresh;
        });
    }

    /**
     * trg_damage_reports_prevent_resolved_tamper (BR-063) is the real
     * authority against editing a resolved report's compensation/resolution
     * — nothing in this UI offers that action, but translated the same way
     * PaymentService does for its own BR-063 trigger, for consistency and
     * direct testability.
     */
    public function translateResolvedTamperError(QueryException $e): ?string
    {
        if ($e->getCode() === '45000' && str_contains($e->getMessage(), 'BR-063')) {
            return 'This damage report is already resolved — its resolution and compensation can no longer be changed.';
        }

        return null;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function assertStatusIn(DamageReport $report, array $allowed, string $verb): void
    {
        if (! in_array($report->status, $allowed, true)) {
            throw new RuntimeException("This damage report cannot be {$verb} from its current status (\"{$report->status}\").");
        }
    }
}
