<?php

namespace App\Models\Ebr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbrImportacion extends Model
{
    protected $table = 'ebr_importaciones';

    protected $fillable = [
        'ejercicio_id', 'tipo', 'nombre_archivo', 'ruta', 'hash', 'total_filas',
        'filas_validas', 'filas_error', 'errores', 'resumen', 'estado', 'usuario_id',
    ];

    protected $casts = [
        'errores' => 'array',
        'resumen' => 'array',
        'total_filas' => 'integer',
        'filas_validas' => 'integer',
        'filas_error' => 'integer',
    ];

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(EbrEjercicio::class, 'ejercicio_id');
    }
}
