<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatParametriaPLD extends Model
{
    protected $table = 'catParametriaPLD';
    protected $primaryKey = 'IDParametro';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'Parametro',
        'Valor',
        'TipoDato',
        'Activo',
        'TimeStampAlta',
        'TimeStampModificacion',
    ];

    public $timestamps = true;

    protected $casts = [
        'Activo' => 'boolean',
        'TimeStampAlta' => 'datetime',
        'TimeStampModificacion' => 'datetime',
    ];
}
