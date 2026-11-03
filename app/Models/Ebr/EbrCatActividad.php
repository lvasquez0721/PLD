<?php

namespace App\Models\Ebr;

use Illuminate\Database\Eloquent\Model;

class EbrCatActividad extends Model
{
    protected $table = 'ebr_cat_actividades';

    protected $fillable = ['ocupacion_giro', 'nivel_riesgo', 'ocupacion_agrupada', 'activo'];

    protected $casts = ['nivel_riesgo' => 'integer', 'activo' => 'boolean'];
}
