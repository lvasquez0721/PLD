<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametriaPLD extends Model
{
    protected $table = 'catParametriaPLD';

    protected $fillable = [
        'Parametro',
        'Valor',
        'Tipo',
        'Activo',
        'TimeStampAlta',
        'TimeStampModificacion',
    ];

    protected $casts = [
        'Activo' => 'boolean',
        'TimeStampAlta' => 'datetime',
        'TimeStampModificacion' => 'datetime',
    ];
}
