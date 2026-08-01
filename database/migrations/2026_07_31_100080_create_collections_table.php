<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('laundry_order_id')->nullable();
            $table->date('scheduled_date');
            $table->date('collection_date')->nullable();
            $table->unsignedSmallInteger('package_quantity')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique('laundry_order_id', 'uq_collections_order');
            $table->foreign('customer_id', 'fk_collections_customer')
                ->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('subscription_id', 'fk_collections_subscription')
                ->references('id')->on('subscriptions')->nullOnDelete();
            $table->foreign('laundry_order_id', 'fk_collections_order')
                ->references('id')->on('laundry_orders')->restrictOnDelete();
            $table->foreign('created_by', 'fk_collections_created_by')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `collections` ADD CONSTRAINT `chk_collections_status` CHECK (`status` IN ('scheduled','upcoming','collected','laundry_created','processing','completed','missed','rescheduled','cancelled'))");
        Schema::table('collections', function (Blueprint $table) {
            $table->index(['customer_id', 'scheduled_date'], 'idx_collections_customer_date');
            $table->index('subscription_id', 'idx_collections_subscription');
            $table->index('status', 'idx_collections_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
