<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_replies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('shortcut');
            $table->string('title');
            $table->text('body');
            $table->boolean('is_favorite')->default(false);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'shortcut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_replies');
    }
};
