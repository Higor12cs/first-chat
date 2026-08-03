<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(fn (Request $request): bool => Gate::check('viewHorizon', [$request->user()]));
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user): bool => $user?->is_super_admin === true);
    }
}
