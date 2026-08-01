<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_type', 10);
            $table->unsignedBigInteger('recipient_id');
            $table->string('type', 50);
            $table->string('channel', 20)->default('in_app');
            $table->string('title', 150);
            $table->string('message', 500);
            $table->json('data')->nullable();
            $table->string('delivery_status', 20)->default('pending');
            $table->string('failed_reason')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE `notifications` ADD CONSTRAINT `chk_notifications_recipient_type` CHECK (`recipient_type` IN ('user','customer'))");
        DB::statement("ALTER TABLE `notifications` ADD CONSTRAINT `chk_notifications_channel` CHECK (`channel` IN ('in_app','whatsapp','sms','email'))");
        DB::statement("ALTER TABLE `notifications` ADD CONSTRAINT `chk_notifications_delivery_status` CHECK (`delivery_status` IN ('pending','sent','failed'))");
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['recipient_type', 'recipient_id', 'delivery_status'], 'idx_notifications_recipient');
            $table->index('created_at', 'idx_notifications_created_at');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 10);
            $table->unsignedBigInteger('owner_id');
            $table->string('channel', 20);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'channel'], 'uq_notification_preferences');
        });
        DB::statement("ALTER TABLE `notification_preferences` ADD CONSTRAINT `chk_notification_preferences_owner_type` CHECK (`owner_type` IN ('user','customer'))");
        DB::statement("ALTER TABLE `notification_preferences` ADD CONSTRAINT `chk_notification_preferences_channel` CHECK (`channel` IN ('in_app','whatsapp','sms','email'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
