# Plan: Insertar log en CatIDClientesSistema al detectar RFC duplicado

## Archivo a modificar
`app/Http/Controllers/ClientesControllerApi.php`

## Cambio
En el bloque de RFC duplicado (líneas 70-76), antes de retornar la respuesta, agregar inserción en `CatIDClientesSistema`.

### Código actual (líneas 70-76)
```php
if ($clienteExistente) {
    return response()->json([
        'codigoError' => 2,
        'message' => 'El RFC ya se encuentra registrado.',
        'error_code' => 'RFC_DUPLICADO',
        'IDCliente' => $clienteExistente->IDCliente,
    ], 200);
}
```

### Código nuevo
```php
if ($clienteExistente) {
    if (! empty($data['IDSistemaOrigen']) && ! empty($data['NoClienteSistema'])) {
        $existeRegistroSistema = CatIDClientesSistema::where('IDCliente', $clienteExistente->IDCliente)
            ->where('IDSistema', $data['IDSistemaOrigen'])
            ->exists();
        if (! $existeRegistroSistema) {
            try {
                $nuevoIDOrigen = (CatIDClientesSistema::max('IDOrigenSistema') ?? 0) + 1;
                CatIDClientesSistema::create([
                    'IDOrigenSistema' => $nuevoIDOrigen,
                    'IDCliente' => $clienteExistente->IDCliente,
                    'IDSistema' => $data['IDSistemaOrigen'],
                    'NCliente' => $data['NoClienteSistema'],
                ]);
            } catch (\Exception $e) {
                Log::error('Error al registrar cliente duplicado en CatIDClientesSistema: '.$e->getMessage());
            }
        }
    }

    return response()->json([
        'codigoError' => 2,
        'message' => 'El RFC ya se encuentra registrado.',
        'error_code' => 'RFC_DUPLICADO',
        'IDCliente' => $clienteExistente->IDCliente,
    ], 200);
}
```

## Lógica
1. Si se detecta RFC duplicado → obtiene `$clienteExistente`
2. Si `IDSistemaOrigen` y `NoClienteSistema` vienen en el request:
   - Verifica si ya existe registro en `CatIDClientesSistema` para la combinación `(IDCliente, IDSistema)`
   - Si NO existe → inserta nuevo registro
   - Si YA existe → no hace nada (evita duplicados)
   - La inserción está envuelta en try-catch con Log::error
3. Retorna `IDCliente` en el response (igual que antes)

## Notas
- Solo se modifica el caso de RFC no-genérico (no `XAXX010101000`)
- Se evita duplicar registros en `CatIDClientesSistema` validando `IDCliente` + `IDSistema`
- La respuesta al cliente no cambia: siempre retorna `IDCliente` y `codigoError: 2`
