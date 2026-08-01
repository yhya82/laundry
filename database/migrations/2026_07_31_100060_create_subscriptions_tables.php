<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('status', 20)->default('active');
            $table->date('start_date');
            $table->string('frequency_type', 20);
            $table->json('custom_frequency_config')->nullable();
            $table->date('next_collection_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_id', 'fk_subscriptions_customer')
                ->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('created_by', 'fk_subscriptions_created_by')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `subscriptions` ADD CONSTRAINT `chk_subscriptions_status` CHECK (`status` IN ('active','paused','suspended','expired','cancelled'))");
        DB::statement("ALTER TABLE `subscriptions` ADD CONSTRAINT `chk_subscriptions_frequency` CHECK (`frequency_type` IN ('monthly_1','monthly_2','monthly_3','monthly_4','custom'))");
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('customer_id', 'idx_subscriptions_customer');
            $table->index('status', 'idx_subscriptions_status');
        });

        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('package_id');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['subscription_id', 'package_id'], 'uq_subscription_packages');
            $table->foreign('subscription_id', 'fk_subscription_packages_subscription')
                ->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('package_id', 'fk_subscription_packages_package')
                ->references('id')->on('packages')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE `subscription_packages` ADD CONSTRAINT `chk_subscription_packages_qty` CHECK (`quantity` > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
        Schema::dropIfExists('subscriptions');
    }
};
