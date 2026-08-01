<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clothing_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('image_path')->nullable();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique('name', 'uq_clothing_types_name');
            $table->foreign('branch_id', 'fk_clothing_types_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `clothing_types` ADD CONSTRAINT `chk_clothing_types_status` CHECK (`status` IN ('active','inactive'))");
        Schema::table('clothing_types', function (Blueprint $table) {
            $table->index('branch_id', 'idx_clothing_types_branch');
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2)->unsigned()->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique('name', 'uq_services_name');
            $table->foreign('branch_id', 'fk_services_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `services` ADD CONSTRAINT `chk_services_status` CHECK (`status` IN ('active','inactive'))");
        Schema::table('services', function (Blueprint $table) {
            $table->index('branch_id', 'idx_services_branch');
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2)->unsigned();
            $table->unsignedSmallInteger('maximum_clothes');
            $table->string('package_type', 50)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('active');
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id', 'fk_packages_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        DB::statement('ALTER TABLE `packages` ADD CONSTRAINT `chk_packages_price` CHECK (`price` >= 0)');
        DB::statement('ALTER TABLE `packages` ADD CONSTRAINT `chk_packages_max_clothes` CHECK (`maximum_clothes` > 0)');
        DB::statement("ALTER TABLE `packages` ADD CONSTRAINT `chk_packages_priority` CHECK (`priority` IN ('normal','express'))");
        DB::statement("ALTER TABLE `packages` ADD CONSTRAINT `chk_packages_status` CHECK (`status` IN ('active','inactive'))");
        Schema::table('packages', function (Blueprint $table) {
            $table->index('branch_id', 'idx_packages_branch');
            $table->index('status', 'idx_packages_status');
        });

        Schema::create('package_services', function (Blueprint $table) {
            $table->unsignedBigInteger('package_id');
            $table->unsignedBigInteger('service_id');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['package_id', 'service_id']);

            $table->foreign('package_id', 'fk_package_services_package')
                ->references('id')->on('packages')->cascadeOnDelete();
            $table->foreign('service_id', 'fk_package_services_service')
                ->references('id')->on('services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_services');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('services');
        Schema::dropIfExists('clothing_types');
    }
};
