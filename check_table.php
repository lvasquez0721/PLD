<?php

// Script para verificar la estructura de la tabla logClientesDomicilio

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Estructura de logClientesDomicilio ===\n";

// Obtener campos de la tabla
$columns = DB::getSchemaBuilder()->getColumnListing('logClientesDomicilio');
echo "Campos: " . implode(', ', $columns) . "\n\n";

// Obtener detalles de cada campo
$fields = DB::select("SHOW COLUMNS FROM logClientesDomicilio");
foreach ($fields as $field) {
    echo "{$field->Field}: {$field->Type}";
    if ($field->Null === 'YES') echo ' (NULL)';
    if ($field->Key === 'PRI') echo ' (PRIMARY KEY)';
    if (strpos($field->Extra, 'auto_increment') !== false) echo ' (AUTO_INCREMENT)';
    echo "\n";
}

echo "\n=== Fin de la verificación ===\n";