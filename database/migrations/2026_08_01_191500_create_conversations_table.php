<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_queue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('ai_objective_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('chat_flow_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('status')->default('pending');
            $table->string('subject')->nullable();
            $table->boolean('is_group')->default(false);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->unsignedInteger('unread_count')->default(0);
            $table->json('flow_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('no_action_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'last_message_at']);
            $table->index(['tenant_id', 'assigned_user_id']);
            $table->index(['tenant_id', 'service_queue_id']);
            $table->index(['tenant_id', 'no_action_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
