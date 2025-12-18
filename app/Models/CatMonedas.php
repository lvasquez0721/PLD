<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatMonedas extends Model
{
    protected $table = 'catMonedas';
    protected $primaryKey = 'IDMoneda';
    public $incrementing = false; // El PK NO es autoincrementable según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDMoneda',
        'Moneda',
        'Fecha',
    ];

    protected $casts = [
        'Fecha' => 'date',
    ];
}
