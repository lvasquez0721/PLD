<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatParametriaPLD extends Model
{
    protected $table = 'catParametriaPLD';
    protected $primaryKey = 'IDParametro';
    public $incrementing = false; // El campo IDParametro NO es autoincremental según la migración
    protected $keyType = 'int';

    protected $fillable = [
        'IDParametro', // SÍ debe incluirse ya que no es autoincremental
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
