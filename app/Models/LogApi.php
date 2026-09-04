<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogApi extends Model
{
    protected $table = 'logApi';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'IDUsuario',
        'Usuario',
        'Metodo',
        'Ruta',
        'URL',
        'IP',
        'UserAgent',
        'Estatus',
        'DuracionMs',
        'Payload',
        'Respuesta',
    ];

    protected $casts = [
        'IDUsuario' => 'integer',
        'Estatus' => 'integer',
        'DuracionMs' => 'float',
    ];
}
