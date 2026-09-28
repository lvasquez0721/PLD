<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbAlertas extends Model
{
    protected $table = 'tbAlertas';

    protected $primaryKey = 'IDRegistroAlerta';

    public $incrementing = true; // Ahora el PK es autoincremental según la migración

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'Folio',
        'Patron',
        'IDCliente',
        'Cliente',
        'Poliza',
        'FechaDeteccion',
        // 'IDOperacionPago',
        'IDOperacion',
        'HoraDeteccion',
        'FechaOperacion',
        'HoraOperacion',
        'MontoOperacion',
        'InstrumentoMonetario',
        'RFCAgente',
        'Agente',
        'Estatus',
        'Descripcion',
        'Razones',
        'Evidencias',
        'IDReporteOP',
        'IDMoneda',
        // 'IDPago',
    ];

    public function operacionPago()
    {
        return $this->belongsTo(TbOperacionesPagos::class, 'IDOperacionPago', 'IDOperacionPago');
    }

    public function pagosAlertas()
    {
        return $this->hasMany(TbPagosAlertas::class, 'IDRegistroAlerta', 'IDRegistroAlerta');
    }

    /**
     * Nombre a guardar/mostrar en tbAlertas.Cliente.
     * Prioridad: RazonSocial (persona moral) sobre Nombre+Apellidos.
     */
    public static function nombreParaCliente($cliente): ?string
    {
        if (! $cliente) {
            return null;
        }
        $razon = trim((string) ($cliente->RazonSocial ?? ''));
        if ($razon !== '') {
            return $razon;
        }
        $nombre = trim(implode(' ', array_filter([
            $cliente->Nombre ?? null,
            $cliente->ApellidoPaterno ?? null,
            $cliente->ApellidoMaterno ?? null,
        ])));
        if ($nombre !== '') {
            return $nombre;
        }

        return $razon !== '' ? $razon : null;
    }
}
