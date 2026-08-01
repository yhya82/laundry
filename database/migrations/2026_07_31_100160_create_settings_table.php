<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_group', 50);
            $table->string('setting_key', 100);
            $table->text('setting_value')->nullable();
            $table->string('value_type', 20)->default('string');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['setting_group', 'setting_key'], 'uq_settings_group_key');
            $table->foreign('updated_by', 'fk_settings_updated_by')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `settings` ADD CONSTRAINT `chk_settings_value_type` CHECK (`value_type` IN ('string','integer','boolean','json'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
