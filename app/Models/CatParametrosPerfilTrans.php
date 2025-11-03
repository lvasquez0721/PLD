<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatParametrosPerfilTrans extends Model
{
    protected $table = 'catParametrosPerfilTrans';
    protected $primaryKey = 'IDRegistroParametro';
    public $incrementing = false; // El PK NO es autoincrementable según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDRegistroParametro',
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
