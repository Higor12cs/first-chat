<?php

use App\Models\User;

it('refuses a new user once the tenant ceiling is reached', function (): void {
    $tenant = tenant(['max_users' => 2]);
    $owner = userFor($tenant);

    userFor($tenant);

    expect(User::query()->count())->toBe(2);

    $this->actingAs($owner)
        ->post('/usuarios', [
            'name' => 'Excedente',
            'email' => 'excedente@example.com',
            'password' => 'senha-secreta',
            'password_confirmation' => 'senha-secreta',
        ])
        ->assertSessionHasErrors('name');

    expect(User::query()->count())->toBe(2);
});

it('lets a tenant grow while it is under the ceiling', function (): void {
    $tenant = tenant(['max_users' => 5]);
    $owner = userFor($tenant);

    $this->actingAs($owner)
        ->post('/usuarios', [
            'name' => 'Nova Atendente',
            'email' => 'nova@example.com',
            'password' => 'senha-secreta',
            'password_confirmation' => 'senha-secreta',
        ])
        ->assertSessionHasNoErrors();

    expect(User::query()->count())->toBe(2);
});

it('reads the limits the administrator set on the tenant', function (): void {
    $tenant = tenant(['max_users' => 1, 'price_cents' => 45000]);
    $owner = userFor($tenant);

    expect($tenant->fresh()->limit('max_users'))->toBe(1)
        ->and($tenant->fresh()->priceCents())->toBe(45000);

    $this->actingAs($owner)
        ->post('/usuarios', [
            'name' => 'Excedente',
            'email' => 'excedente@example.com',
            'password' => 'senha-secreta',
            'password_confirmation' => 'senha-secreta',
        ])
        ->assertSessionHasErrors('name');
});

it('treats an empty limit as unlimited', function (): void {
    $tenant = tenant(['max_users' => null]);
    $owner = userFor($tenant);

    $this->actingAs($owner)
        ->post('/usuarios', [
            'name' => 'Mais Uma',
            'email' => 'maisuma@example.com',
            'password' => 'senha-secreta',
            'password_confirmation' => 'senha-secreta',
        ])
        ->assertSessionHasNoErrors();

    expect($tenant->fresh()->limit('max_users'))->toBeNull();
});
