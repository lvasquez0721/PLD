<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatMonedas extends Model
{
    protected $table = 'catMonedas';
    protected $primaryKey = 'IDMoneda';
    public $incrementing = false; // El PK NO es autoincrementable según la migración
    protected $keyType = 'string'; // Importante: Se espera un string en IDMoneda
    public $timestamps = true;

    protected $fillable = [
        'IDMoneda',
        'Moneda',
        'Fecha',
    ];

    protected $casts = [
        'IDMoneda' => 'string', // <-- Fuerza a string para evitar el error con valores como 'MXN'
        'Fecha' => 'date',
    ];
}
