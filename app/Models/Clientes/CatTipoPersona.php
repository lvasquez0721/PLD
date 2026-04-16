<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatTipoPersona extends Model
{
    use HasFactory;

    protected $table = 'catTipoPersona';

    protected $primaryKey = 'IDTipoPersona';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'IDTipoPersona',
        'TipoPersona',
    ];

    // Relación con TbClientes
    public function clientes()
    {
        return $this->hasMany(TbClientes::class, 'IDTipoPersona', 'IDTipoPersona');
    }
}
