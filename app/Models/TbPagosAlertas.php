<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbPagosAlertas extends Model
{
    protected $table = 'tbPagosAlertas';

    protected $primaryKey = 'IDPagoAlerta';

    public $incrementing = true; // El PK es autoincremental según la migración

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'IDOperacionPago',
        'IDRegistroAlerta',
        'InstrumentoMonetario',
    ];
}
