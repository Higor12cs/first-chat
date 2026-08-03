<?php

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;

it('blocks a route the user has no permission for', function (): void {
    $tenant = tenant();
    $user = userFor($tenant, ['conversations.view']);

    $this->actingAs($user)->get('/contatos')->assertForbidden();
});

it('allows a route declared by a permission the user holds', function (): void {
    $tenant = tenant();
    $user = userFor($tenant, ['conversations.view', 'contacts.view']);

    $this->actingAs($user)->get('/contatos')->assertOk();
});

it('lets a super admin through every gate', function (): void {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get('/admin/tenants')->assertOk();
});

it('keeps the administration panel out of reach of tenant users', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $this->actingAs($user)->get('/admin/tenants')->assertForbidden();
});

it('only lists navigation items the user can reach', function (): void {
    $tenant = tenant();
    $user = userFor($tenant, ['conversations.view', 'dashboard.view']);

    $this->actingAs($user)
        ->get('/painel')
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', ['conversations.view', 'dashboard.view']));
});

it('declares a route for every permission that guards one', function (): void {
    $routes = PermissionRegistry::all()
        ->flatMap(fn ($permission): array => $permission->routes)
        ->unique();

    $registered = collect(app('router')->getRoutes())
        ->map(fn ($route): ?string => $route->getName())
        ->filter()
        ->all();

    foreach ($routes as $route) {
        expect($registered)->toContain($route);
    }
});

it('refuses access outside the allowed schedule', function (): void {
    $tenant = tenant();
    $user = userFor($tenant, null, ['blocked_until' => now()->addDay()]);

    $this->actingAs($user)->get('/painel')->assertRedirect('/entrar');
});
