<?php

namespace App\Models\ListasBloqueadas;

use Illuminate\Database\Eloquent\Model;

class LogListaNegraCNSF extends Model
{
    protected $table = 'loglistanegracnsf';

    protected $primaryKey = 'IDLogRegistroListaCNSF';

    public $incrementing = true;

    protected $keyType = 'int';

    // Campos que se pueden llenar en masa
    protected $fillable = [
        'IDLogRegistroListaCNSF',
        'IDRegistroListaCNSF',
        'IDAccion', // 1 = update, 2 = delete, 3 = insert
        'Nombre',
        'Direccion',
        'RFC',
        'CURP',
        'Observaciones',
        'Pais',
        'FechaNacimiento',
        'Acuerdo',
        'OficiosRelacionados',
        'UsuarioAlta',
        'TimeStampAlta',
        'UsuarioModif',
        'TimeStampModif',
    ];

    public $timestamps = true;

    protected $casts = [
        // FechaNacimiento es un varchar con datos heterogéneos (texto libre,
        // rangos, "sin info."); no se castea a fecha para evitar errores de Carbon.
        'TimeStampAlta' => 'datetime',
        'TimeStampModif' => 'datetime',
    ];
}
