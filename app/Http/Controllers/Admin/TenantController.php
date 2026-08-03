<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\ProvisionTenantConnections;
use App\Actions\Tenancy\CreateTenant;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->withCount(['users', 'connections', 'conversations'])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'ilike', "%{$request->string('search')}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Tenants/Index', [
            'filters' => $request->only('search'),
            'tenants' => $tenants->through(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'document' => $tenant->document,
                'status' => $tenant->status,
                'price_cents' => $tenant->priceCents(),
                'max_users' => $tenant->limit('max_users'),
                'max_connections' => $tenant->limit('max_connections'),
                'max_monthly_messages' => $tenant->limit('max_monthly_messages'),
                'max_monthly_ai_cost_cents' => $tenant->limit('max_monthly_ai_cost_cents'),
                'users_count' => $tenant->users_count,
                'connections_count' => $tenant->connections_count,
                'conversations_count' => $tenant->conversations_count,
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
                'access_expires_at' => $tenant->access_expires_at?->toDateString(),
                'has_valid_access' => $tenant->hasValidAccess(),
                'created_at' => $tenant->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request, CreateTenant $createTenant): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'max_connections' => ['required', 'integer', 'min:1', 'max:100'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', Rule::unique('users', 'email')],
            'owner_password' => ['required', Password::min(8)],
        ]);

        $createTenant->handle(
            name: $validated['name'],
            ownerName: $validated['owner_name'],
            ownerEmail: $validated['owner_email'],
            ownerPassword: $validated['owner_password'],
            maxConnections: (int) $validated['max_connections'],
            document: $validated['document'] ?? null,
        );

        return back()->with('success', 'Tenant criado.');
    }

    public function update(Request $request, Tenant $tenant, ProvisionTenantConnections $provisionConnections): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'trialing', 'suspended'])],
            'trial_ends_at' => ['nullable', 'date'],
            'access_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_connections' => ['required', 'integer', 'min:1', 'max:100'],
            'max_monthly_messages' => ['nullable', 'integer', 'min:1'],
            'max_monthly_ai_cost_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $tenant->update([
            ...$validated,
            'suspended_at' => $validated['status'] === 'suspended' ? now() : null,
        ]);

        $provisioned = $provisionConnections->handle($tenant->fresh());

        return back()->with('success', $provisioned->isEmpty()
            ? 'Tenant atualizado.'
            : "Tenant atualizado e {$provisioned->count()} conexão(ões) provisionada(s).");
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return back()->with('success', 'Tenant excluído.');
    }
}
