<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_queues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('color')->default('primary');
            $table->string('icon')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->string('assignment_strategy')->default('manual');
            $table->json('business_hours')->nullable();
            $table->text('outside_hours_message')->nullable();
            $table->foreignUuid('ai_objective_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'priority']);
        });

        Schema::table('ai_objectives', function (Blueprint $table): void {
            $table->foreign('handoff_service_queue_id')->references('id')->on('service_queues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_objectives', function (Blueprint $table): void {
            $table->dropForeign(['handoff_service_queue_id']);
        });

        Schema::dropIfExists('service_queues');
    }
};
