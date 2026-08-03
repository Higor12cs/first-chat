<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_channels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('identifier');
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->boolean('is_group')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['channel_connection_id', 'identifier']);
            $table->index(['tenant_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_channels');
    }
};
