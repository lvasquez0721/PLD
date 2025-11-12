<?php

namespace App\Models\ListasBloqueadas;

use Illuminate\Database\Eloquent\Model;

class LogBitEspListaNegraCNSF extends Model
{
       
    protected $table = 'tbloglistasnegracnsf';
    protected $primaryKey = 'IDLogRegistroListaCNSF';
    public $incrementing = false;
    protected $keyType = 'unsignedBigInteger';

    // Campos que se pueden llenar en masa
    protected $fillable = [
        'IDLogRegistroListaCNSF',
        'IDRegistroListaCNSF',
        'IDAccion', // 1 = update, 2 = delete, 3 = insert
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
