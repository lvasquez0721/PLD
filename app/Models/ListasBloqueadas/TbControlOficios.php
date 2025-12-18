<?php

namespace App\Models\ListasBloqueadas;

use Illuminate\Database\Eloquent\Model;

class TbControlOficios extends Model
{
    protected $table = 'tbcontroloficios'; // nombre exacto de la tabla
    protected $primaryKey = 'IDRegistro';  // clave primaria
    public $timestamps = false; // no tiene created_at ni updated_at

    protected $fillable = [
        'IDListaN',
        'PathArchivo',
        'Archivo',
        'IDAccion',
        'TimeStampArchivo',
    ];

    protected $casts = [
        'TimeStampArchivo' => 'datetime',
    ];
}
