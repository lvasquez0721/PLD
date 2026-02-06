<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatLocalidad extends Model
{
    use HasFactory;

    protected $table = 'catLocalidad';

    protected $primaryKey = 'IDLocalidad';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'IDLocalidad',
        'Localidad',
        'IDMunicipio',
    ];
}
