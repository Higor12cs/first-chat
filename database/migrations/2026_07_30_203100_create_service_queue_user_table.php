<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_queue_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('service_queue_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_queue_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_queue_user');
    }
};
