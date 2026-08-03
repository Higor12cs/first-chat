<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Fillable(['tenant_id', 'name', 'slug', 'description', 'is_default', 'is_locked'])]
class Role extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return Collection<int, string>
     */
    public function permissions(): Collection
    {
        return $this->permissionRows()->pluck('permission');
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        $this->permissionRows()->delete();

        $rows = collect($permissions)
            ->unique()
            ->map(fn (string $permission): array => [
                'role_id' => $this->id,
                'permission' => $permission,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows !== []) {
            DB::table('permission_role')->insert($rows);
        }
    }

    private function permissionRows(): QueryBuilder
    {
        return DB::table('permission_role')->where('role_id', $this->id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }
}
