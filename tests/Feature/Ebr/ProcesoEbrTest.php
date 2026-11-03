<?php

use App\Models\Ebr\EbrEjercicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function ebrUsuarioProceso(): User
{
    $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

    $user = User::forceCreate([
        'usuario' => 'proceso-ebr',
        'nombre' => 'Proceso',
        'apellido_p' => 'EBR',
        'apellido_m' => '',
        'email' => 'proceso-ebr@example.test',
        'password' => 'password',
        'email_verified_at' => now(),
    ]);

    $user->assignRole($role);

    return $user;
}

function ebrEjercicioProceso(array $overrides = []): EbrEjercicio
{
    return EbrEjercicio::create(array_merge([
        'anio' => 2025,
        'nombre' => 'EBR 2025',
        'fecha_inicio' => '2025-01-01',
        'fecha_fin' => '2025-12-31',
        'estado' => 'cerrado',
        'version_metodologia' => '2025',
        'mitigantes' => config('ebr.mitigantes'),
        'resultados' => ['elementos' => []],
        'totales' => ['inherente' => 2.47, 'residual' => 2.36, 'residual_final' => 1.53, 'nivel' => 'Bajo Riesgo'],
        'observaciones' => 'Conclusión original',
        'cerrado_at' => now(),
    ], $overrides));
}

test('reabrir regresa un ejercicio cerrado a calculado y conserva resultados', function () {
    $user = ebrUsuarioProceso();
    $ejercicio = ebrEjercicioProceso();

    $this->actingAs($user)
        ->post(route('ebr.reabrir', $ejercicio))
        ->assertSessionHas('toast');

    $ejercicio->refresh();

    expect($ejercicio->estado)->toBe('calculado')
        ->and($ejercicio->cerrado_at)->toBeNull()
        ->and($ejercicio->totales['residual_final'])->toBe(1.53);
});

test('no permite reabrir un ejercicio que no está cerrado', function () {
    $user = ebrUsuarioProceso();
    $ejercicio = ebrEjercicioProceso(['estado' => 'borrador', 'cerrado_at' => null]);

    $this->actingAs($user)
        ->post(route('ebr.reabrir', $ejercicio))
        ->assertSessionHas('toast');

    expect($ejercicio->fresh()->estado)->toBe('borrador');
});

test('bloquea observaciones en un ejercicio cerrado', function () {
    $user = ebrUsuarioProceso();
    $ejercicio = ebrEjercicioProceso();

    $this->actingAs($user)
        ->put(route('ebr.observaciones', $ejercicio), ['observaciones' => 'Cambio no permitido'])
        ->assertSessionHas('toast');

    expect($ejercicio->fresh()->observaciones)->toBe('Conclusión original');
});
