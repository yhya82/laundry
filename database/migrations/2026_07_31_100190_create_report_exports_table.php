<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 50);
            $table->string('export_format', 10);
            $table->date('date_range_start')->nullable();
            $table->date('date_range_end')->nullable();
            $table->json('filters')->nullable();
            $table->unsignedBigInteger('generated_by');
            $table->dateTime('generated_at')->useCurrent();

            $table->foreign('generated_by', 'fk_report_exports_user')
                ->references('id')->on('users')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE `report_exports` ADD CONSTRAINT `chk_report_exports_format` CHECK (`export_format` IN ('pdf','excel','csv'))");
        Schema::table('report_exports', function (Blueprint $table) {
            $table->index(['report_type', 'generated_at'], 'idx_report_exports_type_date');
            $table->index('generated_by', 'idx_report_exports_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
