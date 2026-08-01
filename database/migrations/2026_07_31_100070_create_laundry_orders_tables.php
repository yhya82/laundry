<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundry_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30);
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('status', 20)->default('waiting_queue');
            $table->string('priority', 20)->default('normal');
            $table->string('delivery_type', 20)->default('pickup');
            $table->text('instructions')->default('No special instructions');
            $table->text('internal_notes')->nullable();
            $table->decimal('subtotal_amount', 12, 2)->unsigned()->default(0);
            $table->decimal('discount_amount', 12, 2)->unsigned()->default(0);
            $table->decimal('delivery_fee_amount', 12, 2)->unsigned()->default(0);
            $table->decimal('total_amount', 12, 2)->unsigned()->default(0);
            $table->decimal('store_credit_applied_amount', 12, 2)->unsigned()->default(0);
            $table->unsignedBigInteger('assigned_employee_id')->nullable();
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->timestamps();

            $table->unique('order_number', 'uq_laundry_orders_number');
            $table->foreign('customer_id', 'fk_laundry_orders_customer')
                ->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('subscription_id', 'fk_laundry_orders_subscription')
                ->references('id')->on('subscriptions')->nullOnDelete();
            $table->foreign('branch_id', 'fk_laundry_orders_branch')
                ->references('id')->on('branches')->nullOnDelete();
            $table->foreign('assigned_employee_id', 'fk_laundry_orders_employee')
                ->references('id')->on('employees')->nullOnDelete();
            $table->foreign('machine_id', 'fk_laundry_orders_machine')
                ->references('id')->on('machines')->nullOnDelete();
            $table->foreign('created_by', 'fk_laundry_orders_created_by')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `laundry_orders` ADD CONSTRAINT `chk_laundry_orders_status` CHECK (`status` IN ('waiting_queue','received','sorting','washing','drying','ironing','quality_check','packaging','ready','completed','cancelled'))");
        DB::statement("ALTER TABLE `laundry_orders` ADD CONSTRAINT `chk_laundry_orders_priority` CHECK (`priority` IN ('normal','express'))");
        DB::statement("ALTER TABLE `laundry_orders` ADD CONSTRAINT `chk_laundry_orders_delivery_type` CHECK (`delivery_type` IN ('pickup','delivery'))");
        DB::statement('ALTER TABLE `laundry_orders` ADD CONSTRAINT `chk_laundry_orders_totals` CHECK (`total_amount` = `subtotal_amount` - `discount_amount` + `delivery_fee_amount`)');
        DB::statement('ALTER TABLE `laundry_orders` ADD CONSTRAINT `chk_lo_credit_ceiling` CHECK (`store_credit_applied_amount` <= `total_amount`)');
        Schema::table('laundry_orders', function (Blueprint $table) {
            $table->index('customer_id', 'idx_laundry_orders_customer');
            $table->index('subscription_id', 'idx_laundry_orders_subscription');
            $table->index(['status', 'priority', 'created_at'], 'idx_laundry_orders_queue_sort');
            $table->index('assigned_employee_id', 'idx_laundry_orders_employee');
            $table->index('machine_id', 'idx_laundry_orders_machine');
            $table->index('created_at', 'idx_laundry_orders_created_at');
        });

        Schema::create('laundry_order_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('laundry_order_id');
            $table->unsignedBigInteger('package_id');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price_snapshot', 10, 2)->unsigned();
            $table->decimal('line_total', 12, 2)->unsigned();
            $table->timestamps();

            $table->foreign('laundry_order_id', 'fk_lop_order')
                ->references('id')->on('laundry_orders')->cascadeOnDelete();
            $table->foreign('package_id', 'fk_lop_package')
                ->references('id')->on('packages')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE `laundry_order_packages` ADD CONSTRAINT `chk_lop_qty` CHECK (`quantity` > 0)');
        DB::statement('ALTER TABLE `laundry_order_packages` ADD CONSTRAINT `chk_lop_line_total` CHECK (`line_total` = `unit_price_snapshot` * `quantity`)');
        Schema::table('laundry_order_packages', function (Blueprint $table) {
            $table->index('laundry_order_id', 'idx_lop_order');
            $table->index('package_id', 'idx_lop_package');
        });

        Schema::create('laundry_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('laundry_order_package_id');
            $table->unsignedBigInteger('clothing_type_id');
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->foreign('laundry_order_package_id', 'fk_loi_order_package')
                ->references('id')->on('laundry_order_packages')->cascadeOnDelete();
            $table->foreign('clothing_type_id', 'fk_loi_clothing_type')
                ->references('id')->on('clothing_types')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE `laundry_order_items` ADD CONSTRAINT `chk_loi_qty` CHECK (`quantity` > 0)');
        Schema::table('laundry_order_items', function (Blueprint $table) {
            $table->index('laundry_order_package_id', 'idx_loi_order_package');
            $table->index('clothing_type_id', 'idx_loi_clothing_type');
        });

        Schema::create('laundry_order_stage_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('laundry_order_id');
            $table->string('stage', 20);
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('laundry_order_id', 'fk_losh_order')
                ->references('id')->on('laundry_orders')->cascadeOnDelete();
            $table->foreign('machine_id', 'fk_losh_machine')
                ->references('id')->on('machines')->nullOnDelete();
            $table->foreign('changed_by', 'fk_losh_user')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `laundry_order_stage_history` ADD CONSTRAINT `chk_losh_stage` CHECK (`stage` IN ('waiting_queue','received','sorting','washing','drying','ironing','quality_check','packaging','ready','completed'))");
        Schema::table('laundry_order_stage_history', function (Blueprint $table) {
            $table->index(['laundry_order_id', 'started_at'], 'idx_losh_order');
            $table->index(['stage', 'started_at'], 'idx_losh_stage_date');
            $table->index('machine_id', 'idx_losh_machine');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry_order_stage_history');
        Schema::dropIfExists('laundry_order_items');
        Schema::dropIfExists('laundry_order_packages');
        Schema::dropIfExists('laundry_orders');
    }
};
