<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbListasNegraCNSF extends Model
{
    protected $table = 'tbListasNegraCNSF';
    protected $primaryKey = 'IDRegistroListaCNSF';
    public $incrementing = false;
    protected $keyType = 'unsignedBigInteger';

    protected $fillable = [
        'IDRegistroListaCNSF',
        'Nombre',
        'Direccion',
        'RFC',
        'CURP',
        'Pais',
        'FechaNacimiento',
        'OficiosRelacionados',
        'UsuarioAlta',
        'TimeStampAlta',
        'UsuarioModif',
        'TimeStampModif',
    ];

    public $timestamps = true;

    protected $casts = [
        'FechaNacimiento' => 'date',
        'TimeStampAlta' => 'datetime',
        'TimeStampModif' => 'datetime',
    ];
}
