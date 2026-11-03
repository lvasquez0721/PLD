<?php

namespace App\Models\Ebr;

use Illuminate\Database\Eloquent\Model;

class EbrCatEstado extends Model
{
    protected $table = 'ebr_cat_estados';

    protected $fillable = [
        'estado', 'valor_paz', 'nivel_paz', 'riesgo_ubicacion',
        'score_paz', 'score_frontera', 'activo',
    ];

    protected $casts = [
        'valor_paz' => 'float',
        'riesgo_ubicacion' => 'integer',
        'score_paz' => 'float',
        'score_frontera' => 'float',
        'activo' => 'boolean',
    ];
}
