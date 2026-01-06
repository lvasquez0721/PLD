<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatCategoriaPersonasBloqueadas extends Model
{
    // Nombre de la tabla
    protected $table = 'catCategoriaPersonasBloqueadas';

    // Nombre de la clave primaria
    protected $primaryKey = 'ID';

    // Laravel no espera que el PK sea string ni tenga increment
    public $incrementing = true;
    protected $keyType = 'int';

    // No timestamps en la migración original
    public $timestamps = false;

    // Atributos asignables masivamente
    protected $fillable = [
        'IDCategoria',
        'Categoria',
        'Color',
    ];
}
