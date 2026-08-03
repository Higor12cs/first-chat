<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('cost_micro_cents')->default(0)->after('output_tokens');
        });

        foreach ((array) config('ai.pricing', []) as $model => $pricing) {
            DB::table('ai_interactions')
                ->where('model', $model)
                ->update(['cost_micro_cents' => DB::raw(
                    'input_tokens * '.(int) ($pricing['input'] ?? 0)
                    .' + output_tokens * '.(int) ($pricing['output'] ?? 0)
                )]);
        }

        Schema::table('ai_interactions', function (Blueprint $table): void {
            $table->dropColumn('cost_cents');
        });
    }

    public function down(): void
    {
        Schema::table('ai_interactions', function (Blueprint $table): void {
            $table->unsignedInteger('cost_cents')->default(0)->after('output_tokens');
        });

        DB::table('ai_interactions')->update([
            'cost_cents' => DB::raw('ceil(cost_micro_cents / 1000000.0)'),
        ]);

        Schema::table('ai_interactions', function (Blueprint $table): void {
            $table->dropColumn('cost_micro_cents');
        });
    }
};
