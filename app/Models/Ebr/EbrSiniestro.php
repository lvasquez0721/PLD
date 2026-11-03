<?php

namespace App\Models\Ebr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbrSiniestro extends Model
{
    protected $table = 'ebr_siniestros';

    protected $fillable = [
        'ejercicio_id', 'no_siniestro', 'no_poliza', 'no_cliente', 'fecha_pago',
        'monto_pagado', 'moneda', 'tipo_cambio', 'num_operaciones_salida',
        'estado', 'producto', 'grupo_producto', 'canal', 'row_hash',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'monto_pagado' => 'float',
        'tipo_cambio' => 'float',
        'num_operaciones_salida' => 'integer',
    ];

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(EbrEjercicio::class, 'ejercicio_id');
    }
}
