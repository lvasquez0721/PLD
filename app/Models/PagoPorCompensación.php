<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoPorCompensación extends Model
{
    protected $table = "pagos_por_compensacion";

    protected $fillable = [
        'Poliza',
        'Endoso',
        'RPP',
        'Fecha_Pago',
        'Fecha_registro',
        'Fecha_apcontable',
        'Importe_pago',
        'IDMetodoPago',
        'FacturaSi_NO',
        'Ncliente',
        'Tipo_Movimiento',
        'Concepto',
        'FolioControl',
        'FolioControlInterno',
        'NumeroRecibo',
        'MesCierre',
        'AnioCierre',
        'CierreDiario',
        'FacturaComplemento',
        'FolioControlCFDI',
        'FolioControlComp',
        'FacturacionAgrupada',
        'TimeStamp',
        'Facturable',
    ];

    // Opcionalmente, puedes definir los casts para convertir automáticamente los tipos de datos
    protected $casts = [
        'Fecha_Pago' => 'date',
        'Fecha_registro' => 'date',
        'Fecha_apcontable' => 'date',
        'Importe_pago' => 'decimal:2',
        'IDMetodoPago' => 'integer',
        'FacturaSi_NO' => 'boolean',
        'CierreDiario' => 'boolean',
        'FacturacionAgrupada' => 'boolean',
        'Facturable' => 'boolean',
        'TimeStamp' => 'datetime',
        'AnioCierre' => 'integer',
    ];
}
