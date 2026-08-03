<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Http\Resources\UserResource;
use App\Support\Alerts\AlertBuilder;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
            ],
            'auth' => [
                'user' => $user === null ? null : UserResource::make($user->loadMissing('roles'))->resolve(),
                'permissions' => $user?->permissions()->all() ?? [],
                'is_super_admin' => (bool) $user?->is_super_admin,
                'is_locked' => $user?->locked_at !== null,
                'auto_lock_minutes' => $user?->auto_lock_minutes,
                'service_queue_ids' => $user?->serviceQueues()->pluck('service_queues.id')->all() ?? [],
            ],
            'tenant' => function () use ($user): ?array {
                $tenant = app(TenantContext::class)->get();

                return $tenant === null ? null : [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'is_workspace' => (bool) $user?->is_super_admin && ! $user->belongsToTenant($tenant),
                    'access_expires_at' => $tenant->access_expires_at?->toDateString(),
                ];
            },
            'can_switch_tenant' => fn (): bool => $user !== null
                && ($user->is_super_admin || $user->tenants()->count() > 1),
            'navigation' => fn (): array => $user === null ? [] : app(NavigationBuilder::class)->for($user),
            'alerts' => fn (): array => app(AlertBuilder::class)->for($user),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
