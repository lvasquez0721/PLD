<?php

// Script para probar la función darAltaCliente directamente, sin Tinker

// Cargar el framework Laravel
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Función para obtener los nombres de campos de una tabla
function getTableColumns($tableName)
{
    return DB::getSchemaBuilder()->getColumnListing($tableName);
}

// Función para imprimir información de la tabla
function printTableInfo($modelClass, $tableName)
{
    echo "\n=== Información de $tableName ===\n";
    echo "Modelo: $modelClass\n";
    echo 'Campos: '.implode(', ', getTableColumns($tableName))."\n";
}

echo "=== Test de Creación de Cliente ===\n";

try {
    // 1. Obtener información de las tablas relacionadas
    printTableInfo('App\Models\Clientes\CatTipoPersona', 'catTipoPersona');
    printTableInfo('App\Models\Clientes\CatOcupacionesGiros', 'catOcupacionesGiros');
    printTableInfo('App\Models\Clientes\CatNacionalidad', 'catNacionalidad');
    printTableInfo('App\Models\Clientes\CatEstados', 'catEstados');

    // 2. Crear registros en tablas relacionadas con nombres de campos correctos
    echo "\n=== Creando registros necesarios ===\n";

    // CatTipoPersona - el campo correcto es 'TipoPersona', no 'Descripcion'
    $tipoPersona = App\Models\Clientes\CatTipoPersona::firstOrCreate(
        ['IDTipoPersona' => 1],
        ['TipoPersona' => 'Persona Física']
    );
    echo "✓ TipoPersona creado/actualizado: {$tipoPersona->IDTipoPersona} - {$tipoPersona->TipoPersona}\n";

    // CatOcupacionesGiros - el campo correcto es 'OcupacionGiro', no 'Descripcion'
    $ocupacion = App\Models\Clientes\CatOcupacionesGiros::firstOrCreate(
        ['IDOcupacionGiro' => 3],
        ['OcupacionGiro' => 'Profesional']
    );
    echo "✓ OcupacionGiro creado/actualizado: {$ocupacion->IDOcupacionGiro} - {$ocupacion->OcupacionGiro}\n";

    // CatNacionalidad - el campo correcto es 'Nacionalidad', no 'Descripcion'
    $nacionalidad = App\Models\Clientes\CatNacionalidad::firstOrCreate(
        ['IDNacionalidad' => 1],
        ['Nacionalidad' => 'Mexicana']
    );
    echo "✓ Nacionalidad creada/actualizada: {$nacionalidad->IDNacionalidad} - {$nacionalidad->Nacionalidad}\n";

    // CatEstados - el campo correcto es 'Estado', no 'Descripcion'
    $estado = App\Models\Clientes\CatEstados::firstOrCreate(
        ['IDEstado' => 22],
        ['Estado' => 'Querétaro', 'Abreviatura' => 'QRO']
    );
    echo "✓ Estado creado/actualizado: {$estado->IDEstado} - {$estado->Estado}\n";

    // 3. Preparar datos del cliente
    echo "\n=== Preparando datos del cliente ===\n";
    $data = [
        'RFC' => 'PEMJ800101ABC',
        'nombre' => 'Juan',
        'apellidoPaterno' => 'Pérez',
        'apellidoMaterno' => 'Martínez',
        'razonSocial' => 'test',
        'IDTipoPersona' => 1,
        'CURP' => 'PEMJ800101HDFRRN01',
        'IDOcupacionGiro' => 3,
        'fechaNacimiento' => '1980-01-01',
        'fechaConstitucion' => null,
        'folioMercantil' => 'Hola',
        'IDNacionalidad' => 1,
        'IDEstadoNacimiento' => 22,
        'IDSistemaOrigen' => 1,
        'NoClienteSistema' => 'EXT-001',
        'domicilios' => [
            [
                'calle' => 'Av. Siempre Viva',
                'noExterior' => '742',
                'noInterior' => 'A',
                'colonia' => 'Centro',
                'CP' => '76000',
                'IDEstado' => 22,
                'municipio' => 'Querétaro',
                'localidad' => 'Querétaro',
                'telefono' => '4421234567',
            ],
        ],
    ];

    // 4. Crear solicitud HTTP
    $request = new Illuminate\Http\Request($data);

    // 5. Instanciar controlador y llamar al método
    echo "\n=== Llamando a darAltaCliente ===\n";
    $controller = new App\Http\Controllers\ClientesControllerApi;
    $response = $controller->darAltaCliente($request);

    // 6. Mostrar resultado
    echo "\n=== Resultado ===\n";
    echo "Código de respuesta: {$response->getStatusCode()}\n";
    echo 'Contenido: '.$response->getContent()."\n";

} catch (Exception $e) {
    echo "\n=== ERROR ===\n";
    echo 'Mensaje: '.$e->getMessage()."\n";
    echo 'Stack trace: '.$e->getTraceAsString()."\n";
}

echo "\n=== Fin del test ===\n";
