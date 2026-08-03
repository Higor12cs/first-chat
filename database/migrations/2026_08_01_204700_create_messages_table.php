<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('reply_to_message_id')->nullable();
            $table->string('external_id')->nullable();
            $table->string('direction');
            $table->string('type')->default('text');
            $table->string('status')->default('pending');
            $table->string('source')->default('contact');
            $table->boolean('is_internal')->default(false);
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_name')->nullable();
            $table->string('media_mime_type')->nullable();
            $table->text('transcription')->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->unique(['tenant_id', 'external_id']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreign('reply_to_message_id')->references('id')->on('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
