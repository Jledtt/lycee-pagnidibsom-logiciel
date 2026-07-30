<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_messages', function (Blueprint $table) {
            $table->string('delivery_status')->nullable()->after('provider_message_id')->index();
            $table->timestamp('delivery_status_at')->nullable()->after('delivery_status');
            $table->text('delivery_error')->nullable()->after('delivery_status_at');
            $table->timestamp('delivered_at')->nullable()->after('delivery_error');
            $table->timestamp('bounced_at')->nullable()->after('delivered_at');
            $table->timestamp('complained_at')->nullable()->after('bounced_at');
            $table->index('provider_message_id');
        });

        Schema::create('communication_email_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_message_id')
                ->nullable()
                ->constrained('communication_messages')
                ->nullOnDelete();
            $table->string('svix_id')->unique();
            $table->string('provider_message_id')->index();
            $table->string('event_type')->index();
            $table->timestamp('event_at')->index();
            $table->string('recipient_email')->nullable()->index();
            $table->text('reason')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_email_events');

        Schema::table('communication_messages', function (Blueprint $table) {
            $table->dropIndex(['provider_message_id']);
            $table->dropIndex(['delivery_status']);
            $table->dropColumn([
                'delivery_status',
                'delivery_status_at',
                'delivery_error',
                'delivered_at',
                'bounced_at',
                'complained_at',
            ]);
        });
    }
};
