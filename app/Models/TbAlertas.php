<?php

namespace App\Models;

use App\Models\Clientes\TbClientes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

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
        'ActividadVulnerableUnica',
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
     * Clave única para alertas de Actividad vulnerable por identidad de persona.
     */
    public static function claveActividadVulnerable(TbClientes $cliente): string
    {
        return hash('sha256', TbClientes::claveIdentidadAlerta($cliente));
    }

    /**
     * Crea una alerta de nivel cliente solo si no existe otra del mismo patrón
     * para el mismo cliente o, en Actividad vulnerable, para la misma persona.
     * Evita duplicados en ediciones, reintentos y clientes repetidos.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function crearParaClienteSiNoExiste(array $attributes, ?TbClientes $cliente = null): self
    {
        $idCliente = $attributes['IDCliente'] ?? null;
        $patron = $attributes['Patron'] ?? null;

        if ($idCliente === null || $patron === null || trim((string) $patron) === '') {
            return static::create($attributes);
        }

        if ($cliente === null) {
            $cliente = TbClientes::find($idCliente);
        }

        $esActividadVulnerable = $cliente !== null
            && strcasecmp(trim((string) $patron), 'Actividad vulnerable') === 0;

        if ($esActividadVulnerable) {
            $clave = self::claveActividadVulnerable($cliente);
            $attributes['ActividadVulnerableUnica'] = $clave;

            $existente = static::query()
                ->where('ActividadVulnerableUnica', $clave)
                ->orderBy('IDRegistroAlerta')
                ->first();

            if ($existente) {
                return $existente;
            }

            try {
                return static::create($attributes);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062 || $e->getCode() === '23000') {
                    $existente = static::query()
                        ->where('ActividadVulnerableUnica', $clave)
                        ->orderBy('IDRegistroAlerta')
                        ->first();

                    if ($existente) {
                        return $existente;
                    }
                }

                throw $e;
            }
        }

        $existente = static::query()
            ->where('IDCliente', $idCliente)
            ->where('Patron', $patron)
            ->whereNull('IDOperacion')
            ->orderBy('IDRegistroAlerta')
            ->first();

        if ($existente) {
            return $existente;
        }

        return static::create($attributes);
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
