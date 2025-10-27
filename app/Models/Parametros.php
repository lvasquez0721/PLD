<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parametros extends Model
{
    protected $table = "tbParametros";

    protected $fillable = [
        'IDParametro',
        'PorNacimiento',
        'PorResidencia',
        'PorPredio',
        'PorNacionalidad',
        'PorAmbitoLaboral',
        'PorUbicacion',
        'PorOrigenRecursos',
        'PorIngresosEstimados',
        'PorPromedioUR',
        'PorDatosEconomicos',
        'PorDatosLaborales',
        'FechaActualizacion',
    ];

    protected $casts = [
        'IDParametro' => 'integer',
        'FechaActualizacion' => 'datetime',
    ];
}
