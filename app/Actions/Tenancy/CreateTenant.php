<?php

namespace App\Actions\Tenancy;

use App\Actions\Messaging\ProvisionTenantConnections;
use App\Domain\Tenancy\TenantContext;
use App\Models\Role;
use App\Models\ServiceQueue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ProvisionTenantConnections $provisionConnections,
    ) {}

    /**
     * @param  User|null  $owner  Conta já existente que passa a ser dona da nova
     */
    public function handle(
        string $name,
        string $ownerName,
        string $ownerEmail,
        string $ownerPassword,
        int $maxConnections = 1,
        ?string $document = null,
        ?User $owner = null,
    ): Tenant {
        return DB::transaction(function () use ($name, $ownerName, $ownerEmail, $ownerPassword, $maxConnections, $document, $owner): Tenant {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'document' => $document,
                'max_connections' => $maxConnections,
                'status' => 'active',
            ]);

            return $this->context->run($tenant, function () use ($tenant, $ownerName, $ownerEmail, $ownerPassword, $owner): Tenant {
                $administrator = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Administrador',
                    'slug' => 'administrador',
                    'description' => 'Acesso completo à conta.',
                    'is_locked' => true,
                ]);

                $administrator->syncPermissions(PermissionRegistry::keys());

                $agent = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Atendente',
                    'slug' => 'atendente',
                    'description' => 'Atende conversas das filas em que participa.',
                    'is_default' => true,
                ]);

                $agent->syncPermissions([
                    'conversations.view',
                    'conversations.reply',
                    'conversations.close',
                    'conversations.notes',
                    'conversations.tags',
                    'contacts.view',
                    'contacts.create',
                    'contacts.update',
                    'quick-replies.view',
                    'quick-replies.create',
                    'quick-replies.update',
                    'cards.view',
                ]);

                ServiceQueue::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Geral',
                    'slug' => 'geral',
                    'description' => 'Setor padrão para novos atendimentos.',
                    'color' => 'primary',
                    'priority' => 10,
                    'is_default' => true,
                ]);

                $owner ??= $this->context->runWithoutTenant(fn (): User => User::create([
                    'name' => $ownerName,
                    'email' => $ownerEmail,
                    'password' => $ownerPassword,
                    'is_active' => true,
                ]));

                $owner->memberships()->firstOrCreate(['tenant_id' => $tenant->id]);

                $owner->roles()->syncWithoutDetaching([$administrator->id]);

                $this->provisionConnections->handle($tenant);

                return $tenant;
            });
        });
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $candidate = $slug;
        $suffix = 1;

        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-".(++$suffix);
        }

        return $candidate;
    }
}
