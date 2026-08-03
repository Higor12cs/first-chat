<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->timestamps();

            $table->unique(['role_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
