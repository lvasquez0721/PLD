<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogDetectClientesListas extends Model
{
    use HasFactory;

    protected $table = 'logDetectClientesListas';
    protected $primaryKey = 'IDDeteccion';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDDeteccion',
        'IDCliente',
        'Lista',
        'NombreDetectado',
        'Origen',
        'TimeStampDeteccion',
    ];
}
