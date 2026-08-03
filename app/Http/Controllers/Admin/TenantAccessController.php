<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantAccessController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenants' => ['required', 'array', 'min:1'],
            'tenants.*' => ['uuid', 'exists:tenants,id'],
            'access_expires_at' => ['nullable', 'date_format:Y-m-d'],
        ], [], [
            'tenants' => 'tenants',
            'access_expires_at' => 'data limite de acesso',
        ]);

        $updated = Tenant::query()
            ->whereIn('id', $validated['tenants'])
            ->update(['access_expires_at' => $validated['access_expires_at'] ?? null]);

        return back()->with('success', $updated === 1
            ? 'Data limite de acesso atualizada para 1 tenant.'
            : "Data limite de acesso atualizada para {$updated} tenants.");
    }
}
