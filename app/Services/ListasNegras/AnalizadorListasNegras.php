<?php

namespace App\Services\ListasNegras;

use App\Models\ListasBloqueadas\TbListasNegraCNSF;
use App\Models\ListasBloqueadas\TbListasNegrasUIF;
use App\Services\ListasNegras\Nombres\BuscadorNombresListas;
use App\Services\ParametriaPLD\ParametriaPLDService;

class AnalizadorListasNegras
{
    public function __construct(private BuscadorNombresListas $buscador) {}

    public function analizar(array $cliente, bool $esPPE = false): array
    {
        $rfc = strtoupper(trim((string) ($cliente['RFC'] ?? '')));
        $curp = strtoupper(trim((string) ($cliente['CURP'] ?? '')));
        $tipoPersona = (int) ($cliente['IDTipoPersona'] ?? 1);
        $nombre = $cliente['nombre'] ?? null;
        $apellidoPaterno = $cliente['apellidoPaterno'] ?? null;
        $apellidoMaterno = $cliente['apellidoMaterno'] ?? null;
        $razonSocial = $cliente['razonSocial'] ?? null;

        $umbralGlobal = $this->parametro('Porcentaje Match Nombres Listas', 82) / 100;
        $umbralCobertura = $this->parametro('Cobertura Minima Nombres Listas', 78) / 100;
        $umbralApellido = $this->parametro('Porcentaje Match Apellido Listas', 85) / 100;

        $listas = [
            [
                'fuente' => 'UIF',
                'modelo' => TbListasNegrasUIF::class,
                'campoId' => 'IDRegistroListaUIF',
            ],
            [
                'fuente' => 'CNSF',
                'modelo' => TbListasNegraCNSF::class,
                'campoId' => 'IDRegistroListaCNSF',
            ],
        ];

        $detalleListaBloqueadas = [];
        $listasDetectadas = [];

        foreach ($listas as $lista) {
            $metodo = null;
            $score = 1.0;
            $registro = $this->buscarExacto($lista['modelo'], $rfc, $curp, $metodo);

            if ($registro === null) {
                $resultado = $this->buscador->buscar(
                    $lista['modelo'],
                    $lista['campoId'],
                    $tipoPersona,
                    $nombre,
                    $apellidoPaterno,
                    $apellidoMaterno,
                    $razonSocial,
                    $umbralGlobal,
                    $umbralCobertura,
                    $umbralApellido
                );

                if ($resultado !== null) {
                    $registro = $resultado['registro'];
                    $metodo = 'NOMBRE';
                    $score = (float) $resultado['score'];
                }
            }

            if ($registro === null) {
                continue;
            }

            $detalleListaBloqueadas[] = $this->construirDetalle(
                $lista['fuente'],
                $lista['campoId'],
                $registro,
                (string) $metodo,
                $score,
                $esPPE
            );
            $listasDetectadas[] = $lista['fuente'];
        }

        return [
            'personaBloqueada' => ! empty($detalleListaBloqueadas),
            'listasDetectadas' => array_values(array_unique($listasDetectadas)),
            'detalleListaBloqueadas' => $detalleListaBloqueadas,
            'totalCoincidencias' => count($detalleListaBloqueadas),
        ];
    }

    private function buscarExacto(string $modelo, string $rfc, string $curp, ?string &$metodo): ?object
    {
        if ($rfc === '' && $curp === '') {
            return null;
        }

        $registro = $modelo::query()
            ->where(function ($query) use ($rfc, $curp): void {
                if ($rfc !== '') {
                    $query->orWhereRaw('UPPER(TRIM(RFC)) = ?', [$rfc]);
                }

                if ($curp !== '') {
                    $query->orWhereRaw('UPPER(TRIM(CURP)) = ?', [$curp]);
                }
            })
            ->first();

        if ($registro === null) {
            return null;
        }

        $metodo = $rfc !== '' && strtoupper(trim((string) ($registro->RFC ?? ''))) === $rfc
            ? 'RFC'
            : 'CURP';

        return $registro;
    }

    private function construirDetalle(
        string $fuente,
        string $campoId,
        object $registro,
        string $metodo,
        float $score,
        bool $esPPE
    ): array {
        $id = $registro->{$campoId} ?? null;
        $nombre = $registro->Nombre ?? '';

        return [
            'lista' => $fuente,
            'fuente' => $fuente,
            'nombreDetectado' => $nombre,
            'Nombre' => $nombre,
            'IDListaOrigen' => $id,
            $campoId => $id,
            'rfc' => $registro->RFC ?? '',
            'RFC' => $registro->RFC ?? '',
            'curp' => $registro->CURP ?? '',
            'CURP' => $registro->CURP ?? '',
            'cargo' => '',
            'PPEActivo' => $esPPE,
            'metodo' => $metodo,
            'coincidencia' => $metodo === 'NOMBRE' ? round($score * 100, 2) : 100,
        ];
    }

    private function parametro(string $nombre, float $default): float
    {
        $valor = ParametriaPLDService::get($nombre, $default);

        return is_numeric($valor) ? (float) $valor : $default;
    }
}
