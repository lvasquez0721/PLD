<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalculoInusualidadPrimaEmitida extends Model
{
    use HasFactory;

    protected $table = 'logcalculoinusualidadprimaemitida';

    public $timestamps = false;

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
        'TimeStamp',
    ];
}
