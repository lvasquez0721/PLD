<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatParametrosPerfilTrans extends Model
{
    protected $table = 'catParametrosPerfilTrans';
    protected $primaryKey = 'IDRegistroParametro';
    public $incrementing = true; // El PK es autoincremental según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        // 'IDRegistroParametro', // No incluir campo autoincremental en $fillable
        'PorcentajeNacimiento',
        'PorcentajeResidencia',
        'PorcentajePredio',
        'PorcentajeNacionalidad',
        'PorcentajeAmbitoLaboral',
        'PorcentajeUbicacion',
        'PorcentajeOrigenRecursos',
        'PorcentajeIngresosEstimados',
        'PorcentajePromedioUR',
        'PorcentajeDatosEconomicos',
        'PorcentajeDatosLaborales',
        'TimeStampAlta',
        'Activo',
    ];
}
