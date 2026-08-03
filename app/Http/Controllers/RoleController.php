<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Roles/Index', [
            'roles' => RoleResource::collection(Role::query()->withCount('users')->orderBy('name')->get()),
            'permission_groups' => PermissionRegistry::grouped(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            ...$request->validated(),
            'slug' => Str::slug($request->string('name')),
        ]);

        $role->syncPermissions($request->input('permissions', []));

        return back()->with('success', 'Papel criado.');
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->is_locked, 403, 'Este papel não pode ser alterado.');

        $role->update($request->validated());
        $role->syncPermissions($request->input('permissions', []));

        return back()->with('success', 'Papel atualizado.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_locked, 403, 'Este papel não pode ser excluído.');

        $role->delete();

        return back()->with('success', 'Papel excluído.');
    }
}
