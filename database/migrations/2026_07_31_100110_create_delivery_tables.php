<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('laundry_order_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('delivery_type', 20);
            $table->unsignedBigInteger('address_id')->nullable();
            $table->string('address_snapshot')->nullable();
            $table->decimal('delivery_fee', 10, 2)->unsigned()->default(0);
            $table->unsignedBigInteger('assigned_staff_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('delivery_instructions')->nullable();
            $table->timestamps();

            $table->unique('laundry_order_id', 'uq_deliveries_order');
            $table->foreign('laundry_order_id', 'fk_deliveries_order')
                ->references('id')->on('laundry_orders')->restrictOnDelete();
            $table->foreign('customer_id', 'fk_deliveries_customer')
                ->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('address_id', 'fk_deliveries_address')
                ->references('id')->on('customer_addresses')->nullOnDelete();
            $table->foreign('assigned_staff_id', 'fk_deliveries_staff')
                ->references('id')->on('employees')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `deliveries` ADD CONSTRAINT `chk_deliveries_type` CHECK (`delivery_type` IN ('pickup','delivery'))");
        DB::statement("ALTER TABLE `deliveries` ADD CONSTRAINT `chk_deliveries_status` CHECK (`status` IN ('pending','scheduled','assigned','picked_up','out_for_delivery','delivered','failed','cancelled'))");
        Schema::table('deliveries', function (Blueprint $table) {
            $table->index('customer_id', 'idx_deliveries_customer');
            $table->index('assigned_staff_id', 'idx_deliveries_staff');
            $table->index('status', 'idx_deliveries_status');
        });

        Schema::create('delivery_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('status', 20);
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('delivery_id', 'fk_dsh_delivery')
                ->references('id')->on('deliveries')->cascadeOnDelete();
            $table->foreign('changed_by', 'fk_dsh_user')
                ->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('delivery_status_history', function (Blueprint $table) {
            $table->index(['delivery_id', 'created_at'], 'idx_dsh_delivery');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_status_history');
        Schema::dropIfExists('deliveries');
    }
};
