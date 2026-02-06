<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatSistemas extends Model
{
    use HasFactory;

    protected $table = 'catSistemas';

    protected $primaryKey = 'IDSistema';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'IDSistema',
        'Sistema',
        'Activo',
    ];
}
