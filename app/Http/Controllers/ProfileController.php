<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'auto_lock_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if (filled($validated['password'] ?? null) && ! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Senha atual incorreta.']);
        }

        $user->update(collect($validated)
            ->except('current_password')
            ->reject(fn (mixed $value, string $field): bool => $field === 'password' && blank($value))
            ->all());

        return back()->with('success', 'Perfil atualizado.');
    }
}
