<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        $user->forceFill([
            'locked_at' => null,
            'last_seen_at' => now(),
        ])->save();

        return $this->destinationFor($request, $user);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function destinationFor(Request $request, User $user): RedirectResponse
    {
        if ($user->is_super_admin) {
            return redirect()->route('admin.tenants.index');
        }

        $tenants = $user->accessibleTenants();

        if ($tenants->count() !== 1) {
            return redirect()->route('tenants.select');
        }

        $request->session()->put(IdentifyTenant::TENANT_KEY, $tenants->first()->id);

        return redirect()->intended(route('conversations.index'));
    }
}
