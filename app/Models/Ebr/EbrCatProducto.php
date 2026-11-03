<?php

namespace App\Models\Ebr;

use Illuminate\Database\Eloquent\Model;

class EbrCatProducto extends Model
{
    protected $table = 'ebr_cat_productos';

    protected $fillable = ['producto', 'ramo', 'grupo', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
