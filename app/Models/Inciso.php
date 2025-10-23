<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inciso extends Model
{
    protected $table = 'tbIncisos';

    protected $fillable = [
        'IDPoliza',
        'IDSolicitud',
        'NoPoliza',
        'NoRPP',
        'FEmisionPoliza',
        'StatusPoliza',
        'SuperAsegurada',
        'SumaAsegurada',
        'SumaASeguradaTotal',
        'PrimaTotal',
        'FIV',
        'FFV',
        'FLPPoliza',
        'PrimaAsegurado',
        'PrimaGob',
        'PrimaApoyos',
        'Tarifa',
        'Rendimiento',
        'Precio',
        'GastoEmision',
        'SubSidioGobSiNo',
        'PorcentajeGob',
        'ApoyoCruzadaSiNo',
        'PorcentajeApoyos',
        'Beneficiarios',
        'IDFormaPago',
        'FormaPago',
        'MontoPagado',
        'ConceptoPago',
        'IndemnizacionTotal',
        'IDCiclo',
        'IDModalidad',
        'IDCultivo',
        'IDEstado',
        'IDRamo',
        'IDSubramo',
        'IDProducto',
        'IDMetodoEva',
        'IDUnidadRiesgo',
        'IDMoneda',
        'IDOficina',
        'IDAgente',
        'MontoPagado',
    ];
}
