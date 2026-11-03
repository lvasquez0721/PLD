<?php

namespace App\Models\Ebr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbrAlerta extends Model
{
    protected $table = 'ebr_alertas';

    protected $fillable = [
        'ejercicio_id', 'patron', 'no_cliente', 'no_poliza', 'id_operacion',
        'fecha_deteccion', 'monto', 'moneda', 'tipo_cambio', 'instrumento_monetario', 'agente', 'estatus',
        'procedio', 'num_operaciones', 'descripcion', 'razones', 'row_hash',
    ];

    protected $casts = [
        'fecha_deteccion' => 'date',
        'monto' => 'float',
        'tipo_cambio' => 'float',
        'procedio' => 'boolean',
        'num_operaciones' => 'integer',
    ];

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(EbrEjercicio::class, 'ejercicio_id');
    }
}
