<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Http\Middleware\IdentifyTenant;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantSelectionController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Auth/SelectTenant', [
            'tenants' => $user->accessibleTenants()
                ->map(fn (Tenant $tenant): array => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ])
                ->all(),
            'current_tenant_id' => $request->session()->get(IdentifyTenant::TENANT_KEY),
            'is_super_admin' => $user->is_super_admin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid'],
        ]);

        $user = $request->user();

        $tenant = $user->accessibleTenants()->firstWhere('id', $validated['tenant_id']);

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Você não tem acesso a esta conta.',
            ]);
        }

        $request->session()->put(IdentifyTenant::TENANT_KEY, $tenant->id);

        return redirect()->route('conversations.index')->with('success', "Você está atendendo em {$tenant->name}.");
    }
}
