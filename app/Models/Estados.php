<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estados extends Model
{
    protected $table = "cat_Estados";

    protected $fillable = [
        'IdEstado',
        'Estado',
        'CveEntidad',
        'ApoyoSAGARPA16',
        'ApoyoSAGARPA17',
        'NivelRiesgo',
        'TimeStampModif',
    ];

    protected $casts = [
        'IdEstado' => 'integer',
        'TimeStampModif' => 'datetime',
    ];
}
