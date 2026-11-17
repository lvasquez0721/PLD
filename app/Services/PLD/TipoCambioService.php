<?php

namespace App\Services\PLD;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Obtiene y resuelve el tipo de cambio FIX de Banxico usado para convertir
 * operaciones a moneda nacional conforme a la regla del DOF:
 *
 * Para una operación del día D se usa la FIX publicada en el DOF el día hábil
 * bancario inmediato anterior. Como Banxico publica un día hábil después de la
 * fecha de determinación, esa FIX corresponde a la determinada dos días hábiles
 * antes de D.
 *
 * `catTipoCambio` almacena la serie SF43718 (fecha de determinación), por lo
 * que la regla se implementa tomando la SEGUNDA fila más reciente con
 * Fecha < fecha de la operación.
 */
class TipoCambioService
{
    /** Serie Banxico: tipo de cambio FIX (fecha de determinación). */
    private const SERIE_FIX = 'SF43718';

    private const BANXICO_BASE_URL = 'https://www.banxico.org.mx/SieAPIRest/service/v1/series';

    private const DIAS_SINCRONIZACION_DEFAULT = 7;

    private const DIAS_BUSQUEDA_FALLBACK = 30;

    /** @var array<string, array{fecha_operacion: string, fecha_determinacion: string, tipo_cambio: float}|null> */
    private array $cacheTipoCambioOperacion = [];

    /**
     * Sincroniza el FIX de Banxico en catTipoCambio para un rango reciente.
     *
     * @return array{ok: bool, mensaje: string, registros: array<int, array{fecha: string, dato: float, estado: string}>}
     */
    public function sincronizar(?string $hasta = null, int $dias = self::DIAS_SINCRONIZACION_DEFAULT): array
    {
        $token = config('services.banxico.token');

        if (empty($token)) {
            $mensaje = 'No existe BANXICO_TOKEN configurado; no se puede actualizar catTipoCambio.';
            Log::error('[TipoCambio] '.$mensaje);

            return ['ok' => false, 'mensaje' => $mensaje, 'registros' => []];
        }

        try {
            $fechaHasta = $this->normalizarFecha($hasta);
            $fechaDesde = Carbon::parse($fechaHasta)->subDays(max(1, $dias))->toDateString();
        } catch (\Throwable $e) {
            $mensaje = 'Fecha inválida para sincronizar el tipo de cambio: '.(string) $hasta;
            Log::error('[TipoCambio] '.$mensaje);

            return ['ok' => false, 'mensaje' => $mensaje, 'registros' => []];
        }

        $url = self::BANXICO_BASE_URL.'/'.self::SERIE_FIX.'/datos/'.$fechaDesde.'/'.$fechaHasta;

        try {
            $response = Http::timeout(20)->retry(3, 1000)->get($url, ['token' => $token]);

            if (! $response->successful()) {
                $mensaje = 'Banxico respondió HTTP '.$response->status().' al consultar el FIX.';
                Log::error('[TipoCambio] '.$mensaje, [
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                ]);

                return ['ok' => false, 'mensaje' => $mensaje, 'registros' => []];
            }

            $datos = $response->json('bmx.series.0.datos');

            if (! is_array($datos)) {
                $mensaje = 'Respuesta inesperada de Banxico al consultar el FIX.';
                Log::error('[TipoCambio] '.$mensaje, [
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                ]);

                return ['ok' => false, 'mensaje' => $mensaje, 'registros' => []];
            }

            if ($datos === []) {
                $mensaje = "Banxico no devolvió FIX para el rango {$fechaDesde} a {$fechaHasta}.";
                Log::info('[TipoCambio] '.$mensaje);

                return ['ok' => true, 'mensaje' => $mensaje, 'registros' => []];
            }

            $registros = [];

            DB::transaction(function () use ($datos, &$registros) {
                foreach ($datos as $dato) {
                    $fechaBanxico = $dato['fecha'] ?? null;
                    $valor = $dato['dato'] ?? null;

                    if (! is_numeric($valor) || (float) $valor <= 0) {
                        continue;
                    }

                    try {
                        $fecha = Carbon::createFromFormat('d/m/Y', (string) $fechaBanxico);
                    } catch (\Throwable $e) {
                        Log::warning('[TipoCambio] Fecha inválida devuelta por Banxico: '.$fechaBanxico);

                        continue;
                    }

                    if (! $fecha) {
                        continue;
                    }

                    $fechaSql = $fecha->format('Y-m-d');
                    $existia = DB::table('catTipoCambio')->where('Fecha', $fechaSql)->exists();

                    DB::table('catTipoCambio')->updateOrInsert(
                        ['Fecha' => $fechaSql],
                        ['DOFDolar' => trim((string) $valor)]
                    );

                    $registros[] = [
                        'fecha' => $fechaSql,
                        'dato' => (float) $valor,
                        'estado' => $existia ? 'actualizado' : 'insertado',
                    ];
                }
            });

            $mensaje = $registros === []
                ? "Banxico no devolvió datos válidos para el rango {$fechaDesde} a {$fechaHasta}."
                : 'Sincronización de tipo de cambio completada ('.count($registros).' registro(s)).';

            return ['ok' => true, 'mensaje' => $mensaje, 'registros' => $registros];
        } catch (\Throwable $e) {
            $mensaje = 'Error al consultar Banxico o guardar catTipoCambio: '.$e->getMessage();
            Log::error('[TipoCambio] '.$mensaje, [
                'desde' => $fechaDesde,
                'hasta' => $fechaHasta,
            ]);

            return ['ok' => false, 'mensaje' => $mensaje, 'registros' => []];
        }
    }

    /**
     * Devuelve la FIX aplicable a una operación conforme al DOF: la determinada
     * dos días hábiles antes (publicada el día hábil anterior).
     */
    public function obtenerTipoCambioParaOperacion($fechaOperacion = null): ?float
    {
        $detalle = $this->obtenerDetalleTipoCambioParaOperacion($fechaOperacion);

        return $detalle['tipo_cambio'] ?? null;
    }

    /**
     * Devuelve la FIX aplicable junto con la fecha de determinación usada.
     *
     * @return array{fecha_operacion: string, fecha_determinacion: string, tipo_cambio: float}|null
     */
    public function obtenerDetalleTipoCambioParaOperacion($fechaOperacion = null): ?array
    {
        $fecha = $this->normalizarFecha($fechaOperacion);

        if (array_key_exists($fecha, $this->cacheTipoCambioOperacion)) {
            return $this->cacheTipoCambioOperacion[$fecha];
        }

        $detalle = $this->buscarTipoCambioLocal($fecha, true);

        if ($detalle === null) {
            // Si la tabla local no tiene el historial completo, se hace backfill
            // desde Banxico con un rango que nunca incluye la fecha de la operación.
            $hastaFallback = Carbon::parse($fecha)->subDay()->toDateString();
            $resultado = $this->sincronizar($hastaFallback, self::DIAS_BUSQUEDA_FALLBACK);

            if (! $resultado['ok']) {
                Log::warning('[TipoCambio] No se pudo completar el historial para '.$fecha.': '.$resultado['mensaje']);
            } else {
                $detalle = $this->buscarTipoCambioLocal($fecha, false);
            }
        }

        if ($detalle === null) {
            Log::error('[TipoCambio] Sin FIX aplicable para la operación del '.$fecha.'. No se evalúa la conversión.');
        } else {
            $detalle['fecha_operacion'] = $fecha;
        }

        $this->cacheTipoCambioOperacion[$fecha] = $detalle;

        return $detalle;
    }

    public function convertirAUSD(float $monto, $idMoneda, ?float $tipoCambio = null, $fechaOperacion = null): ?float
    {
        $moneda = strtoupper(trim((string) $idMoneda));

        if ($moneda === 'USD') {
            return $monto;
        }

        if ($moneda === 'MXN') {
            $tc = $tipoCambio ?? $this->obtenerTipoCambioParaOperacion($fechaOperacion);

            return ($tc !== null && $tc > 0) ? $monto / $tc : null;
        }

        Log::error('[TipoCambio] Moneda no soportada para convertir a USD: '.$moneda);

        return null;
    }

    public function convertirAMXN(float $monto, $idMoneda, ?float $tipoCambio = null, $fechaOperacion = null): ?float
    {
        $moneda = strtoupper(trim((string) $idMoneda));

        if ($moneda === 'MXN') {
            return $monto;
        }

        if ($moneda === 'USD') {
            $tc = $tipoCambio ?? $this->obtenerTipoCambioParaOperacion($fechaOperacion);

            return ($tc !== null && $tc > 0) ? $monto * $tc : null;
        }

        Log::error('[TipoCambio] Moneda no soportada para convertir a MXN: '.$moneda);

        return null;
    }

    /**
     * Segunda fila más reciente con Fecha < fecha de la operación.
     *
     * Cuando $exigirFechasEsperadas es true se valida que las dos filas
     * correspondan a los dos días hábiles anteriores (según calendario de fin
     * de semana). Si no coinciden, se asume historial incompleto y se fuerza
     * el backfill desde Banxico.
     *
     * @return array{fecha_determinacion: string, tipo_cambio: float}|null
     */
    private function buscarTipoCambioLocal(string $fechaOperacion, bool $exigirFechasEsperadas): ?array
    {
        try {
            $filas = DB::table('catTipoCambio')
                ->where('Fecha', '<', $fechaOperacion)
                ->orderByDesc('Fecha')
                ->limit(2)
                ->get(['Fecha', 'DOFDolar']);
        } catch (\Throwable $e) {
            Log::error('[TipoCambio] No se pudo leer catTipoCambio: '.$e->getMessage());

            return null;
        }

        if ($filas->count() < 2) {
            return null;
        }

        if ($exigirFechasEsperadas) {
            $esperadaAnterior = $this->fechaHabilAnterior($fechaOperacion);
            $esperadaDosAnterior = $this->fechaHabilAnterior($esperadaAnterior);

            if ($filas[0]->Fecha !== $esperadaAnterior || $filas[1]->Fecha !== $esperadaDosAnterior) {
                Log::info('[TipoCambio] Historial local incompleto para '.$fechaOperacion.'. Se requiere backfill.');

                return null;
            }
        }

        // Evita usar datos demasiado antiguos para fechas futuras o con gaps.
        $diasDesfase = Carbon::parse($filas[1]->Fecha)->diffInDays(Carbon::parse($fechaOperacion));

        if ($diasDesfase > 15) {
            Log::warning('[TipoCambio] La FIX aplicable para '.$fechaOperacion.' es demasiado antigua ('.$filas[1]->Fecha.').');

            return null;
        }

        $dato = $filas[1]->DOFDolar;

        if (! is_numeric($dato) || (float) $dato <= 0) {
            Log::error('[TipoCambio] El valor de catTipoCambio no es válido para '.$filas[1]->Fecha.': '.$dato);

            return null;
        }

        return [
            'fecha_determinacion' => $filas[1]->Fecha,
            'tipo_cambio' => (float) $dato,
        ];
    }

    /**
     * Día hábil anterior considerando únicamente fines de semana. Los días
     * inhábiles oficiales se resuelven con los datos reales de Banxico.
     */
    private function fechaHabilAnterior(string $fecha): string
    {
        $dia = Carbon::parse($fecha)->subDay();

        while ($dia->isWeekend()) {
            $dia->subDay();
        }

        return $dia->toDateString();
    }

    private function normalizarFecha($fecha): string
    {
        if (empty($fecha)) {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($fecha)->toDateString();
        } catch (\Throwable $e) {
            Log::warning('[TipoCambio] Fecha inválida, se usará la fecha actual: '.(string) $fecha);

            return now()->toDateString();
        }
    }
}
