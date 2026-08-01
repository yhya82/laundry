<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique('name', 'uq_expense_categories_name');
            $table->foreign('branch_id', 'fk_expense_categories_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->index('branch_id', 'idx_expense_categories_branch');
        });

        Schema::create('expense_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->unsignedBigInteger('category_id');
            $table->decimal('amount', 12, 2)->unsigned();
            $table->string('frequency', 20);
            $table->date('next_run_date');
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('category_id', 'fk_expense_schedules_category')
                ->references('id')->on('expense_categories')->restrictOnDelete();
            $table->foreign('created_by', 'fk_expense_schedules_created_by')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `expense_schedules` ADD CONSTRAINT `chk_expense_schedules_frequency` CHECK (`frequency` IN ('weekly','monthly','quarterly','yearly'))");
        DB::statement("ALTER TABLE `expense_schedules` ADD CONSTRAINT `chk_expense_schedules_status` CHECK (`status` IN ('active','paused','cancelled'))");

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_schedule_id')->nullable();
            $table->string('title', 150);
            $table->unsignedBigInteger('category_id');
            $table->decimal('amount', 12, 2)->unsigned();
            $table->string('payment_method', 20);
            $table->string('description')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->date('expense_date');
            $table->timestamps();

            $table->foreign('expense_schedule_id', 'fk_expenses_schedule')
                ->references('id')->on('expense_schedules')->nullOnDelete();
            $table->foreign('category_id', 'fk_expenses_category')
                ->references('id')->on('expense_categories')->restrictOnDelete();
            $table->foreign('created_by', 'fk_expenses_created_by')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by', 'fk_expenses_approved_by')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement('ALTER TABLE `expenses` ADD CONSTRAINT `chk_expenses_amount` CHECK (`amount` > 0)');
        DB::statement("ALTER TABLE `expenses` ADD CONSTRAINT `chk_expenses_method` CHECK (`payment_method` IN ('cash','bank_transfer','mobile_money','card'))");
        DB::statement("ALTER TABLE `expenses` ADD CONSTRAINT `chk_expenses_status` CHECK (`status` IN ('pending','approved','rejected','paid','cancelled'))");
        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['expense_date', 'category_id'], 'idx_expenses_date_category');
            $table->index('category_id', 'idx_expenses_category');
            $table->index('status', 'idx_expenses_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_schedules');
        Schema::dropIfExists('expense_categories');
    }
};
