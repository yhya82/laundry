<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ports the 24 triggers from MASTER_SPECIFICATION.md §10.2 (triggers.sql)
 * verbatim. Run after every table this file references already exists.
 *
 * Execution-order note (carried over from triggers.sql's own header): every
 * table below with more than one same-timing/same-event trigger has its
 * triggers created in the same order as triggers.sql, since MySQL/MariaDB
 * run same-table/same-timing triggers in creation order by default (no
 * FOLLOWS/PRECEDES used here, matching the source file).
 */
return new class extends Migration
{
    public function up(): void
    {
        // BR-014 — clothes added to a package instance must not exceed the
        // package's maximum_clothes.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_loi_check_package_limit_ins
            AFTER INSERT ON laundry_order_items
            FOR EACH ROW
            BEGIN
                DECLARE v_total INT;
                DECLARE v_max INT;

                SELECT COALESCE(SUM(loi.quantity), 0) INTO v_total
                FROM laundry_order_items loi
                WHERE loi.laundry_order_package_id = NEW.laundry_order_package_id;

                SELECT p.maximum_clothes INTO v_max
                FROM laundry_order_packages lop
                JOIN packages p ON p.id = lop.package_id
                WHERE lop.id = NEW.laundry_order_package_id;

                IF v_total > v_max THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'BR-014 violation: total clothes for this package instance exceed the package maximum_clothes limit';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_loi_check_package_limit_upd
            AFTER UPDATE ON laundry_order_items
            FOR EACH ROW
            BEGIN
                DECLARE v_total INT;
                DECLARE v_max INT;

                SELECT COALESCE(SUM(loi.quantity), 0) INTO v_total
                FROM laundry_order_items loi
                WHERE loi.laundry_order_package_id = NEW.laundry_order_package_id;

                SELECT p.maximum_clothes INTO v_max
                FROM laundry_order_packages lop
                JOIN packages p ON p.id = lop.package_id
                WHERE lop.id = NEW.laundry_order_package_id;

                IF v_total > v_max THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'BR-014 violation: total clothes for this package instance exceed the package maximum_clothes limit';
                END IF;
            END
        SQL);

        // BR-038 — total refunds for a payment must never exceed the
        // original payment amount.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_refunds_check_total
            AFTER INSERT ON refunds
            FOR EACH ROW
            BEGIN
                DECLARE v_refunded_total DECIMAL(12,2);
                DECLARE v_payment_amount DECIMAL(12,2);

                SELECT COALESCE(SUM(amount), 0) INTO v_refunded_total
                FROM refunds
                WHERE payment_id = NEW.payment_id;

                SELECT amount INTO v_payment_amount
                FROM payments
                WHERE id = NEW.payment_id;

                IF v_refunded_total > v_payment_amount THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'BR-038 violation: total refunds for this payment exceed the original payment amount';
                END IF;
            END
        SQL);

        // BR-034 — total recorded payments (paid + partial) must never
        // exceed the order's total_amount.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_payments_check_not_exceed_total_ins
            BEFORE INSERT ON payments
            FOR EACH ROW
            BEGIN
                DECLARE v_existing_total DECIMAL(12,2);
                DECLARE v_order_total DECIMAL(12,2);

                IF NEW.payment_status IN ('paid','partial') THEN
                    SELECT COALESCE(SUM(amount), 0) INTO v_existing_total
                    FROM payments
                    WHERE laundry_order_id = NEW.laundry_order_id
                      AND payment_status IN ('paid','partial');

                    SELECT total_amount INTO v_order_total
                    FROM laundry_orders
                    WHERE id = NEW.laundry_order_id;

                    IF (v_existing_total + NEW.amount) > v_order_total THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-034 violation: recorded payments would exceed the laundry order total';
                    END IF;
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_payments_check_not_exceed_total_upd
            BEFORE UPDATE ON payments
            FOR EACH ROW
            BEGIN
                DECLARE v_existing_total DECIMAL(12,2);
                DECLARE v_order_total DECIMAL(12,2);

                IF NEW.payment_status IN ('paid','partial') THEN
                    SELECT COALESCE(SUM(amount), 0) INTO v_existing_total
                    FROM payments
                    WHERE laundry_order_id = NEW.laundry_order_id
                      AND payment_status IN ('paid','partial')
                      AND id <> NEW.id;

                    SELECT total_amount INTO v_order_total
                    FROM laundry_orders
                    WHERE id = NEW.laundry_order_id;

                    IF (v_existing_total + NEW.amount) > v_order_total THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-034 violation: recorded payments would exceed the laundry order total';
                    END IF;
                END IF;
            END
        SQL);

        // BR-045 — damage reports cannot be created against a cancelled order.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_damage_reports_check_order_status
            BEFORE INSERT ON damage_reports
            FOR EACH ROW
            BEGIN
                DECLARE v_order_status VARCHAR(20);

                SELECT status INTO v_order_status
                FROM laundry_orders
                WHERE id = NEW.laundry_order_id;

                IF v_order_status = 'cancelled' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'BR-045 violation: cannot report damage against a cancelled laundry order';
                END IF;
            END
        SQL);

        // BR-046 — a damaged item must belong to the same order as the
        // damage report referencing it.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_damage_items_check_scope
            BEFORE INSERT ON damage_items
            FOR EACH ROW
            BEGIN
                DECLARE v_item_order_id BIGINT UNSIGNED;
                DECLARE v_report_order_id BIGINT UNSIGNED;

                SELECT lop.laundry_order_id INTO v_item_order_id
                FROM laundry_order_items loi
                JOIN laundry_order_packages lop ON lop.id = loi.laundry_order_package_id
                WHERE loi.id = NEW.laundry_order_item_id;

                SELECT laundry_order_id INTO v_report_order_id
                FROM damage_reports
                WHERE id = NEW.damage_report_id;

                IF v_item_order_id <> v_report_order_id THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'BR-046 violation: damaged item does not belong to the laundry order referenced by this damage report';
                END IF;
            END
        SQL);

        // BR-025/BR-026 — stages move forward one step at a time; no
        // skipping, no backward moves; cancellation allowed except from
        // 'completed'; nothing changes once cancelled.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_laundry_orders_stage_sequence
            BEFORE UPDATE ON laundry_orders
            FOR EACH ROW
            BEGIN
                DECLARE v_old_pos INT;
                DECLARE v_new_pos INT;

                IF NEW.status <> OLD.status THEN
                    IF OLD.status = 'cancelled' THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-025 violation: cannot change the status of a cancelled laundry order';
                    ELSEIF NEW.status = 'cancelled' THEN
                        IF OLD.status = 'completed' THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'BR-025 violation: cannot cancel a completed laundry order';
                        END IF;
                    ELSE
                        SET v_old_pos = FIELD(OLD.status,
                            'waiting_queue','received','sorting','washing','drying','ironing',
                            'quality_check','packaging','ready','completed');
                        SET v_new_pos = FIELD(NEW.status,
                            'waiting_queue','received','sorting','washing','drying','ironing',
                            'quality_check','packaging','ready','completed');

                        IF v_new_pos <> v_old_pos + 1 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'BR-025 violation: laundry order stages cannot be skipped or moved backward';
                        END IF;
                    END IF;
                END IF;
            END
        SQL);

        // BR-016 — an order must have at least one package line before
        // leaving 'waiting_queue'; cancelling an empty order is still fine.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_laundry_orders_require_package
            BEFORE UPDATE ON laundry_orders
            FOR EACH ROW
            BEGIN
                DECLARE v_package_count INT;

                IF OLD.status = 'waiting_queue' AND NEW.status NOT IN ('waiting_queue','cancelled') THEN
                    SELECT COUNT(*) INTO v_package_count
                    FROM laundry_order_packages
                    WHERE laundry_order_id = NEW.id;

                    IF v_package_count = 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-016 violation: a laundry order must contain at least one package before processing can start';
                    END IF;
                END IF;
            END
        SQL);

        // BR-012 — completed/missed collections can't be rescheduled or reopened.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_collections_lock_completed
            BEFORE UPDATE ON collections
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('completed','missed')
                   AND (NEW.scheduled_date <> OLD.scheduled_date OR NEW.status NOT IN ('completed','missed')) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'BR-012 violation: completed or missed collections cannot be rescheduled or have their status reopened';
                END IF;
            END
        SQL);

        // Keeps customers.store_credit_balance in lockstep with the ledger
        // automatically — trusts the ledger row's own balance_after, which
        // trg_sct_verify_balance (below) has already validated.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_store_credit_sync_balance
            AFTER INSERT ON store_credit_transactions
            FOR EACH ROW
            BEGIN
                UPDATE customers
                SET store_credit_balance = NEW.balance_after
                WHERE id = NEW.customer_id;
            END
        SQL);

        // customers.phone uniqueness among ACTIVE (deleted_at IS NULL) rows only.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_customers_phone_unique_active_ins
            BEFORE INSERT ON customers
            FOR EACH ROW
            BEGIN
                DECLARE v_count INT;

                IF NEW.deleted_at IS NULL THEN
                    SELECT COUNT(*) INTO v_count
                    FROM customers
                    WHERE phone = NEW.phone AND deleted_at IS NULL;

                    IF v_count > 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'An active customer with this phone number already exists';
                    END IF;
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_customers_phone_unique_active_upd
            BEFORE UPDATE ON customers
            FOR EACH ROW
            BEGIN
                DECLARE v_count INT;

                IF NEW.deleted_at IS NULL THEN
                    SELECT COUNT(*) INTO v_count
                    FROM customers
                    WHERE phone = NEW.phone AND deleted_at IS NULL AND id <> NEW.id;

                    IF v_count > 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'An active customer with this phone number already exists';
                    END IF;
                END IF;
            END
        SQL);

        // users.email uniqueness among ACTIVE (deleted_at IS NULL) rows only.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_users_email_unique_active_ins
            BEFORE INSERT ON users
            FOR EACH ROW
            BEGIN
                DECLARE v_count INT;

                IF NEW.deleted_at IS NULL THEN
                    SELECT COUNT(*) INTO v_count
                    FROM users
                    WHERE email = NEW.email AND deleted_at IS NULL;

                    IF v_count > 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'An active user with this email already exists';
                    END IF;
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_users_email_unique_active_upd
            BEFORE UPDATE ON users
            FOR EACH ROW
            BEGIN
                DECLARE v_count INT;

                IF NEW.deleted_at IS NULL THEN
                    SELECT COUNT(*) INTO v_count
                    FROM users
                    WHERE email = NEW.email AND deleted_at IS NULL AND id <> NEW.id;

                    IF v_count > 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'An active user with this email already exists';
                    END IF;
                END IF;
            END
        SQL);

        // store_credit_transactions balance integrity: verifies balance_after
        // is actually correct, and the SELECT ... FOR UPDATE row lock
        // serializes concurrent writes for the same customer at the DB level.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_sct_verify_balance
            BEFORE INSERT ON store_credit_transactions
            FOR EACH ROW
            BEGIN
                DECLARE v_current_balance DECIMAL(12,2);
                DECLARE v_expected_balance DECIMAL(12,2);

                SELECT store_credit_balance INTO v_current_balance
                FROM customers
                WHERE id = NEW.customer_id
                FOR UPDATE;

                IF NEW.direction = 'credit' THEN
                    SET v_expected_balance = v_current_balance + NEW.amount;
                ELSE
                    SET v_expected_balance = v_current_balance - NEW.amount;
                END IF;

                IF v_expected_balance < 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Store credit debit would drive the customer balance below zero';
                END IF;

                IF NEW.balance_after <> v_expected_balance THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'balance_after does not match the computed running balance — reload the customer and retry';
                END IF;
            END
        SQL);

        // BR-063 — a settled payment is immutable except via a refund.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_payments_prevent_paid_tamper
            BEFORE UPDATE ON payments
            FOR EACH ROW
            BEGIN
                IF OLD.payment_status = 'paid' THEN
                    IF NOT (NEW.amount <=> OLD.amount) OR NOT (NEW.payment_method <=> OLD.payment_method) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-063 violation: cannot modify the amount or method of a paid payment — record a refund instead';
                    END IF;
                END IF;
            END
        SQL);

        // BR-063 — a resolved damage report's compensation/resolution is locked.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_damage_reports_prevent_resolved_tamper
            BEFORE UPDATE ON damage_reports
            FOR EACH ROW
            BEGIN
                IF OLD.status = 'resolved' THEN
                    IF NOT (NEW.cash_compensation_amount <=> OLD.cash_compensation_amount)
                       OR NOT (NEW.store_credit_compensation_amount <=> OLD.store_credit_compensation_amount)
                       OR NOT (NEW.resolution_type <=> OLD.resolution_type) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-063 violation: cannot modify compensation amounts or resolution type of a resolved damage report';
                    END IF;
                END IF;
            END
        SQL);

        // BR-063 — order composition frozen once completed/cancelled.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_lop_prevent_post_completion_tamper
            BEFORE UPDATE ON laundry_order_packages
            FOR EACH ROW
            BEGIN
                DECLARE v_order_status VARCHAR(20);

                SELECT status INTO v_order_status
                FROM laundry_orders
                WHERE id = OLD.laundry_order_id;

                IF v_order_status IN ('completed','cancelled') THEN
                    IF NOT (NEW.quantity <=> OLD.quantity)
                       OR NOT (NEW.package_id <=> OLD.package_id)
                       OR NOT (NEW.unit_price_snapshot <=> OLD.unit_price_snapshot) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-063 violation: cannot modify package composition of a completed or cancelled order';
                    END IF;
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_loi_prevent_post_completion_tamper
            BEFORE UPDATE ON laundry_order_items
            FOR EACH ROW
            BEGIN
                DECLARE v_order_status VARCHAR(20);

                SELECT lo.status INTO v_order_status
                FROM laundry_order_packages lop
                JOIN laundry_orders lo ON lo.id = lop.laundry_order_id
                WHERE lop.id = OLD.laundry_order_package_id;

                IF v_order_status IN ('completed','cancelled') THEN
                    IF NOT (NEW.quantity <=> OLD.quantity)
                       OR NOT (NEW.clothing_type_id <=> OLD.clothing_type_id) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-063 violation: cannot modify clothing-item composition of a completed or cancelled order';
                    END IF;
                END IF;
            END
        SQL);

        // A deliveries row can't be created for an order not marked
        // delivery_type='delivery'.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_deliveries_check_order_type
            BEFORE INSERT ON deliveries
            FOR EACH ROW
            BEGIN
                DECLARE v_order_delivery_type VARCHAR(20);

                SELECT delivery_type INTO v_order_delivery_type
                FROM laundry_orders
                WHERE id = NEW.laundry_order_id;

                IF v_order_delivery_type <> 'delivery' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'A deliveries row cannot be created for an order not marked delivery_type=''delivery''';
                END IF;
            END
        SQL);

        // Populates the denormalized receipts.customer_id from the order at
        // creation time — a one-time, drift-free copy since an order's
        // customer never changes post-creation.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_receipts_set_customer
            BEFORE INSERT ON receipts
            FOR EACH ROW
            BEGIN
                IF NEW.customer_id IS NULL THEN
                    SET NEW.customer_id = (
                        SELECT customer_id FROM laundry_orders WHERE id = NEW.laundry_order_id
                    );
                END IF;
            END
        SQL);

        // A deactivated role cannot be newly assigned to a user.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_user_roles_require_active_role
            BEFORE INSERT ON user_roles
            FOR EACH ROW
            BEGIN
                DECLARE v_role_status VARCHAR(20);

                SELECT status INTO v_role_status FROM roles WHERE id = NEW.role_id;

                IF v_role_status <> 'active' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Cannot assign a deactivated role to a user';
                END IF;
            END
        SQL);

        // Keeps laundry_orders.subscription_id in sync when a collection
        // converts into an order — auto-fills if unset, rejects a genuine
        // mismatch rather than silently overwriting.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_collections_sync_order_subscription
            BEFORE UPDATE ON collections
            FOR EACH ROW
            BEGIN
                DECLARE v_order_subscription_id BIGINT UNSIGNED;

                IF NEW.laundry_order_id IS NOT NULL
                   AND NOT (NEW.laundry_order_id <=> OLD.laundry_order_id) THEN

                    SELECT subscription_id INTO v_order_subscription_id
                    FROM laundry_orders
                    WHERE id = NEW.laundry_order_id;

                    IF v_order_subscription_id IS NULL THEN
                        UPDATE laundry_orders
                        SET subscription_id = NEW.subscription_id
                        WHERE id = NEW.laundry_order_id;
                    ELSEIF NOT (v_order_subscription_id <=> NEW.subscription_id) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'laundry_orders.subscription_id conflicts with the converting collection''s subscription_id';
                    END IF;
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        $triggers = [
            'trg_loi_check_package_limit_ins',
            'trg_loi_check_package_limit_upd',
            'trg_refunds_check_total',
            'trg_payments_check_not_exceed_total_ins',
            'trg_payments_check_not_exceed_total_upd',
            'trg_damage_reports_check_order_status',
            'trg_damage_items_check_scope',
            'trg_laundry_orders_stage_sequence',
            'trg_laundry_orders_require_package',
            'trg_collections_lock_completed',
            'trg_store_credit_sync_balance',
            'trg_customers_phone_unique_active_ins',
            'trg_customers_phone_unique_active_upd',
            'trg_users_email_unique_active_ins',
            'trg_users_email_unique_active_upd',
            'trg_sct_verify_balance',
            'trg_payments_prevent_paid_tamper',
            'trg_damage_reports_prevent_resolved_tamper',
            'trg_lop_prevent_post_completion_tamper',
            'trg_loi_prevent_post_completion_tamper',
            'trg_deliveries_check_order_type',
            'trg_receipts_set_customer',
            'trg_user_roles_require_active_role',
            'trg_collections_sync_order_subscription',
        ];

        foreach ($triggers as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
