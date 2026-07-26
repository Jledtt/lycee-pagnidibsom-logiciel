<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->json('available_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('communication_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('announcement');
            $table->string('audience');
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role_name')->nullable();
            $table->string('title');
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('draft');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('communication_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('communication_campaigns')->nullOnDelete();
            $table->string('template_code')->nullable();
            $table->string('event_type');
            $table->nullableMorphs('related');
            $table->string('recipient_type')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_email')->index();
            $table->string('subject');
            $table->text('body');
            $table->string('channel')->default('email');
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->string('deduplication_key', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['event_type', 'status']);
            $table->index(['recipient_type', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_messages');
        Schema::dropIfExists('communication_campaigns');
        Schema::dropIfExists('communication_templates');
    }
};
