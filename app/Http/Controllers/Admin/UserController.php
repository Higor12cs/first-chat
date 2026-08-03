<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\ServiceQueue;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'filters' => $request->only(['search', 'tenant']),
            'users' => User::query()
                ->acrossTenants()
                ->with('tenants')
                ->when($request->filled('search'), fn ($query) => $query->where(
                    fn ($inner) => $inner->where('name', 'ilike', "%{$request->string('search')}%")
                        ->orWhere('email', 'ilike', "%{$request->string('search')}%")
                ))
                ->when($request->filled('tenant'), fn ($query) => $query->whereHas(
                    'memberships',
                    fn (Builder $membership) => $membership->where('tenant_id', $request->string('tenant'))
                ))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_ids' => $user->tenants->pluck('id')->all(),
                    'tenants' => $user->tenants->pluck('name')->all(),
                    'is_super_admin' => $user->is_super_admin,
                    'is_active' => $user->is_active,
                    'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                    'created_at' => $user->created_at?->toIso8601String(),
                ]),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $this->context->runWithoutTenant(function () use ($validated): void {
            $user = User::create(collect($validated)->except('tenant_ids')->all());

            $this->syncMemberships($user, $validated['tenant_ids'] ?? []);
        });

        return back()->with('success', 'Usuário criado.');
    }

    public function update(Request $request, User $adminUser): RedirectResponse
    {
        $validated = $this->validated($request, $adminUser);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $this->context->runWithoutTenant(function () use ($adminUser, $validated): void {
            $adminUser->update(collect($validated)->except('tenant_ids')->all());

            $this->syncMemberships($adminUser, $validated['tenant_ids'] ?? []);
        });

        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroy(Request $request, User $adminUser): RedirectResponse
    {
        abort_if($adminUser->id === $request->user()->id, 403, 'Você não pode excluir o próprio usuário.');

        $adminUser->delete();

        return back()->with('success', 'Usuário excluído.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user === null ? 'required' : 'nullable', Password::min(8)],
            'tenant_ids' => ['array'],
            'tenant_ids.*' => ['uuid', 'exists:tenants,id'],
            'is_super_admin' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @param  array<int, string>  $tenantIds
     */
    private function syncMemberships(User $user, array $tenantIds): void
    {
        $removed = $user->memberships()->whereNotIn('tenant_id', $tenantIds)->pluck('tenant_id')->all();

        if ($removed !== []) {
            $user->roles()->detach(Role::query()->whereIn('tenant_id', $removed)->pluck('id'));
            $user->serviceQueues()->detach(ServiceQueue::query()->whereIn('tenant_id', $removed)->pluck('id'));
            $user->memberships()->whereIn('tenant_id', $removed)->delete();
        }

        $existing = $user->memberships()->pluck('tenant_id')->all();

        foreach (array_diff($tenantIds, $existing) as $tenantId) {
            $user->memberships()->create(['tenant_id' => $tenantId]);
        }

        $user->unsetRelation('memberships')->unsetRelation('tenants');
    }
}
