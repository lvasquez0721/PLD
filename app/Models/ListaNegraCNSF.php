<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaNegraCNSF extends Model
{
    protected $table = 'tbListaNegraCNSF';

    protected $fillable = [
        'Nombres',
        'Direccion',
        'Empresa',
        'Cedula',
        'Pasaporte',
        'NIT',
        'IFE',
        'RFC',
        'CURP',
        'Pais',
        'FechaNacimiento',
        'Usuario',
        'TimeStampAlta',
        'TimeStampModif',
    ];

    protected $casts = [
        'FechaNacimiento' => 'date',
        'TimeStampAlta' => 'datetime',
        'TimeStampModif' => 'datetime',
    ];
}
