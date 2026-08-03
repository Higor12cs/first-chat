<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('driver');
            $table->string('channel');
            $table->string('status')->default('disconnected');
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->string('external_identifier')->nullable();
            $table->text('qr_code')->nullable();
            $table->string('pair_code')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->foreignUuid('default_service_queue_id')->nullable()->constrained('service_queues')->nullOnDelete();
            $table->foreignUuid('chat_flow_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connections');
    }
};
