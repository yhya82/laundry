<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique('name', 'uq_damage_types_name');
            $table->foreign('branch_id', 'fk_damage_types_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `damage_types` ADD CONSTRAINT `chk_damage_types_status` CHECK (`status` IN ('active','inactive'))");
        Schema::table('damage_types', function (Blueprint $table) {
            $table->index('branch_id', 'idx_damage_types_branch');
        });

        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('laundry_order_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('status', 20)->default('reported');
            $table->text('description');
            $table->string('resolution_type', 30)->nullable();
            $table->decimal('cash_compensation_amount', 10, 2)->unsigned()->default(0);
            $table->decimal('store_credit_compensation_amount', 10, 2)->unsigned()->default(0);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('laundry_order_id', 'fk_damage_reports_order')
                ->references('id')->on('laundry_orders')->restrictOnDelete();
            $table->foreign('customer_id', 'fk_damage_reports_customer')
                ->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('created_by', 'fk_damage_reports_created_by')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by', 'fk_damage_reports_approved_by')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `damage_reports` ADD CONSTRAINT `chk_damage_reports_status` CHECK (`status` IN ('reported','under_review','approved','rejected','resolved'))");
        DB::statement("ALTER TABLE `damage_reports` ADD CONSTRAINT `chk_damage_reports_resolution` CHECK (`resolution_type` IS NULL OR `resolution_type` IN ('repair','refund','rewash','store_credit','replacement','other'))");
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->index('laundry_order_id', 'idx_damage_reports_order');
            $table->index('customer_id', 'idx_damage_reports_customer');
            $table->index('status', 'idx_damage_reports_status');
        });

        Schema::create('damage_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('damage_report_id');
            $table->unsignedBigInteger('laundry_order_item_id');
            $table->unsignedBigInteger('damage_type_id');
            $table->string('severity', 20);
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('damage_report_id', 'fk_damage_items_report')
                ->references('id')->on('damage_reports')->cascadeOnDelete();
            $table->foreign('laundry_order_item_id', 'fk_damage_items_order_item')
                ->references('id')->on('laundry_order_items')->restrictOnDelete();
            $table->foreign('damage_type_id', 'fk_damage_items_type')
                ->references('id')->on('damage_types')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE `damage_items` ADD CONSTRAINT `chk_damage_items_severity` CHECK (`severity` IN ('low','medium','high','critical'))");
        Schema::table('damage_items', function (Blueprint $table) {
            $table->index('damage_report_id', 'idx_damage_items_report');
        });

        Schema::create('damage_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('damage_report_id');
            $table->string('file_path');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('damage_report_id', 'fk_damage_evidence_report')
                ->references('id')->on('damage_reports')->cascadeOnDelete();
            $table->foreign('uploaded_by', 'fk_damage_evidence_user')
                ->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('damage_evidence', function (Blueprint $table) {
            $table->index('damage_report_id', 'idx_damage_evidence_report');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_evidence');
        Schema::dropIfExists('damage_items');
        Schema::dropIfExists('damage_reports');
        Schema::dropIfExists('damage_types');
    }
};
