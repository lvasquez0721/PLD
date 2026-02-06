<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatListasNotificaQeQ extends Model
{
    protected $table = 'catListasNotificaQeQ';

    protected $primaryKey = 'IDLista';

    public $incrementing = true; // El PK puede ser autoincremental o definido en la query

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'IDLista',
        'Lista',
        'Tipo',
        'Activo',
    ];
}
