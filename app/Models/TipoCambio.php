<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCambio extends Model
{
    protected $table = "Cat_TipoCambio";

    protected $fillable = [
        'fecha',
        'campko',
        'DOF_dolar',
        'DOF_udi',
        'FIX_dolar',
        'DOF_TIIE28',
        'DOF_TIIE91',
        'FIX_udi',
        'FIX_TIIE28',
        'FIX_CETES',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'campko' => 'decimal:6',
        'DOF_dolar' => 'decimal:6',
        'DOF_udi' => 'decimal:6',
        'FIX_dolar' => 'decimal:6',
        'DOF_TIIE28' => 'decimal:6',
        'DOF_TIIE91' => 'decimal:6',
        'FIX_udi' => 'decimal:6',
        'FIX_TIIE28' => 'decimal:6',
        'FIX_CETES' => 'decimal:6',
    ];
}
