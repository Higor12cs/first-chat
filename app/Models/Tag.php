<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable(['tenant_id', 'name', 'slug', 'color', 'icon', 'description', 'automation'])]
class Tag extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public function contacts(): MorphToMany
    {
        return $this->morphedByMany(Contact::class, 'taggable');
    }

    public function conversations(): MorphToMany
    {
        return $this->morphedByMany(Conversation::class, 'taggable');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'automation' => 'array',
        ];
    }
}
