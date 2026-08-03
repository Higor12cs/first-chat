<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LockScreenController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->locked_at === null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Lock');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['locked_at' => now()])->save();

        return redirect()->route('lock.show');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->string('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Senha incorreta.']);
        }

        $request->user()->forceFill([
            'locked_at' => null,
            'last_seen_at' => now(),
        ])->save();

        return redirect()->route('dashboard');
    }
}
