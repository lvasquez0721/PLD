<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportesOp extends Model
{
    protected $table = 'tbReportesOp';

    protected $fillable = [
        'IDReporteOP',
        'Fecha',
        'Descripcion',
        'Usuario',
        'StatusReporte',
    ];

    protected $casts = [
        'Fecha' => 'datetime',
    ];
}
