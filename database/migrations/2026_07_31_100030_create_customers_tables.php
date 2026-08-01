<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->string('customer_type', 20)->default('walk_in');
            $table->string('status', 20)->default('active');
            $table->string('profile_image_path')->nullable();
            $table->decimal('outstanding_balance', 12, 2)->unsigned()->default(0);
            $table->decimal('store_credit_balance', 12, 2)->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id', 'fk_customers_branch')
                ->references('id')->on('branches')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `customers` ADD CONSTRAINT `chk_customers_type` CHECK (`customer_type` IN ('walk_in','subscription'))");
        DB::statement("ALTER TABLE `customers` ADD CONSTRAINT `chk_customers_status` CHECK (`status` IN ('active','inactive','suspended'))");
        Schema::table('customers', function (Blueprint $table) {
            $table->index('name', 'idx_customers_name');
            $table->fullText('name', 'ftx_customers_name');
            $table->index(['phone', 'deleted_at'], 'idx_customers_phone_active');
            $table->index(['customer_type', 'status'], 'idx_customers_type_status');
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('label', 50)->nullable();
            $table->string('street')->nullable();
            $table->string('area', 150)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('customer_id', 'fk_customer_addresses_customer')
                ->references('id')->on('customers')->cascadeOnDelete();
        });
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->index('customer_id', 'idx_customer_addresses_customer');
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('note_type', 20);
            $table->text('content');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_id', 'fk_customer_notes_customer')
                ->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('created_by', 'fk_customer_notes_user')
                ->references('id')->on('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE `customer_notes` ADD CONSTRAINT `chk_customer_notes_type` CHECK (`note_type` IN ('instruction','internal'))");
        Schema::table('customer_notes', function (Blueprint $table) {
            $table->index(['customer_id', 'note_type'], 'idx_customer_notes_customer_type');
        });

        Schema::create('customer_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('event_type', 50);
            $table->string('reference_table', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('title', 150);
            $table->string('description')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('customer_id', 'fk_customer_timeline_customer')
                ->references('id')->on('customers')->cascadeOnDelete();
        });
        Schema::table('customer_timeline_events', function (Blueprint $table) {
            $table->index(['customer_id', 'occurred_at'], 'idx_customer_timeline_customer_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_timeline_events');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
