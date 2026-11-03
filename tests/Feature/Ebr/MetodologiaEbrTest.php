<?php

use App\Models\Ebr\EbrEjercicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function ebrUsuarioMetodologia(): User
{
    $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

    $user = User::forceCreate([
        'usuario' => 'auditor-ebr',
        'nombre' => 'Auditor EBR',
        'apellido_p' => 'Prueba',
        'apellido_m' => '',
        'email' => 'auditor-ebr@example.test',
        'password' => 'password',
        'email_verified_at' => now(),
    ]);

    $user->assignRole($role);

    return $user;
}

test('expone la metodologia y los metadatos de auditoria del ejercicio', function () {
    $user = ebrUsuarioMetodologia();

    $ejercicio = EbrEjercicio::create([
        'anio' => 2098,
        'nombre' => 'EBR 2098',
        'fecha_inicio' => '2098-01-01',
        'fecha_fin' => '2098-12-31',
        'estado' => 'cerrado',
        'version_metodologia' => '2025',
        'mitigantes' => config('ebr.mitigantes'),
        'importado_por' => $user->id,
        'calculado_por' => $user->id,
        'calculado_at' => now(),
        'cerrado_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('ebr.show', $ejercicio))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Ebr/Show')
            ->where('ejercicio.version_metodologia', '2025')
            ->where('ejercicio.importado_por_nombre', 'Auditor EBR')
            ->where('ejercicio.calculado_por_nombre', 'Auditor EBR')
            ->where('ejercicio.calculado_at', $ejercicio->calculado_at?->format('Y-m-d H:i'))
            ->where('ejercicio.cerrado_at', $ejercicio->cerrado_at?->format('Y-m-d H:i'))
            ->where('metodologia.version', '2025')
            ->where('metodologia.productos.offset', 0.4)
            ->where('metodologia.clientes.pf.offset', 0.4)
            ->where('metodologia.clientes.pm.offset', 0.35)
            ->where('metodologia.canales.offset', 0.2)
            ->where('metodologia.institucion.pesos.productos', 0.2)
            ->where('metodologia.transacciones.denominador_alertas', 0.1)
            ->has('metodologia.productos.indicadores', 5)
            ->has('metodologia.clientes.pf.indicadores', 6)
            ->has('metodologia.clientes.pm.indicadores', 6)
            ->has('metodologia.canales.indicadores', 4)
            ->has('metodologia.transacciones.indicadores', 4)
            ->has('metodologia.geografia.estados.indicadores', 2)
        );
});
