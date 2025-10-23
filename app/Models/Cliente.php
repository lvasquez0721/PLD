<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    // Especifica la tabla asociada (opcional si sigue la convención)
    protected $table = 'clientes';

    // Especifica el nombre de la primary key si no es 'id'
    protected $primaryKey = 'id';

    // Define los campos que pueden ser asignados masivamente
    protected $fillable = [
        'IDSolicitante',
        'no_cliente',
        'nombre',
        'apellido_p',
        'apellido_m',
        'tipo_persona',
        'curp',
        'rfc',
        'ocupacion_giro',
        'estado_radica',
        'fecha_nacimiento',
        'edad',
        'calle',
        'no_exterior',
        'colonia',
        'cp',
        'id_estado',
        'id_municipio',
        'id_localidad',
    ];

    // Opcional: conversión de atributos, por ejemplo fechas
    protected $casts = [
        'fecha_nacimiento' => 'date',
        'edad' => 'integer',
        'id_estado' => 'integer',
        'id_municipio' => 'integer',
        'id_localidad' => 'integer',
    ];
}
