<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TotalPago extends Model
{
    protected $table = 'tbTotal_Pagos';

    protected $fillable = [
        'IDPoliza',
        'Endoso',
        'RPP',
        'Fecha_emision',
        'Fecha_ult_pago_cte',
        'Fecha_ult_pago_shcp',
        'Fecha_ult_pago_sagarpa',
        'Prima_asegurado',
        'Prima_gobierno',
        'Prima_otros',
        'Total_pagos_cte',
        'Total_pagos_shcp',
        'Total_pagos_sagarpa',
        'Gastos_Emision',
        'Ncliente',
        'visibleFechaPago',
        'IDMetodoPagoFront',
        'FacInterna',
    ];
}
