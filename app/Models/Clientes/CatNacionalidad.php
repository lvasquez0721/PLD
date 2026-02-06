<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatNacionalidad extends Model
{
    use HasFactory;

    protected $table = 'catNacionalidad';

    protected $primaryKey = 'IDNacionalidad';

    public $incrementing = false;

    protected $keyType = 'string'; // Cambiado de 'int' a 'string' acorde al tipo de la columna

    public $timestamps = true;

    protected $fillable = [
        'IDNacionalidad',
        'Nacionalidad',
    ];
}
