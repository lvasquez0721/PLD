<?php

use App\Models\CatParametriaPLD;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('el switch de entorno_desarrollo persiste en BD', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Cache::forget('entorno_desarrollo_activo');

    $response = $this->post(route('configuracion-cumplimiento.entorno'), ['activo' => true]);
    $response->assertRedirect(route('configuracion-cumplimiento.index'));

    expect(CatParametriaPLD::getEntornoDesarrolloActivo())->toBeTrue();

    $this->post(route('configuracion-cumplimiento.entorno'), ['activo' => false]);

    expect(CatParametriaPLD::getEntornoDesarrolloActivo())->toBeFalse();
});

test('la etiqueta se comparte en props de inertia', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    CatParametriaPLD::setEntornoDesarrolloActivo(true);
    Cache::forget('entorno_desarrollo_activo');

    $response = $this->get(route('configuracion-cumplimiento.index'));
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('envBadge')
        ->where('envBadge.activo', true)
        ->has('config.entorno_desarrollo')
    );
});

test('invitados no pueden cambiar la etiqueta', function () {
    $response = $this->post(route('configuracion-cumplimiento.entorno'), ['activo' => true]);
    $response->assertRedirect(route('login'));
});
