<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Poliza extends Model
{
    protected $table = 'tbPolizas';

    protected $primaryKey = 'IDPoliza';

    protected $fillable = [
        // Foreign keys
        'IDSolicitud',
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
        'IDFormaPago',
        'IDConvenio',
        'IDFunciones',
        'IDEspecies',
        // Policy information
        'NoPoliza',
        'NoRRP',
        'FEmisionPoliza',
        'StatusPoliza',
        'SuperAsegurada',
        'SumaAsegurada',
        'SumaASeguradaTotal',
        'PrimaTotal',
        // Dates
        'FIV',
        'FFV',
        'FLPPoliza',
        // Premium details
        'PrimaAsegurado',
        'PrimaGob',
        'PrimaApoyos',
        'Tarifa',
        'Rendimiento',
        'Precio',
        'GastoEmision',
        // Subsidy and support information
        'SubSidioGobSiNo',
        'PorcentajeGob',
        'ApoyoCruzadaSiNo',
        'PorcentajeApoyos',
        // Beneficiaries and payment
        'Beneficiarios',
        'FechaPago',
        'FormaPago',
        'MontoPagado',
        'ConceptoPago',
        'IndemnizacionTotal',
        // Agent commission
        'ComisionAgente',
        // User and year
        'Usuario',
        'año',
        // Additional policy details
        'FLDFolioPoliza',
        'Coaseguro',
        'Franquicia',
        'Cve_convenios',
        'Conv_prop',
        'TipoPoliza',
        'ObservacionesCaratula',
        'PagoPorlintermediario',
        'FacturaPorProductor',
        'FolioSolicitud',
        // File information
        'PathArchivo',
        'NombreArchivo',
        // Insurance legend
        'LeyendaAseguramiento',
    ];

    protected $casts = [
        'SuperAsegurada' => 'boolean',
        'SubSidioGobSiNo' => 'boolean',
        'ApoyoCruzadaSiNo' => 'boolean',
        'Coaseguro' => 'boolean',
        'Franquicia' => 'boolean',
        'PagoPorlintermediario' => 'boolean',
        'FacturaPorProductor' => 'boolean',
        'FEmisionPoliza' => 'date',
        'FIV' => 'date',
        'FFV' => 'date',
        'FLPPoliza' => 'date',
        'FechaPago' => 'date',
        'ano' => 'integer',
        'SumaAsegurada' => 'decimal:2',
        'SumaASeguradaTotal' => 'decimal:2',
        'PrimaTotal' => 'decimal:2',
        'PrimaAsegurado' => 'decimal:2',
        'PrimaGob' => 'decimal:2',
        'PrimaApoyos' => 'decimal:2',
        'Tarifa' => 'decimal:4',
        'Rendimiento' => 'decimal:4',
        'Precio' => 'decimal:2',
        'GastoEmision' => 'decimal:2',
        'PorcentajeGob' => 'decimal:2',
        'PorcentajeApoyos' => 'decimal:2',
        'MontoPagado' => 'decimal:2',
        'IndemnizacionTotal' => 'decimal:2',
        'ComisionAgente' => 'decimal:2',
    ];

    // Example relationships

    // public function agente()
    // {
    //     return $this->belongsTo(Agente::class, 'IDAgente');
    // }

    // public function solicitud()
    // {
    //     return $this->belongsTo(Solicitud::class, 'IDSolicitud');
    // }
}
