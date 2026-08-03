<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\IdentifyTenant;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->isReachableBy($request->user()), 403, 'Conta suspensa.');

        $request->session()->put(IdentifyTenant::TENANT_KEY, $tenant->id);

        return redirect('/atendimentos')->with('success', "Você está operando como {$tenant->name}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(IdentifyTenant::TENANT_KEY);

        return redirect('/admin/tenants')->with('success', 'Você saiu do workspace.');
    }
}
