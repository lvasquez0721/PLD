<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListasNegrasUIF extends Model
{
    protected $table = 'tbListasNegrasUIF';

    protected $fillable = [
        'Buscador',
        'RFCCURP',
        'FechaNac',
        'FechaPubAcuerdo',
        'Acuerdo',
        'NoOficioUIF',
        'AnioLista',
    ];

    protected $casts = [
        'FechaNac' => 'date',
        'FechaPubAcuerdo' => 'date',
    ];
}
