<?php

namespace App\Services\ListasNegras\Nombres;

class BuscadorNombresListas
{
    public function __construct(
        private NormalizadorNombre $normalizador,
        private ComparadorNombres $comparador
    ) {}

    public function buscar(
        string $modelo,
        string $campoId,
        int $tipoPersona,
        ?string $nombre,
        ?string $apellidoPaterno,
        ?string $apellidoMaterno,
        ?string $razonSocial,
        float $umbralGlobal,
        float $umbralCobertura,
        float $umbralApellido
    ): ?array {
        $textoBusqueda = $tipoPersona === 1
            ? trim(($nombre ?? '').' '.($apellidoPaterno ?? '').' '.($apellidoMaterno ?? ''))
            : (string) $razonSocial;

        $tokens = $this->normalizador->tokens($textoBusqueda);

        if (empty($tokens)) {
            return null;
        }

        $patrones = array_values(array_unique(array_filter(
            $tokens,
            fn (string $token): bool => mb_strlen($token) >= 3
        )));

        $candidatos = $this->obtenerCandidatos($modelo, $campoId, $tokens, $patrones);

        $mejor = $this->evaluarCandidatos(
            $candidatos,
            $tipoPersona,
            $nombre,
            $apellidoPaterno,
            $apellidoMaterno,
            $razonSocial,
            $tokens,
            $umbralGlobal,
            $umbralCobertura,
            $umbralApellido
        );

        if ($mejor === null && count($tokens) === 1) {
            $mejor = $this->evaluarCandidatos(
                $this->consultar($modelo, $campoId)->get(),
                $tipoPersona,
                $nombre,
                $apellidoPaterno,
                $apellidoMaterno,
                $razonSocial,
                $tokens,
                $umbralGlobal,
                $umbralCobertura,
                $umbralApellido
            );
        }

        return $mejor;
    }

    private function obtenerCandidatos(
        string $modelo,
        string $campoId,
        array $tokens,
        array $patrones
    ) {
        $tokensFulltext = array_values(array_filter(
            $tokens,
            fn (string $token): bool => mb_strlen($token) >= 3
        ));

        $fulltextEjecutado = false;

        if (! empty($tokensFulltext)) {
            $ordenados = $tokensFulltext;
            usort($ordenados, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
            $estrictos = array_slice($ordenados, 0, min(2, count($ordenados)));
            $consultaEstricta = implode(' ', array_map(
                fn (string $token): string => '+'.$token,
                $estrictos
            ));

            try {
                $candidatos = $this->consultar($modelo, $campoId)
                    ->whereRaw('MATCH(Nombre) AGAINST (? IN BOOLEAN MODE)', [$consultaEstricta])
                    ->get();

                if ($candidatos->isNotEmpty()) {
                    return $candidatos;
                }

                $candidatos = $this->consultar($modelo, $campoId)
                    ->whereRaw('MATCH(Nombre) AGAINST (? IN BOOLEAN MODE)', [implode(' ', $tokensFulltext)])
                    ->get();

                if ($candidatos->isNotEmpty()) {
                    return $candidatos;
                }

                $fulltextEjecutado = true;
            } catch (\Throwable $e) {
            }
        }

        if (! $fulltextEjecutado && ! empty($patrones)) {
            $candidatos = $this->consultar($modelo, $campoId)
                ->where(function ($query) use ($patrones): void {
                    foreach ($patrones as $patron) {
                        $query->orWhereRaw(
                            "UPPER(REPLACE(REPLACE(Nombre, 'Ñ', 'N'), 'ñ', 'N')) LIKE ?",
                            ['%'.$patron.'%']
                        );
                    }
                })
                ->get();

            if ($candidatos->isNotEmpty()) {
                return $candidatos;
            }

            return collect();
        }

        if (empty($patrones)) {
            return $this->consultar($modelo, $campoId)->get();
        }

        return collect();
    }

    private function evaluarCandidatos(
        iterable $candidatos,
        int $tipoPersona,
        ?string $nombre,
        ?string $apellidoPaterno,
        ?string $apellidoMaterno,
        ?string $razonSocial,
        array $tokens,
        float $umbralGlobal,
        float $umbralCobertura,
        float $umbralApellido
    ): ?array {
        $mejor = null;
        $tokensRequeridos = count($tokens) >= 2 ? 2 : 1;

        foreach ($candidatos as $candidato) {
            $tokensCandidato = $this->normalizador->tokens((string) ($candidato->Nombre ?? ''));

            if (empty($tokensCandidato)) {
                continue;
            }

            if ($this->comparador->contarCoincidenciasTokens($tokens, $tokensCandidato) < $tokensRequeridos) {
                continue;
            }

            $resultado = $this->comparador->comparar(
                $tipoPersona,
                $nombre,
                $apellidoPaterno,
                $apellidoMaterno,
                $razonSocial,
                (string) ($candidato->Nombre ?? ''),
                $umbralGlobal,
                $umbralCobertura,
                $umbralApellido
            );

            if ($resultado === null) {
                continue;
            }

            if ($mejor === null || $resultado['score'] > $mejor['score']) {
                $mejor = $resultado + ['registro' => $candidato];
            }

            if ($resultado['score'] >= 0.9999) {
                break;
            }
        }

        return $mejor;
    }

    private function consultar(string $modelo, string $campoId)
    {
        return $modelo::query()->select($campoId, 'Nombre', 'RFC', 'CURP');
    }
}
