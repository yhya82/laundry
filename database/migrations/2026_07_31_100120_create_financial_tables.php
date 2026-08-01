<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 30);
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('laundry_order_id');
            $table->decimal('amount', 12, 2)->unsigned();
            $table->string('payment_method', 20);
            $table->string('payment_status', 20)->default('pending');
            $table->string('reference', 150)->nullable();
            $table->unsignedBigInteger('paid_by');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique('payment_number', 'uq_payments_number');
            $table->foreign('customer_id', 'fk_payments_customer')
                ->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('laundry_order_id', 'fk_payments_order')
                ->references('id')->on('laundry_orders')->restrictOnDelete();
            $table->foreign('paid_by', 'fk_payments_paid_by')
                ->references('id')->on('users')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE `payments` ADD CONSTRAINT `chk_payments_amount` CHECK (`amount` > 0)');
        DB::statement("ALTER TABLE `payments` ADD CONSTRAINT `chk_payments_method` CHECK (`payment_method` IN ('cash','card','mobile_money','bank_transfer','credit','store_credit'))");
        DB::statement("ALTER TABLE `payments` ADD CONSTRAINT `chk_payments_status` CHECK (`payment_status` IN ('pending','partial','paid','failed','refunded','cancelled'))");
        Schema::table('payments', function (Blueprint $table) {
            $table->index('customer_id', 'idx_payments_customer');
            $table->index('laundry_order_id', 'idx_payments_order');
            $table->index('payment_status', 'idx_payments_status');
            $table->index('created_at', 'idx_payments_created_at');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 30);
            $table->unsignedBigInteger('payment_id');
            $table->decimal('amount', 12, 2)->unsigned();
            $table->string('reason');
            $table->unsignedBigInteger('processed_by');
            $table->timestamps();

            $table->unique('refund_number', 'uq_refunds_number');
            $table->foreign('payment_id', 'fk_refunds_payment')
                ->references('id')->on('payments')->restrictOnDelete();
            $table->foreign('processed_by', 'fk_refunds_processed_by')
                ->references('id')->on('users')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE `refunds` ADD CONSTRAINT `chk_refunds_amount` CHECK (`amount` > 0)');
        Schema::table('refunds', function (Blueprint $table) {
            $table->index('payment_id', 'idx_refunds_payment');
        });

        Schema::create('store_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('direction', 10);
            $table->decimal('amount', 12, 2)->unsigned();
            $table->decimal('balance_after', 12, 2)->unsigned();
            $table->string('source_type', 30);
            $table->string('source_table', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('customer_id', 'fk_sct_customer')
                ->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('created_by', 'fk_sct_created_by')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `store_credit_transactions` ADD CONSTRAINT `chk_sct_direction` CHECK (`direction` IN ('credit','debit'))");
        DB::statement('ALTER TABLE `store_credit_transactions` ADD CONSTRAINT `chk_sct_amount` CHECK (`amount` > 0)');
        DB::statement('ALTER TABLE `store_credit_transactions` ADD CONSTRAINT `chk_sct_balance_after` CHECK (`balance_after` >= 0)');
        Schema::table('store_credit_transactions', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'idx_sct_customer_date');
            $table->index(['source_table', 'source_id'], 'idx_sct_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_credit_transactions');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
    }
};
