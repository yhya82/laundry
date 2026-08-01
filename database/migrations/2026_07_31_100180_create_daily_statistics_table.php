<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_statistics', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('metric_key', 100);
            $table->decimal('metric_value', 18, 2)->default(0);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();

            $table->unique(['stat_date', 'metric_key', 'branch_id'], 'uq_daily_statistics');
            $table->foreign('branch_id', 'fk_daily_statistics_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        Schema::table('daily_statistics', function (Blueprint $table) {
            $table->index(['stat_date', 'metric_key'], 'idx_daily_statistics_date_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_statistics');
    }
};
