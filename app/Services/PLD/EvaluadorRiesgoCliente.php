<?php

namespace App\Services\PLD;

use App\Models\Clientes\CatEstados;
use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\TbClientes;
use Illuminate\Support\Facades\Log;

/**
 * Evalúa los factores que hacen que un cliente sea considerado de alto riesgo
 * y construye las alertas de nivel cliente correspondientes.
 *
 * Factores evaluados:
 *  - Actividad vulnerable: la ocupación/giro tiene NivelRiesgo = 3.
 *  - Zona geográfica: al menos un domicilio está en un estado con IndicePaz = 3.
 *  - Coincidencia en listas negras (CNSF / UIF).
 *  - Persona políticamente expuesta (PPE).
 */
class EvaluadorRiesgoCliente
{
    /** Patrón de alertamiento por ocupación vulnerable. */
    public const PATRON_ACTIVIDAD_VULNERABLE = 'Actividad vulnerable';

    /** Patrón de alertamiento por domicilio en zona de alto riesgo. */
    public const PATRON_ZONA_GEOGRAFICA = 'Zona geografica';

    /** Nivel de riesgo (catOcupacionesGiros.NivelRiesgo) que se considera vulnerable. */
    public const NIVEL_RIESGO_VULNERABLE = 3;

    /** Índice de paz (catEstados.IndicePaz) que se considera zona de alto riesgo. */
    public const INDICE_PAZ_ALTO = 3;

    /**
     * Evalúa la ocupación/giro del cliente.
     *
     * @param  int|null  $idOcupacionGiro  IDOcupacionGiro recibido en el JSON.
     * @return array{patron: string, descripcion: string, razones: string, factor: string}|null
     */
    public function evaluarOcupacion($idOcupacionGiro): ?array
    {
        if (empty($idOcupacionGiro)) {
            return null;
        }

        try {
            $ocupacion = CatOcupacionesGiros::find($idOcupacionGiro);
        } catch (\Throwable $e) {
            Log::warning('No se pudo leer catOcupacionesGiros para evaluar el riesgo del cliente: '.$e->getMessage());

            return null;
        }

        if (! $ocupacion) {
            return null;
        }

        if (! $this->esNivelVulnerable($ocupacion->NivelRiesgo)) {
            return null;
        }

        $nombreOcupacion = trim((string) ($ocupacion->OcupacionGiro ?? ''));
        $nombreOcupacion = $nombreOcupacion !== '' ? $nombreOcupacion : 'Ocupación '.$ocupacion->IDOcupacionGiro;

        $descripcion = 'Actividad vulnerable detectada: la ocupación/giro "'.$nombreOcupacion.'" tiene un nivel de riesgo '.self::NIVEL_RIESGO_VULNERABLE.'.';
        $razones = 'El cliente registra la ocupación/giro "'.$nombreOcupacion.'" (IDOcupacionGiro '.$ocupacion->IDOcupacionGiro.') con NivelRiesgo = '.self::NIVEL_RIESGO_VULNERABLE.', considerada actividad vulnerable.';

        return [
            'patron' => self::PATRON_ACTIVIDAD_VULNERABLE,
            'descripcion' => $descripcion,
            'razones' => $razones,
            'factor' => self::PATRON_ACTIVIDAD_VULNERABLE.': ocupación/giro "'.$nombreOcupacion.'" con nivel de riesgo alto.',
        ];
    }

    /**
     * Evalúa los domicilios del cliente buscando estados con IndicePaz = 3.
     *
     * @param  array<int, array<string, mixed>>  $domicilios  Domicilios con IDEstado ya resuelto.
     * @return array{patron: string, descripcion: string, razones: string, factor: string}|null
     */
    public function evaluarZonaGeografica(array $domicilios): ?array
    {
        if (empty($domicilios)) {
            return null;
        }

        $idsEstado = [];
        foreach ($domicilios as $domicilio) {
            $idEstado = $domicilio['IDEstado'] ?? null;
            if ($idEstado === null || $idEstado === '') {
                continue;
            }
            $idsEstado[] = (int) $idEstado;
        }

        $idsEstado = array_values(array_unique($idsEstado));
        if (empty($idsEstado)) {
            return null;
        }

        try {
            $estados = CatEstados::whereIn('IDEstado', $idsEstado)->get();
        } catch (\Throwable $e) {
            Log::warning('No se pudo leer catEstados para evaluar el riesgo del cliente: '.$e->getMessage());

            return null;
        }

        $estadosAltoRiesgo = [];
        foreach ($estados as $estado) {
            if ($this->esIndicePazAlto($estado->IndicePaz)) {
                $estadosAltoRiesgo[] = $estado;
            }
        }

        if (empty($estadosAltoRiesgo)) {
            return null;
        }

        $nombres = array_map(fn ($estado) => trim((string) ($estado->Estado ?? '')) ?: 'ID '.$estado->IDEstado, $estadosAltoRiesgo);
        $listaEstados = implode(', ', $nombres);

        return [
            'patron' => self::PATRON_ZONA_GEOGRAFICA,
            'descripcion' => 'Zona geografica detectada: el cliente tiene domicilio(s) en estados con IndicePaz = '.self::INDICE_PAZ_ALTO.' ('.$listaEstados.').',
            'razones' => 'El cliente tiene domicilio(s) registrado(s) en estados considerados de alto riesgo geográfico (IndicePaz = '.self::INDICE_PAZ_ALTO.'): '.$listaEstados.'.',
            'factor' => self::PATRON_ZONA_GEOGRAFICA.': domicilio en '.$listaEstados.' (IndicePaz = '.self::INDICE_PAZ_ALTO.').',
        ];
    }

    /**
     * Evalúa los factores de listas negras y PPE.
     *
     * @param  bool  $coincideEnListas  Coincide en listas negras CNSF / UIF.
     * @param  bool  $esPPE  Es persona políticamente expuesta.
     * @return array<int, string> Factores detectados.
     */
    public function evaluarListas(bool $coincideEnListas, bool $esPPE): array
    {
        $factores = [];

        if ($coincideEnListas) {
            $factores[] = 'Coincidencia en listas negras (CNSF / UIF).';
        }

        if ($esPPE) {
            $factores[] = 'Persona políticamente expuesta (PPE).';
        }

        return $factores;
    }

    /**
     * Evalúa todos los factores de alto riesgo de un cliente.
     *
     * @param  int|null  $idOcupacionGiro
     * @param  array<int, array<string, mixed>>  $domicilios
     * @return array{esAltoRiesgo: bool, factores: array<int, string>, alertas: array<int, array<string, string>>}
     */
    public function evaluar($idOcupacionGiro, array $domicilios, bool $coincideEnListas, bool $esPPE): array
    {
        $alertas = [];
        $factores = [];

        $ocupacion = $this->evaluarOcupacion($idOcupacionGiro);
        if ($ocupacion !== null) {
            $alertas[] = $ocupacion;
            $factores[] = $ocupacion['factor'];
        }

        $zona = $this->evaluarZonaGeografica($domicilios);
        if ($zona !== null) {
            $alertas[] = $zona;
            $factores[] = $zona['factor'];
        }

        foreach ($this->evaluarListas($coincideEnListas, $esPPE) as $factor) {
            $factores[] = $factor;
        }

        return [
            'esAltoRiesgo' => ! empty($factores),
            'factores' => $factores,
            'alertas' => $alertas,
        ];
    }

    /**
     * Persiste la marca de alto riesgo del cliente.
     */
    public function marcar(TbClientes $cliente, bool $esAltoRiesgo): void
    {
        $cliente->EsAltoRiesgo = $esAltoRiesgo;
        $cliente->save();
    }

    /**
     * Determina si un nivel de riesgo de catOcupacionesGiros es vulnerable.
     */
    private function esNivelVulnerable($nivelRiesgo): bool
    {
        if ($nivelRiesgo === null || $nivelRiesgo === '') {
            return false;
        }

        return (float) $nivelRiesgo === (float) self::NIVEL_RIESGO_VULNERABLE;
    }

    /**
     * Determina si un índice de paz de catEstados corresponde a zona de alto riesgo.
     */
    private function esIndicePazAlto($indicePaz): bool
    {
        if ($indicePaz === null || $indicePaz === '') {
            return false;
        }

        return (float) $indicePaz === (float) self::INDICE_PAZ_ALTO;
    }
}
