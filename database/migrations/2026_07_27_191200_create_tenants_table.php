<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('document')->nullable();
            $table->string('status')->default('active');
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->timestamp('trial_ends_at')->nullable();
            $table->date('access_expires_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_connections')->nullable()->default(1);
            $table->unsignedInteger('max_monthly_messages')->nullable();
            $table->unsignedInteger('max_monthly_ai_cost_cents')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('access_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
