<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatOcupacionesGiros extends Model
{
    use HasFactory;

    protected $table = 'catOcupacionesGiros';

    protected $primaryKey = 'IDOcupacionGiro';

    public $incrementing = true; // IDOcupacionGiro es autoincremental según la migración

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        // 'IDOcupacionGiro', // NO incluimos campos autoincrementales en $fillable
        'CVE_GIRO',
        'OcupacionGiro',
        'NivelRiesgo',
    ];

    /**
     * Resuelve una ocupación/giro a partir del valor almacenado en tbClientes.
     *
     * Históricamente tbClientes.IDOcupacionGiro puede contener el CVE_GIRO
     * (datos importados/legacy) o el IDOcupacionGiro del catálogo. Se intenta
     * primero por CVE_GIRO y, como respaldo, por IDOcupacionGiro.
     */
    public static function buscarPorReferencia($referencia): ?self
    {
        $raw = trim((string) ($referencia ?? ''));

        if ($raw === '') {
            return null;
        }

        $porCve = self::where('CVE_GIRO', $raw)->first();

        if ($porCve) {
            return $porCve;
        }

        // tbClientes.IDOcupacionGiro es numérico, por lo que un CVE con ceros
        // a la izquierda pudo guardarse sin ellos (p. ej. 0100008 -> 100008).
        if (is_numeric($raw)) {
            $cveConCeros = str_pad($raw, 7, '0', STR_PAD_LEFT);

            if ($cveConCeros !== $raw) {
                $porCveConCeros = self::where('CVE_GIRO', $cveConCeros)->first();

                if ($porCveConCeros) {
                    return $porCveConCeros;
                }
            }
        }

        return self::where('IDOcupacionGiro', $raw)->first();
    }
}
