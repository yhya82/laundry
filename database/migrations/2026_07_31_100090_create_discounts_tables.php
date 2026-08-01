<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('discount_type', 20);
            $table->decimal('value', 10, 2)->unsigned();
            $table->boolean('requires_approval')->default(false);
            $table->decimal('max_cashier_value', 10, 2)->unsigned()->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique('name', 'uq_discount_templates_name');
            $table->foreign('branch_id', 'fk_discount_templates_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `discount_templates` ADD CONSTRAINT `chk_discount_templates_type` CHECK (`discount_type` IN ('percentage','fixed'))");
        DB::statement("ALTER TABLE `discount_templates` ADD CONSTRAINT `chk_discount_templates_status` CHECK (`status` IN ('active','inactive'))");
        Schema::table('discount_templates', function (Blueprint $table) {
            $table->index('branch_id', 'idx_discount_templates_branch');
        });

        Schema::create('order_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('laundry_order_id');
            $table->unsignedBigInteger('discount_template_id')->nullable();
            $table->string('discount_type', 20);
            $table->decimal('value', 10, 2)->unsigned();
            $table->decimal('amount', 12, 2)->unsigned();
            $table->string('reason');
            $table->string('status', 20)->default('applied');
            $table->unsignedBigInteger('applied_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('laundry_order_id', 'fk_order_discounts_order')
                ->references('id')->on('laundry_orders')->cascadeOnDelete();
            $table->foreign('discount_template_id', 'fk_order_discounts_template')
                ->references('id')->on('discount_templates')->nullOnDelete();
            $table->foreign('applied_by', 'fk_order_discounts_applied_by')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by', 'fk_order_discounts_approved_by')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `order_discounts` ADD CONSTRAINT `chk_order_discounts_type` CHECK (`discount_type` IN ('percentage','fixed'))");
        DB::statement("ALTER TABLE `order_discounts` ADD CONSTRAINT `chk_order_discounts_status` CHECK (`status` IN ('applied','pending_approval','approved','rejected'))");
        Schema::table('order_discounts', function (Blueprint $table) {
            $table->index('laundry_order_id', 'idx_order_discounts_order');
            $table->index('status', 'idx_order_discounts_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_discounts');
        Schema::dropIfExists('discount_templates');
    }
};
