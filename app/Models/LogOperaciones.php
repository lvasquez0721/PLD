<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogOperaciones extends Model
{
    protected $table = 'logOperaciones';

    // La migración NO define PK autoincremental,
    // solo hay un 'IDOperacion' unsigned big int (NO autoincrement!)
    protected $primaryKey = 'IDOperacion';

    public $incrementing = false; // No autoincrement. Se debe asignar manualmente si aplica.

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'IDOperacion',
        'IDCliente',
        'IDMoneda',
        'FechaInicioVigencia',
        'FechaFinVigencia',
        'RazonSocialAgente',
        'FolioPoliza',
        'FolioEndoso',
        'FechaEmision',
        'PrimaTotal',
        'GastosEmision',
        'RFCAgente',
        'CURPAgente',
        'NombreAgente',
        'APaternoAgente',
        'AMaternoAgente',
        'tipoDocumento',
        'cancelaPoliza',
    ];
}
