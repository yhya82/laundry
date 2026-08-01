<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the discrepancy flagged on App\Models\LaundryOrderStageHistory:
 * MASTER_SPECIFICATION.md's permissions.sql groups laundry_order_stage_history
 * with the pure append-only logs (REVOKE UPDATE, DELETE entirely), but the
 * column design (nullable completed_at) only makes sense as an interval —
 * a row opened when a stage starts, closed when it ends. That shape matches
 * payments/refunds/receipts/damage_reports (DELETE forbidden, UPDATE
 * legitimate but constrained), not activity_log/delivery_status_history/
 * customer_timeline_events (no UPDATE at all, ever).
 *
 * This is the 25th trigger — not part of the master spec's original 24,
 * added here to close that gap. It allows exactly one legitimate mutation:
 * setting completed_at once, while it's still NULL. Everything else about
 * a recorded stage (laundry_order_id, stage, machine_id, started_at,
 * changed_by) is immutable once written, and completed_at itself can't be
 * changed again after it's set — mirroring the existing
 * trg_payments_prevent_paid_tamper / trg_lop_prevent_post_completion_tamper
 * pattern already used elsewhere in this schema. DELETE is blocked at the
 * model layer (ForbidsDelete on LaundryOrderStageHistory), matching how
 * Payment/Refund/Receipt/DamageReport are guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_losh_prevent_tamper
            BEFORE UPDATE ON laundry_order_stage_history
            FOR EACH ROW
            BEGIN
                IF NOT (NEW.laundry_order_id <=> OLD.laundry_order_id)
                   OR NOT (NEW.stage <=> OLD.stage)
                   OR NOT (NEW.machine_id <=> OLD.machine_id)
                   OR NOT (NEW.started_at <=> OLD.started_at)
                   OR NOT (NEW.changed_by <=> OLD.changed_by) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'laundry_order_stage_history: only completed_at may be updated after a row is recorded';
                END IF;

                IF OLD.completed_at IS NOT NULL AND NOT (NEW.completed_at <=> OLD.completed_at) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'laundry_order_stage_history: completed_at cannot be changed once set';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_losh_prevent_tamper');
    }
};
