<?php

use App\Models\Ebr\EbrEjercicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function ebrUsuarioEditor(): User
{
    $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

    $user = User::forceCreate([
        'usuario' => 'editor-ebr',
        'nombre' => 'Editor',
        'apellido_p' => 'EBR',
        'apellido_m' => '',
        'email' => 'editor-ebr@example.test',
        'password' => 'password',
        'email_verified_at' => now(),
    ]);

    $user->assignRole($role);

    return $user;
}

function ebrMitigantesConEfecto(): array
{
    return collect(config('ebr.mitigantes'))->map(function ($m) {
        $efectividad = (float) (config('ebr.niveles_efectividad')[$m['nivel']] ?? 1);

        return $m + [
            'efectividad' => $efectividad,
            'efecto' => round((float) $m['max'] * $efectividad, 6),
        ];
    })->all();
}

function ebrEjercicioMitigantes(array $overrides = []): EbrEjercicio
{
    return EbrEjercicio::create(array_merge([
        'anio' => 2025,
        'nombre' => 'EBR 2025',
        'fecha_inicio' => '2025-01-01',
        'fecha_fin' => '2025-12-31',
        'estado' => 'borrador',
        'version_metodologia' => '2025',
        'mitigantes' => ebrMitigantesConEfecto(),
    ], $overrides));
}

function ebrPayloadMitigantes(): array
{
    return collect(config('ebr.mitigantes'))->map(fn ($m) => [
        'id' => $m['id'],
        'nombre' => $m['nombre'],
        'scope' => $m['scope'],
        'max' => $m['max'],
        'nivel' => 'Optimizado',
    ])->all();
}

test('mantiene fijos los niveles de efectividad de la metodologia 2025', function () {
    $user = ebrUsuarioEditor();
    $ejercicio = ebrEjercicioMitigantes();
    $payload = ebrPayloadMitigantes();
    $payload[0]['nivel'] = 'Monitoreado';

    $this->actingAs($user)
        ->put(route('ebr.mitigantes', $ejercicio), ['mitigantes' => $payload])
        ->assertSessionHasErrors('mitigantes');

    $ejercicio->refresh();

    expect($ejercicio->mitigantes[0]['nivel'])->toBe('Optimizado')
        ->and($ejercicio->mitigantes[0]['efecto'])->toBe(0.2)
        ->and($ejercicio->mitigantes[0]['scope'])->toBe('producto_1_2')
        ->and($ejercicio->mitigantes[0]['max'])->toBe(0.2);
});

test('rechaza alteraciones de alcance, nombre o maximo', function (string $campo, mixed $valor) {
    $user = ebrUsuarioEditor();
    $ejercicio = ebrEjercicioMitigantes();
    $payload = ebrPayloadMitigantes();
    $payload[0][$campo] = $valor;

    $this->actingAs($user)
        ->put(route('ebr.mitigantes', $ejercicio), ['mitigantes' => $payload])
        ->assertSessionHasErrors('mitigantes');

    expect($ejercicio->fresh()->mitigantes[0][$campo])->not->toBe($valor);
})->with([
    'alcance' => ['scope', 'entidad'],
    'nombre' => ['nombre', 'Otro nombre'],
    'maximo' => ['max', 1],
]);

test('rechaza mitigantes duplicados', function () {
    $user = ebrUsuarioEditor();
    $ejercicio = ebrEjercicioMitigantes();
    $payload = ebrPayloadMitigantes();
    $payload[1]['id'] = $payload[0]['id'];

    $this->actingAs($user)
        ->put(route('ebr.mitigantes', $ejercicio), ['mitigantes' => $payload])
        ->assertSessionHasErrors('mitigantes');
});

test('rechaza mitigantes desconocidos', function () {
    $user = ebrUsuarioEditor();
    $ejercicio = ebrEjercicioMitigantes();
    $payload = ebrPayloadMitigantes();
    $payload[0]['id'] = 999;

    $this->actingAs($user)
        ->put(route('ebr.mitigantes', $ejercicio), ['mitigantes' => $payload])
        ->assertSessionHasErrors('mitigantes');
});

test('rechaza listas incompletas de mitigantes', function () {
    $user = ebrUsuarioEditor();
    $ejercicio = ebrEjercicioMitigantes();
    $payload = ebrPayloadMitigantes();
    array_pop($payload);

    $this->actingAs($user)
        ->put(route('ebr.mitigantes', $ejercicio), ['mitigantes' => $payload])
        ->assertSessionHasErrors('mitigantes');
});

test('no permite modificar mitigantes de un ejercicio cerrado', function () {
    $user = ebrUsuarioEditor();
    $ejercicio = ebrEjercicioMitigantes(['estado' => 'cerrado']);
    $payload = ebrPayloadMitigantes();
    $payload[0]['nivel'] = 'Informal';

    $this->actingAs($user)
        ->put(route('ebr.mitigantes', $ejercicio), ['mitigantes' => $payload])
        ->assertSessionHas('toast');

    expect($ejercicio->fresh()->mitigantes[0]['nivel'])->toBe('Optimizado');
});

test('exige que el ejercicio cubra doce meses completos', function () {
    $user = ebrUsuarioEditor();

    $this->actingAs($user)
        ->post(route('ebr.store'), [
            'anio' => 2026,
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-11-30',
        ])
        ->assertSessionHasErrors('fecha_inicio');

    expect(EbrEjercicio::where('anio', 2026)->exists())->toBeFalse();
});

test('acepta un ejercicio anual completo', function () {
    $user = ebrUsuarioEditor();

    $this->actingAs($user)
        ->post(route('ebr.store'), [
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
        ])
        ->assertSessionHasNoErrors();

    $ejercicio = EbrEjercicio::where('anio', 2026)->first();

    expect($ejercicio)->not->toBeNull()
        ->and($ejercicio->mitigantes)->toHaveCount(9);
});

test('el informe incluye la seccion de mitigantes y el total de entidad', function () {
    $ejercicio = ebrEjercicioMitigantes(['estado' => 'calculado']);

    $resultados = [
        'resumen_datos' => [
            'polizas' => 0, 'clientes' => 0, 'operaciones_entrada' => 0,
            'operaciones_salida' => 0, 'alertas' => 0,
            'alertas_procedentes' => 0, 'alertas_relevantes' => 0,
        ],
        'elementos' => [
            'productos' => [
                'detalle' => [], 'inherente' => 2.5, 'residual' => 2.38,
                'nivel' => 'Medio Riesgo', 'nivel_residual' => 'Medio Riesgo',
                'mitigante' => 0.0,
            ],
            'clientes' => [
                'pf' => [
                    'inherente' => 2.86, 'residual' => 2.4,
                    'nivel' => 'Medio Riesgo', 'nivel_residual' => 'Medio Riesgo',
                    'indicadores' => [],
                ],
                'pm' => [
                    'inherente' => 2.86, 'residual' => 2.4,
                    'nivel' => 'Medio Riesgo', 'nivel_residual' => 'Medio Riesgo',
                    'indicadores' => [],
                ],
                'inherente' => 2.86, 'residual' => 2.4,
                'nivel' => 'Medio Riesgo', 'nivel_residual' => 'Medio Riesgo',
                'mitigante' => 0.1,
            ],
            'geografia' => [
                'inherente' => 3.3, 'residual' => 3.3,
                'nivel_residual' => 'Alto Riesgo', 'paises' => [], 'estados' => [],
                'pais' => [],
            ],
            'canales' => [
                'detalle' => [], 'inherente' => 2.82, 'residual' => 2.82,
                'nivel_residual' => 'Medio Riesgo',
            ],
            'transacciones' => [
                'indicadores' => [], 'inherente' => 0.94, 'residual' => 0.94,
                'nivel_residual' => 'Bajo Riesgo',
            ],
        ],
        'institucion' => [
            'detalle' => [], 'inherente' => 2.47, 'nivel_inherente' => 'Medio Riesgo',
            'residual' => 2.36, 'nivel_residual' => 'Medio Riesgo',
            'mitigante_entidad' => 0.35, 'residual_final' => 1.53, 'nivel' => 'Bajo Riesgo',
        ],
        'advertencias' => [],
    ];

    $html = view('ebr.informe', ['ejercicio' => $ejercicio, 'r' => $resultados])->render();

    expect($html)->toContain('2.2 Mitigantes')
        ->toContain('Total mitigantes de entidad')
        ->toContain('35%')
        ->toContain('1.53');
});
