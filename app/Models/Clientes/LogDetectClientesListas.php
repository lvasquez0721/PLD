<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogDetectClientesListas extends Model
{
    use HasFactory;

    protected $table = 'logDetectClientesListas';

    protected $primaryKey = 'IDDeteccion';

    public $incrementing = true; // Debe ser autoincrementable según la migración

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        // 'IDDeteccion', // No incluir campo autoincrementable en $fillable
        'IDCliente',
        'Lista',
        'NombreDetectado',
        'Origen',
        'TimeStampDeteccion',
    ];
}
