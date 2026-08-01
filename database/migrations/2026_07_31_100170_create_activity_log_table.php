<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name', 50)->nullable();
            $table->string('description');
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type', 100)->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('event', 50)->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // Deliberately no updated_at/deleted_at: insert-only table.
            // No application-level UPDATE/DELETE grant should exist for this
            // table in production (BR-060) — see MASTER_SPECIFICATION.md §5.2.
        });
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id'], 'idx_activity_log_subject');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'idx_activity_log_subject_date');
            $table->index(['causer_type', 'causer_id'], 'idx_activity_log_causer');
            $table->index('created_at', 'idx_activity_log_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
