<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alertas extends Model
{
    protected $table = 'tbAlertas';

    protected $fillable = [
        'IDAlertas',
        'Folio',
        'Patron',
        'NCliente',
        'Nombre',
        'NoOperacion',
        'NoPoliza',
        'FechaDeteccion',
        'Hora',
        'FechaOperacion',
        'HoraOperacion',
        'NoMovimiento',
        'Monto',
        'InstrumentoMonetario',
        'Agente',
        'Estatus',
        'Descripcion',
        'Razones',
        'Evidencias',
        'IDReporteOP',
        'IDPago',
    ];

    protected $casts = [
        'FechaDeteccion' => 'datetime',
        'Hora' => 'string',
        'FechaOperacion' => 'datetime',
        'HoraOperacion' => 'string',
        'Monto' => 'decimal:6',
        'IDAlertas' => 'integer',
        'IDReporteOP' => 'integer',
        'IDPago' => 'integer',
    ];
}
