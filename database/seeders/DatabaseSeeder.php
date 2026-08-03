<?php

namespace Database\Seeders;

use App\Actions\Tenancy\CreateTenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->account('admin@admin.com', 'Administrador', isSuperAdmin: true);
        $owner = $this->account('user@user.com', 'Usuário');

        foreach ($this->companies() as $name => $seeder) {
            $tenant = $this->company($name, $owner, $seeder);

            app(TenantContext::class)->run($tenant, fn () => $this->join($tenant, $admin));
        }
    }

    /**
     * @return array<string, class-string<Seeder>>
     */
    private function companies(): array
    {
        return [
            'Consys' => ConsysSeeder::class,
            'Freevolt' => FreevoltSeeder::class,
        ];
    }

    private function account(string $email, string $name, bool $isSuperAdmin = false): User
    {
        return app(TenantContext::class)->runWithoutTenant(fn (): User => User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'is_super_admin' => $isSuperAdmin,
                'is_active' => true,
            ],
        ));
    }

    /**
     * @param  class-string<Seeder>  $seeder
     */
    private function company(string $name, User $owner, string $seeder): Tenant
    {
        $existing = Tenant::query()->where('slug', Str::slug($name))->first();

        if ($existing !== null) {
            return $existing;
        }

        $tenant = app(CreateTenant::class)->handle(
            name: $name,
            ownerName: $owner->name,
            ownerEmail: $owner->email,
            ownerPassword: 'password',
            maxConnections: 1,
            owner: $owner,
        );

        app(TenantContext::class)->run($tenant, fn () => $this->call($seeder));

        return $tenant;
    }

    private function join(Tenant $tenant, User $user): void
    {
        $user->memberships()->firstOrCreate(['tenant_id' => $tenant->id]);

        $administrator = Role::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'administrador')
            ->first();

        if ($administrator !== null) {
            $user->roles()->syncWithoutDetaching([$administrator->id]);
        }

        $user->unsetRelation('memberships')->unsetRelation('roles');
    }
}
