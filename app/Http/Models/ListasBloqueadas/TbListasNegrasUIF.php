<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbListasNegrasUIF extends Model
{
    protected $table = 'tbListasNegrasUIF';
    protected $primaryKey = 'IDRegistroListaUIF';
    public $incrementing = false;
    protected $keyType = 'unsignedBigInteger';

    protected $fillable = [
        'IDRegistroListaUIF',
        'Nombre',
        'RFC',
        'CURP',
        'FechaNacimiento',
        'FechaPubAcuerdo',
        'Acuerdo',
        'NoOficioUIF',
        'AnioLista',
        'UsuarioAlta',
        'TimeStampAlta',
        'UsuarioModif',
        'TimeStampModif',
    ];

    public $timestamps = true;

    protected $casts = [
        'FechaNacimiento' => 'date',
        'FechaPubAcuerdo' => 'date',
        'TimeStampAlta' => 'datetime',
        'TimeStampModif' => 'datetime',
    ];
}
