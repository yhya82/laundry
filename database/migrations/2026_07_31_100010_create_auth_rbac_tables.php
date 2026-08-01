<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique('name', 'uq_departments_name');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique('name', 'uq_roles_name');
            $table->unique('slug', 'uq_roles_slug');
        });
        DB::statement("ALTER TABLE `roles` ADD CONSTRAINT `chk_roles_status` CHECK (`status` IN ('active','inactive'))");

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150);
            $table->string('permission_group', 100);
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique('slug', 'uq_permissions_slug');
        });
        DB::statement("ALTER TABLE `permissions` ADD CONSTRAINT `chk_permissions_status` CHECK (`status` IN ('active','inactive'))");

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id', 'fk_role_permissions_role')
                ->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('permission_id', 'fk_role_permissions_permission')
                ->references('id')->on('permissions')->restrictOnDelete();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->string('password_hash');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id', 'fk_users_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `users` ADD CONSTRAINT `chk_users_status` CHECK (`status` IN ('active','inactive','suspended'))");
        Schema::table('users', function (Blueprint $table) {
            $table->index('status', 'idx_users_status');
            $table->index(['email', 'deleted_at'], 'idx_users_email_active');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'role_id'], 'uq_user_roles');
            $table->foreign('user_id', 'fk_user_roles_user')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id', 'fk_user_roles_role')
                ->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('assigned_by', 'fk_user_roles_assigned_by')
                ->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('user_roles', function (Blueprint $table) {
            $table->index('role_id', 'idx_user_roles_role');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 150)->primary();
            $table->string('token');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('departments');
    }
};
