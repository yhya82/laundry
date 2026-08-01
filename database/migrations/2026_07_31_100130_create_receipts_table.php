<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30);
            $table->unsignedBigInteger('laundry_order_id');
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('status', 20)->default('generated');
            $table->decimal('subtotal_snapshot', 12, 2)->unsigned();
            $table->decimal('discount_snapshot', 12, 2)->unsigned()->default(0);
            $table->decimal('delivery_fee_snapshot', 12, 2)->unsigned()->default(0);
            $table->decimal('store_credit_used_snapshot', 12, 2)->unsigned()->default(0);
            $table->decimal('total_snapshot', 12, 2)->unsigned();
            $table->string('business_name_snapshot', 150);
            $table->string('business_logo_snapshot')->nullable();
            $table->unsignedBigInteger('generated_by');
            $table->dateTime('generated_at')->useCurrent();
            $table->string('cancelled_reason')->nullable();
            $table->timestamps();

            $table->unique('receipt_number', 'uq_receipts_number');
            $table->unique('payment_id', 'uq_receipts_payment');
            $table->foreign('laundry_order_id', 'fk_receipts_order')
                ->references('id')->on('laundry_orders')->restrictOnDelete();
            $table->foreign('payment_id', 'fk_receipts_payment')
                ->references('id')->on('payments')->restrictOnDelete();
            $table->foreign('customer_id', 'fk_receipts_customer')
                ->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('generated_by', 'fk_receipts_generated_by')
                ->references('id')->on('users')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE `receipts` ADD CONSTRAINT `chk_receipts_status` CHECK (`status` IN ('generated','printed','sent','cancelled'))");
        Schema::table('receipts', function (Blueprint $table) {
            $table->index('laundry_order_id', 'idx_receipts_order');
            $table->index('customer_id', 'idx_receipts_customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
