<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatTipoOperacion extends Model
{
    protected $table = 'catTipoOperacion';

    protected $primaryKey = 'IDTipoOperacion';

    public $incrementing = false;

    protected $keyType = 'unsignedBigInteger';

    protected $fillable = [
        'IDTipoOperacion',
        'TipoOperacion',
    ];
}
