<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogListaNegraCnsf extends Model
{
    use HasFactory;

    protected $table = 'loglistanegracnsf';

    protected $primaryKey = 'IDLogRegistroListaCNSF';

    protected $fillable = [
        'IDRegistroListaCNSF',
        'IDAccion',
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
}
