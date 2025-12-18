<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatCampos extends Model
{
    protected $table = 'catcampos'; // Nombre exacto de la tabla
    protected $primaryKey = 'IDCampo'; // Clave primaria
    public $timestamps = false; // No tiene created_at ni updated_at

    protected $fillable = [
        'IDModulo',
        'Seccion',
        'Tipo',
        'IDSubPerfil',
        'NombreCampo',
        'Longitud',
        'Placeholder',
        'Clase',
        'Columnas',
        'EtiquetaCampo',
        'idname',
        'Orden',
        'Requerido',
        'Visible',
        'Value',
        'JQuery',
    ];
}
