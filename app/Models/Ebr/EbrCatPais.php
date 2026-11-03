<?php

namespace App\Models\Ebr;

use Illuminate\Database\Eloquent\Model;

class EbrCatPais extends Model
{
    protected $table = 'ebr_cat_paises';

    protected $fillable = ['pais', 'score_bis', 'nivel', 'activo'];

    protected $casts = ['score_bis' => 'float', 'activo' => 'boolean'];
}
