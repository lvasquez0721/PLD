<?php

// Script para probar la función darAltaCliente desde Tinker

// 1. Crear registros necesarios en tablas relacionadas
// CatTipoPersona
\App\Models\Clientes\CatTipoPersona::firstOrCreate(['IDTipoPersona' => 1], ['Descripcion' => 'Persona Física']);

// CatOcupacionesGiros
\App\Models\Clientes\CatOcupacionesGiros::firstOrCreate(['IDOcupacionGiro' => 3], ['Descripcion' => 'Profesional']);

// CatNacionalidad
\App\Models\Clientes\CatNacionalidad::firstOrCreate(['IDNacionalidad' => 1], ['Descripcion' => 'Mexicana']);

// CatEstados (para EstadoNacimiento y domicilio)
\App\Models\Clientes\CatEstados::firstOrCreate(['IDEstado' => 22], ['Descripcion' => 'Querétaro', 'Abreviatura' => 'QRO']);

// 2. Crear usuario de prueba si no existe
$user = \App\Models\User::firstOrCreate(
    ['email' => 'test@example.com'],
    ['name' => 'Test User', 'password' => bcrypt('password')]
);

// 3. Preparar los datos del cliente
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

// 4. Crear solicitud HTTP con los datos
$request = new \Illuminate\Http\Request($data);

// 5. Asignar usuario a la solicitud (para middleware auth:sanctum)
$request->setUserResolver(function () use ($user) {
    return $user;
});

// 6. Instanciar controlador y llamar al métodocontroller = new \App\Http\Controllers\ClientesControllerApi();
$response = $controller->darAltaCliente($request);

// 7. Mostrar respuesta
echo "Respuesta del servidor:\n";
print_r($response->getData());

echo "\nPrueba completada exitosamente!\n";
