<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('hides_other_conversations')->default(false);
            $table->boolean('signs_messages')->nullable();
            $table->json('work_days')->nullable();
            $table->time('work_starts_at')->nullable();
            $table->time('work_ends_at')->nullable();
            $table->timestamp('blocked_until')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};
