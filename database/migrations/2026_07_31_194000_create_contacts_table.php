<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('document')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'nickname']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
