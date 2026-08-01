<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('position', 100)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('joined_date')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('user_id', 'uq_employees_user');
            $table->foreign('user_id', 'fk_employees_user')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('department_id', 'fk_employees_department')
                ->references('id')->on('departments')->nullOnDelete();
            $table->foreign('branch_id', 'fk_employees_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `employees` ADD CONSTRAINT `chk_employees_status` CHECK (`status` IN ('active','inactive','suspended','terminated'))");
        Schema::table('employees', function (Blueprint $table) {
            $table->index('department_id', 'idx_employees_department');
            $table->index('status', 'idx_employees_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
