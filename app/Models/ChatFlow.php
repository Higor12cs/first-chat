<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'name', 'slug', 'description', 'nodes', 'edges', 'triggers', 'is_active'])]
class ChatFlow extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * @return array<string, mixed>|null
     */
    public function startNode(): ?array
    {
        return collect($this->nodes)->firstWhere('type', 'start');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function node(string $id): ?array
    {
        return collect($this->nodes)->firstWhere('id', $id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function edgesFrom(string $nodeId): array
    {
        return collect($this->edges)->where('source', $nodeId)->values()->all();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nodes' => 'array',
            'edges' => 'array',
            'triggers' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
