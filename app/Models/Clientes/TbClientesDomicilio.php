<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Clientes\CatEstados;
use App\Models\Clientes\CatMunicipio;
use App\Models\Clientes\CatLocalidad;

class TbClientesDomicilio extends Model
{
    use HasFactory;

    protected $table = 'tbClientesDomicilio';
    protected $primaryKey = 'IDDomicilio';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDCliente',
        'Calle',
        'NoExterior',
        'NoInterior',
        'Colonia',
        'CP',
        'IDEstado',
        'IDMunicipio',
        'IDLocalidad',
        'Telefono',
    ];

    // Relaciones con catEstados, catMunicipio y catLocalidad
    public function estado()
    {
        return $this->belongsTo(CatEstados::class, 'IDEstado', 'IDEstado');
    }

    public function municipio()
    {
        return $this->belongsTo(CatMunicipio::class, 'IDMunicipio', 'IDMunicipio');
    }

    public function localidad()
    {
        return $this->belongsTo(CatLocalidad::class, 'IDLocalidad', 'IDLocalidad');
    }
}
