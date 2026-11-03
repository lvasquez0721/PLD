<?php

namespace App\Models\Ebr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbrPoliza extends Model
{
    protected $table = 'ebr_polizas';

    protected $fillable = [
        'ejercicio_id', 'no_poliza', 'id_operacion', 'folio_endoso', 'tipo_movimiento', 'no_cliente', 'nombre_cliente', 'tipo_persona',
        'rfc', 'curp', 'fecha_nacimiento', 'fecha_constitucion', 'fecha_primera_relacion',
        'edad', 'antiguedad_empresa_anios', 'antiguedad_relacion_anios',
        'ocupacion_giro', 'actividad_nivel_riesgo', 'actividad_agrupada', 'estado',
        'pais_nacionalidad', 'pais_operacion', 'categoria_pld', 'lista_riesgo',
        'canal', 'agente', 'ramo', 'producto', 'grupo_producto', 'prima_total',
        'moneda', 'tipo_cambio', 'fecha_emision', 'num_operaciones_entrada',
        'excluida', 'motivo_exclusion', 'row_hash', 'clave_operacion',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_constitucion' => 'date',
        'fecha_primera_relacion' => 'date',
        'fecha_emision' => 'date',
        'edad' => 'float',
        'antiguedad_empresa_anios' => 'float',
        'antiguedad_relacion_anios' => 'float',
        'prima_total' => 'float',
        'tipo_cambio' => 'float',
        'num_operaciones_entrada' => 'integer',
        'actividad_nivel_riesgo' => 'integer',
        'lista_riesgo' => 'integer',
        'excluida' => 'boolean',
    ];

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(EbrEjercicio::class, 'ejercicio_id');
    }
}
