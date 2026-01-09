<?php

namespace App\Models\ListasBloqueadas;

use Illuminate\Database\Eloquent\Model;

class TbListasNegrasUIF extends Model
{
    protected $table = 'tbListasNegrasUIF';
    protected $primaryKey = 'IDRegistroListaUIF';
    public $incrementing = true; // El PK es autoincremental según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        // 'IDRegistroListaUIF', // No incluir campo autoincremental en $fillable
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

    protected $casts = [
        'FechaNacimiento' => 'date',
        'FechaPubAcuerdo' => 'date',
        'TimeStampAlta' => 'datetime',
        'TimeStampModif' => 'datetime',
    ];
}
