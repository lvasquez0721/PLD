<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerfilTransaccional extends Model
{
    protected $table = "PerfilTransaccional";

    protected $fillable = [
        'IDPerfil',
        'NCliente',
        'Nombre',
        'EdoNacimiento',
        'NivelRiesgoNac',
        'CalculoNacimiento',
        'EdoDomicilio',
        'NivelRiesgoDoc',
        'CalculoResidencia',
        'EdoLabora',
        'NivelRiesgoResidencia',
        'CalculoLaboral',
        'TotalUbicacion',
        'Origen',
        'ORecursos',
        'Ingresos',
        'PromedioHA',
        'TotalEconomico',
        'OcupGiro',
        'NivelRiesgo',
        'CalculoOcupacion',
        'Perfil',
        'Periodo',
        'IDTipoEjecuccion',
        'AVGPrimaTotal',
        'AVGHaTotal',
        'STDEVPrimaTotal',
        'STDEVHaTotal',
        'origenRecursos',
        'ValorIngresoEstimado',
        'ValorHaEstimado',
    ];

    protected $casts = [
        'IDPerfil' => 'integer',
        'IDTipoEjecuccion' => 'integer',
        'AVGPrimaTotal' => 'decimal:2',
        'AVGHaTotal' => 'decimal:2',
        'STDEVPrimaTotal' => 'decimal:2',
        'STDEVHaTotal' => 'decimal:2',
        'ValorIngresoEstimado' => 'decimal:2',
        'ValorHaEstimado' => 'decimal:2',
    ];
}
