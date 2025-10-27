<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalculoInusualidadPrimaEmitida extends Model
{
    protected $table = 'CalculoInusualidadPrimaEmitida';

    protected $fillable = [
        'PolizaHistorico',
        'EndosoHistorico',
        'FechaEmision',
        'PrimaTotalHistorica',
        'NCliente',
        'Cliente',
        'FechaInicioMuestra',
        'FechaFinMuestra',
        'PolizaEmitida',
        'EndosoEmitido',
        'PrimaEmitida',
        'Detectado',
        'AniosAnterioresConsiderados',
        'Promedio',
        'DesviacionEstandar',
        'FactorDesviacionEstandar',
        'LimiteInferior',
        'LimiteSuperior',
        'TimeStamp'
    ];

    protected $casts = [
        'FechaEmision' => 'datetime',
        'PrimaTotalHistorica' => 'decimal:6',
        'FechaInicioMuestra' => 'datetime',
        'FechaFinMuestra' => 'datetime',
        'PrimaEmitida' => 'decimal:6',
        'Detectado' => 'boolean',
        'AniosAnterioresConsiderados' => 'integer',
        'Promedio' => 'decimal:6',
        'DesviacionEstandar' => 'decimal:6',
        'FactorDesviacionEstandar' => 'decimal:6',
        'LimiteInferior' => 'decimal:6',
        'LimiteSuperior' => 'decimal:6',
        'TimeStamp' => 'datetime',
    ];
}
