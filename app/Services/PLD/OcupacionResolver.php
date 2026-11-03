<?php

namespace App\Services\PLD;

use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\TbClientes;

/**
 * Resuelve la clave de ocupación/giro (CVE_GIRO) que se exporta en los
 * reportes regulatorios, garantizando un valor para los reportes "Nuevo".
 */
class OcupacionResolver
{
    public const CVE_AGENTE = '8219017';

    public const NOMBRE_AGENTE = 'Agente de seguros';

    public const CVE_GENERICO = '9501009';

    public const NOMBRE_GENERICO = 'Empleado del sector privado';

    /** @var array<string, string> */
    private array $cacheNombres = [];

    /**
     * Obtiene la ocupación de la persona o, si no está capturada, un valor
     * por defecto según su rol. Nunca devuelve una cadena vacía.
     */
    public function resolverDesdePersona(?TbClientes $persona): string
    {
        $cve = $this->desdeIdOcupacion($persona?->IDOcupacionGiro);
        if ($cve !== '') {
            return $cve;
        }

        if ($this->esAgente($persona)) {
            return $this->desdeNombre(self::NOMBRE_AGENTE) ?: self::CVE_AGENTE;
        }

        return $this->desdeNombre(self::NOMBRE_GENERICO) ?: self::CVE_GENERICO;
    }

    /**
     * Resuelve la clave a partir del ID del catálogo. Si el ID no existe pero
     * es numérico, se conserva como clave con longitud de 7 dígitos.
     */
    public function desdeIdOcupacion($idOcupacion): string
    {
        $raw = trim((string) ($idOcupacion ?? ''));
        if ($raw === '') {
            return '';
        }

        $cve = CatOcupacionesGiros::where('IDOcupacionGiro', $raw)->value('CVE_GIRO');
        if ($cve !== null && trim((string) $cve) !== '') {
            return trim((string) $cve);
        }

        return is_numeric($raw) ? str_pad($raw, 7, '0', STR_PAD_LEFT) : '';
    }

    /**
     * Busca una ocupación por nombre (coincidencia por prefijo).
     */
    public function desdeNombre(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return '';
        }

        if (array_key_exists($nombre, $this->cacheNombres)) {
            return $this->cacheNombres[$nombre];
        }

        $cve = CatOcupacionesGiros::where('OcupacionGiro', 'like', $nombre.'%')->value('CVE_GIRO');

        return $this->cacheNombres[$nombre] = trim((string) ($cve ?? ''));
    }

    private function esAgente(?TbClientes $persona): bool
    {
        if (! $persona) {
            return false;
        }

        return $persona->TipoPersona === TbClientes::TIPO_AGENTE
            || (int) ($persona->ckAgente ?? 0) === 1;
    }
}
