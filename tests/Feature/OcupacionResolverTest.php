<?php

use App\Http\Controllers\ReporteOperacionesController;
use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\TbReporteRegulatorioPLD;
use App\Services\PLD\OcupacionResolver;
use Illuminate\Support\Facades\DB;

/**
 * Valida que la columna Ocupacion nunca quede vacía en los reportes "Nuevo",
 * sin importar si el sujeto es cliente, agente o colaborador interno.
 */
beforeEach(function () {
    config(['database.connections.mysql.database' => 'PLD-UAT']);
    DB::purge('mysql');
});

test('el resolver usa la ocupación capturada o un valor por defecto según el rol', function () {
    $resolver = new OcupacionResolver;

    $agente = new TbClientes(['TipoPersona' => TbClientes::TIPO_AGENTE]);
    $colaborador = new TbClientes(['TipoPersona' => TbClientes::TIPO_COLABORADOR]);
    $cliente = new TbClientes(['TipoPersona' => TbClientes::TIPO_CLIENTE]);
    $conOcupacion = new TbClientes(['IDOcupacionGiro' => 390]);

    expect($resolver->resolverDesdePersona($agente))->toBe(OcupacionResolver::CVE_AGENTE)
        ->and($resolver->resolverDesdePersona($colaborador))->toBe(OcupacionResolver::CVE_GENERICO)
        ->and($resolver->resolverDesdePersona($cliente))->toBe(OcupacionResolver::CVE_GENERICO)
        ->and($resolver->resolverDesdePersona($conOcupacion))->toBe(OcupacionResolver::CVE_GENERICO);
});

test('la exportación Nuevo llena Ocupacion para cliente, agente y colaborador sin ocupación', function () {
    DB::connection('mysql')->beginTransaction();

    try {
        $resolver = new OcupacionResolver;
        $controller = new ReporteOperacionesController;
        $mapear = new ReflectionMethod($controller, 'mapearFilasDesdeAlertas');

        $roles = [
            TbClientes::TIPO_CLIENTE => $resolver->resolverDesdePersona(new TbClientes(['TipoPersona' => TbClientes::TIPO_CLIENTE])),
            TbClientes::TIPO_AGENTE => $resolver->resolverDesdePersona(new TbClientes(['TipoPersona' => TbClientes::TIPO_AGENTE])),
            TbClientes::TIPO_COLABORADOR => $resolver->resolverDesdePersona(new TbClientes(['TipoPersona' => TbClientes::TIPO_COLABORADOR])),
        ];

        $idsAlertas = [];

        foreach (array_keys($roles) as $rol) {
            $persona = new TbClientes;
            $persona->RFC = strtoupper(substr('T'.$rol.uniqid(), 0, 13));
            $persona->Nombre = 'PRUEBA';
            $persona->ApellidoPaterno = $rol;
            $persona->IDTipoPersona = 1;
            $persona->IDNacionalidad = 'MX';
            $persona->TipoPersona = $rol;
            $persona->IDOcupacionGiro = null;
            $persona->CoincideEnListasNegras = false;
            $persona->EsPPEActivo = false;
            $persona->Activo = true;
            $persona->save();

            $alerta = TbAlertas::create([
                'Patron' => 'Nuevo',
                'IDCliente' => $persona->IDCliente,
                'Cliente' => TbAlertas::nombreParaCliente($persona),
                'FechaDeteccion' => now()->toDateString(),
                'Estatus' => 'Por reportar',
            ]);

            $idsAlertas[$rol] = $alerta->IDRegistroAlerta;
        }

        $alertas = TbAlertas::whereIn('IDRegistroAlerta', array_values($idsAlertas))
            ->orderBy('IDRegistroAlerta')
            ->get();

        $filas = $mapear->invoke($controller, $alertas);
        $ocupaciones = array_column($filas, 27);

        expect($ocupaciones)->toHaveCount(count($roles));

        foreach (array_keys($roles) as $rol) {
            expect($ocupaciones)->toContain($roles[$rol]);
        }
    } finally {
        DB::connection('mysql')->rollBack();
    }
});

test('la exportación respeta la Ocupacion ya guardada en el reporte regulatorio', function () {
    DB::connection('mysql')->beginTransaction();

    try {
        $persona = new TbClientes;
        $persona->RFC = strtoupper(substr('TREP'.uniqid(), 0, 13));
        $persona->Nombre = 'PRUEBA';
        $persona->ApellidoPaterno = 'REPORTE';
        $persona->IDTipoPersona = 1;
        $persona->IDNacionalidad = 'MX';
        $persona->TipoPersona = TbClientes::TIPO_CLIENTE;
        $persona->CoincideEnListasNegras = false;
        $persona->EsPPEActivo = false;
        $persona->Activo = true;
        $persona->save();

        $alerta = TbAlertas::create([
            'Patron' => 'Nuevo',
            'IDCliente' => $persona->IDCliente,
            'Cliente' => TbAlertas::nombreParaCliente($persona),
            'FechaDeteccion' => now()->toDateString(),
            'Estatus' => 'Por reportar',
        ]);

        $reporte = new TbReporteRegulatorioPLD;
        $reporte->IDRegistroAlerta = $alerta->IDRegistroAlerta;
        $reporte->IDTipoReporte = 3;
        $reporte->TipoReporte = 'Nuevo';
        $reporte->Ocupacion = '1234567';

        $controller = new ReporteOperacionesController;
        $mapear = new ReflectionMethod($controller, 'mapearFilas');

        $filas = $mapear->invoke($controller, collect([$reporte]));

        expect($filas[0][27])->toBe('1234567');
    } finally {
        DB::connection('mysql')->rollBack();
    }
});
