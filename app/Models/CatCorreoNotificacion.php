<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatCorreoNotificacion extends Model
{
    // Nombre de la tabla
    protected $table = 'catCorreoNotificaciones';

    // Clave primaria
    protected $primaryKey = 'IDCorreo';

    // Desactivar incremento automático para permitir definición manual,
    // pero aún permite autoincremento si no se proporciona el valor en el insert
    public $incrementing = true;

    // Permitir que la clave primaria pueda ser asignada manualmente si se requiere
    protected $fillable = [
        'IDCorreo',
        'Archivo',
        'Correo',
        'Nombre',
        'Activo',
    ];
}
