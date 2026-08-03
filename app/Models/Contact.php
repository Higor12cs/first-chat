<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable([
    'tenant_id',
    'name',
    'nickname',
    'phone',
    'email',
    'document',
    'avatar_url',
    'notes',
    'custom_fields',
    'is_blocked',
    'last_interaction_at',
])]
class Contact extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public function channels(): HasMany
    {
        return $this->hasMany(ContactChannel::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function displayName(): string
    {
        return filled($this->nickname) ? (string) $this->nickname : (string) $this->name;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'ilike', "%{$term}%")
                    ->orWhere('nickname', 'ilike', "%{$term}%")
                    ->orWhere('phone', 'ilike', "%{$term}%")
                    ->orWhere('email', 'ilike', "%{$term}%");
            });
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'is_blocked' => 'boolean',
            'last_interaction_at' => 'datetime',
        ];
    }
}
