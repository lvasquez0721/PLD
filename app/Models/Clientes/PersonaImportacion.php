<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Model;

class PersonaImportacion extends Model
{
    protected $table = 'personas_importaciones';

    protected $fillable = [
        'tipo_persona',
        'nombre_archivo',
        'ruta',
        'hash',
        'total_filas',
        'filas_validas',
        'filas_error',
        'errores',
        'resumen',
        'estado',
        'usuario_id',
    ];

    protected $casts = [
        'total_filas' => 'integer',
        'filas_validas' => 'integer',
        'filas_error' => 'integer',
        'errores' => 'array',
        'resumen' => 'array',
    ];
}
