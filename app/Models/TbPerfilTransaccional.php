<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbPerfilTransaccional extends Model
{
    protected $table = 'tbPerfilTransaccional';

    protected $primaryKey = 'IDRegistroPerfil';

    public $incrementing = true; // El PK ES autoincremental según la migración

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        // 'IDRegistroPerfil', // No incluir campo autoincremental en $fillable
        'IDCliente',
        'IDEstadoNacimiento',
        'NivelRiesgoNac',
        'CalculoNacimiento',
        'IDEstadoDomicilio',
        'NivelRiesgoDoc',
        'CalculoResidencia',
        'IDEstadoLabora',
        'NivelRiesgoResidencia',
        'CalculoLaboral',
        'TotalUbicacion',
        'Origen',
        'ORecursos',
        'IngresosMensuales',
        'PromedioHA',
        'TotalEconomico',
        'OcupGiro',
        'NivelRiesgo',
        'CalculoOcupacion',
        'Perfil',
        'FechaEjecucción',
        'IDTipoEjecuccion',
        'AVGPrimaTotal',
        'AVGHaTotal',
        'STDEVPrimaTotal',
        'STDEVHaTotal',
        'origenRecursos',
        'ValorIngresoEstimado',
        'ValorHaEstimado',
        'TimeStamp',
    ];
}
