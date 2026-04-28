<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatTipoPago extends Model
{
    protected $table = 'catTipoPagos';

    protected $primaryKey = 'IDTipoPago';

    public $incrementing = true; // El PK SÍ es autoincremental según la migración

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'TipoPago',
    ];
}
