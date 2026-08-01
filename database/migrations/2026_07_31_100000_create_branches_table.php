<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE `branches` ADD CONSTRAINT `chk_branches_status` CHECK (`status` IN ('active','inactive'))");

        DB::table('branches')->insert([
            'name' => 'Main Branch',
            'is_default' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
