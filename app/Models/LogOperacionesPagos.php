<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogOperacionesPagos extends Model
{
    protected $table = 'logOperacionesPagos';

    protected $primaryKey = 'IDOperacionPago';

    public $incrementing = true; // Autoincremental PK

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'IDOperacion',
        'IDCliente',
        'Monto',
        'IDMoneda',
        'IDFormaPago',
        'TipoCambio',
        'FechaPago',
        'TimeStampRegistro',
    ];
}
