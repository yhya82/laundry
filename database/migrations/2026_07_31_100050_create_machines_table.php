<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 100);
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->string('status', 20)->default('available');
            $table->timestamps();

            $table->foreign('branch_id', 'fk_machines_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `machines` ADD CONSTRAINT `chk_machines_status` CHECK (`status` IN ('available','running','maintenance','inactive'))");
        Schema::table('machines', function (Blueprint $table) {
            $table->index('status', 'idx_machines_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
