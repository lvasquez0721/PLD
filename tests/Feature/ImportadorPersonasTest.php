<?php

use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\TbClientes;
use App\Services\PLD\ImportadorPersonas;
use App\Services\PLD\RegistroPLDService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Valida la carga masiva de agentes y colaboradores desde CSV.
 */
beforeEach(function () {
    config(['database.connections.mysql.database' => 'PLD-UAT']);
    DB::purge('mysql');
    DB::connection('mysql')->beginTransaction();
    Storage::fake('local');

    $this->importador = new ImportadorPersonas;
});

afterEach(function () {
    DB::connection('mysql')->rollBack();
});

function csvImportacionPersonas(string $tipo, array $filas = []): string
{
    $headers = config("personas_importacion.{$tipo}.headers", []);
    $base = config("personas_importacion.{$tipo}.ejemplos.0", []);

    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, $headers);

    foreach ($filas ?: [$base] as $fila) {
        $valores = array_merge($base, $fila);
        $linea = [];
        foreach ($headers as $header) {
            $linea[] = $valores[$header] ?? '';
        }
        fputcsv($stream, $linea);
    }

    rewind($stream);
    $contenido = stream_get_contents($stream);
    fclose($stream);

    return $contenido;
}

function archivoCsvPersonas(string $contenido): UploadedFile
{
    return UploadedFile::fake()->createWithContent('personas.csv', $contenido);
}

test('genera plantillas distintas para agentes y colaboradores', function () {
    $agente = $this->importador->plantilla(TbClientes::TIPO_AGENTE);
    $colaborador = $this->importador->plantilla(TbClientes::TIPO_COLABORADOR);

    expect($agente['headers'])->toContain('rfc', 'ocupacion_giro', 'calle')
        ->and($agente['ejemplos'][0]['ocupacion_giro'])->toBe('Agente de seguros')
        ->and($colaborador['ejemplos'][0]['ocupacion_giro'])->toBe('Empleado del sector privado (u.i.f)');
});

test('prevalida y confirma la importación de agentes y colaboradores con ocupación por defecto', function () {
    $mock = Mockery::mock(RegistroPLDService::class);
    $mock->shouldReceive('analizarYRegistrar')
        ->twice()
        ->andReturn([
            'personaBloqueada' => false,
            'esPPE' => false,
            'esAltoRiesgo' => false,
            'alertasGeneradas' => [],
        ]);
    $mock->shouldReceive('notificar')->twice();
    app()->instance(RegistroPLDService::class, $mock);

    $casos = [
        TbClientes::TIPO_AGENTE => [
            'rfc' => 'AGTE000101AB1',
            'ocupacion_giro' => '',
        ],
        TbClientes::TIPO_COLABORADOR => [
            'rfc' => 'COLA000101AB2',
            'ocupacion_giro' => '',
        ],
    ];

    foreach ($casos as $tipo => $overrides) {
        $csv = csvImportacionPersonas($tipo, [$overrides]);
        $importacion = $this->importador->prevalidar(archivoCsvPersonas($csv), $tipo);

        expect($importacion->estado)->toBe('prevalidado')
            ->and($importacion->total_filas)->toBe(1)
            ->and($importacion->filas_validas)->toBe(1);

        $importacion = $this->importador->confirmar($importacion);

        expect($importacion->estado)->toBe('importado')
            ->and($importacion->filas_validas)->toBe(1);

        $persona = TbClientes::where('RFC', $overrides['rfc'])->first();

        expect($persona)->not->toBeNull()
            ->and($persona->TipoPersona)->toBe($tipo)
            ->and($persona->IDTipoPersona)->toBe(1)
            ->and($persona->domicilios()->count())->toBe(1);

        if ($tipo === TbClientes::TIPO_AGENTE) {
            $ocupacion = CatOcupacionesGiros::where('OcupacionGiro', 'Agente de seguros')->value('IDOcupacionGiro');
            expect($persona->ckAgente)->toBe(1)
                ->and((int) $persona->IDOcupacionGiro)->toBe((int) $ocupacion);
        } else {
            $ocupacion = CatOcupacionesGiros::where('OcupacionGiro', 'like', 'Empleado del sector privado%')->value('IDOcupacionGiro');
            expect($persona->ckAgente)->toBe(0)
                ->and((int) $persona->IDOcupacionGiro)->toBe((int) $ocupacion);
        }
    }
});

test('rechaza RFC duplicados en el archivo y existentes en base de datos', function () {
    $existente = new TbClientes;
    $existente->RFC = 'EXIST000101AB1';
    $existente->Nombre = 'EXISTENTE';
    $existente->ApellidoPaterno = 'PRUEBA';
    $existente->IDTipoPersona = 1;
    $existente->IDNacionalidad = 'MX';
    $existente->TipoPersona = TbClientes::TIPO_AGENTE;
    $existente->CoincideEnListasNegras = false;
    $existente->EsPPEActivo = false;
    $existente->Activo = true;
    $existente->save();

    $csv = csvImportacionPersonas(TbClientes::TIPO_AGENTE, [
        ['rfc' => 'EXIST000101AB1'],
        ['rfc' => 'DUPER000101AB1'],
        ['rfc' => 'DUPER000101AB1'],
    ]);

    $importacion = $this->importador->prevalidar(archivoCsvPersonas($csv), TbClientes::TIPO_AGENTE);
    $mensajes = implode(' ', array_column($importacion->errores ?? [], 'mensaje'));

    expect($importacion->estado)->toBe('error')
        ->and($importacion->filas_validas)->toBe(0)
        ->and($mensajes)->toContain('Ya existe una persona registrada')
        ->and($mensajes)->toContain('duplicado en el archivo');
});

test('rechaza archivos a los que les faltan columnas obligatorias', function () {
    $csv = "tipo_persona,nombre\nPersona Física,Juan\n";

    $importacion = $this->importador->prevalidar(archivoCsvPersonas($csv), TbClientes::TIPO_AGENTE);
    $mensajes = implode(' ', array_column($importacion->errores ?? [], 'mensaje'));

    expect($importacion->estado)->toBe('error')
        ->and($mensajes)->toContain('Faltan columnas obligatorias');
});

test('hace rollback total si falla una fila durante la confirmación', function () {
    $mock = Mockery::mock(RegistroPLDService::class);
    $llamadas = 0;
    $mock->shouldReceive('analizarYRegistrar')
        ->twice()
        ->andReturnUsing(function () use (&$llamadas) {
            $llamadas++;
            if ($llamadas === 2) {
                throw new RuntimeException('Fallo simulado');
            }

            return [
                'personaBloqueada' => false,
                'esPPE' => false,
                'esAltoRiesgo' => false,
                'alertasGeneradas' => [],
            ];
        });
    $mock->shouldNotReceive('notificar');
    app()->instance(RegistroPLDService::class, $mock);

    $csv = csvImportacionPersonas(TbClientes::TIPO_AGENTE, [
        ['rfc' => 'ROLL000101AB1'],
        ['rfc' => 'ROLL000101AB2'],
    ]);

    $importacion = $this->importador->prevalidar(archivoCsvPersonas($csv), TbClientes::TIPO_AGENTE);
    $importacion = $this->importador->confirmar($importacion);

    expect($importacion->estado)->toBe('error')
        ->and(TbClientes::whereIn('RFC', ['ROLL000101AB1', 'ROLL000101AB2'])->count())->toBe(0);
});
