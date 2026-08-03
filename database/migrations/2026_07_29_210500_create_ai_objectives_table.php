<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_objectives', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->unsignedInteger('max_tokens')->default(1024);
            $table->text('system_prompt');
            $table->json('tools')->nullable();
            $table->unsignedInteger('cost_limit_cents')->nullable();
            $table->unsignedSmallInteger('max_turns')->default(20);
            $table->uuid('handoff_service_queue_id')->nullable();
            $table->text('closing_condition')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_objectives');
    }
};
