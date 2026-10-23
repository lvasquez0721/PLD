<?php

namespace App\Services\ListasNegras\Nombres;

class ComparadorNombres
{
    public function __construct(private NormalizadorNombre $normalizador) {}

    public function comparar(
        int $tipoPersona,
        ?string $nombre,
        ?string $apellidoPaterno,
        ?string $apellidoMaterno,
        ?string $razonSocial,
        string $nombreLista,
        float $umbralGlobal = 0.82,
        float $umbralCobertura = 0.78,
        float $umbralApellido = 0.85
    ): ?array {
        $datosCliente = $this->prepararCliente(
            $tipoPersona,
            $nombre,
            $apellidoPaterno,
            $apellidoMaterno,
            $razonSocial
        );

        if ($datosCliente === null) {
            return null;
        }

        $mejor = null;

        foreach ($this->normalizador->variantes($nombreLista) as $variante) {
            $tokensLista = $this->normalizador->tokens($variante);

            if (empty($tokensLista)) {
                continue;
            }

            $resultado = $this->evaluarVariante(
                $datosCliente,
                $tokensLista,
                $variante,
                $umbralGlobal,
                $umbralCobertura,
                $umbralApellido
            );

            if ($resultado !== null && ($mejor === null || $resultado['score'] > $mejor['score'])) {
                $mejor = $resultado;
            }
        }

        return $mejor;
    }

    private function prepararCliente(
        int $tipoPersona,
        ?string $nombre,
        ?string $apellidoPaterno,
        ?string $apellidoMaterno,
        ?string $razonSocial
    ): ?array {
        if ($tipoPersona === 1) {
            $campos = [
                'nombre' => $this->normalizador->tokens((string) $nombre),
                'apellidoPaterno' => $this->normalizador->tokens((string) $apellidoPaterno),
                'apellidoMaterno' => $this->normalizador->tokens((string) $apellidoMaterno),
            ];

            $pesos = [
                'nombre' => 0.35,
                'apellidoPaterno' => 0.45,
                'apellidoMaterno' => 0.20,
            ];

            $tokens = $this->construirTokensPonderados($campos, $pesos);

            if (! empty($tokens)) {
                return $this->construirDatosCliente($tipoPersona, $tokens, $campos);
            }
        }

        $tokensRazonSocial = $this->normalizador->tokens((string) $razonSocial);

        if (empty($tokensRazonSocial)) {
            $tokensRazonSocial = $this->normalizador->tokens(trim(
                ($nombre ?? '').' '.($apellidoPaterno ?? '').' '.($apellidoMaterno ?? '')
            ));
        }

        if (empty($tokensRazonSocial)) {
            return null;
        }

        $pesosBase = array_map(
            fn (string $token): int => max(3, mb_strlen($token)),
            $tokensRazonSocial
        );
        $totalPesos = array_sum($pesosBase);
        $tokens = [];

        foreach ($tokensRazonSocial as $indice => $token) {
            $tokens[] = [
                'token' => $token,
                'campo' => 'razonSocial',
                'peso' => $totalPesos > 0 ? $pesosBase[$indice] / $totalPesos : 0,
            ];
        }

        return $this->construirDatosCliente(
            $tipoPersona,
            $tokens,
            ['razonSocial' => $tokensRazonSocial]
        );
    }

    private function construirTokensPonderados(array $campos, array $pesos): array
    {
        $totalCampos = 0;

        foreach ($campos as $campo => $tokens) {
            if (! empty($tokens)) {
                $totalCampos += $pesos[$campo];
            }
        }

        if ($totalCampos <= 0) {
            return [];
        }

        $resultado = [];

        foreach ($campos as $campo => $tokens) {
            if (empty($tokens)) {
                continue;
            }

            $pesoCampo = $pesos[$campo] / $totalCampos;
            $pesoToken = $pesoCampo / count($tokens);

            foreach ($tokens as $token) {
                $resultado[] = [
                    'token' => $token,
                    'campo' => $campo,
                    'peso' => $pesoToken,
                ];
            }
        }

        return $resultado;
    }

    private function construirDatosCliente(int $tipoPersona, array $tokens, array $campos): array
    {
        $valores = array_map(fn (array $item): string => $item['token'], $tokens);
        $ordenados = $valores;
        sort($ordenados);

        return [
            'tipoPersona' => $tipoPersona,
            'tokens' => $tokens,
            'campos' => $campos,
            'texto' => implode(' ', $valores),
            'textoOrdenado' => implode(' ', $ordenados),
            'compacto' => $this->normalizador->compacto($valores),
            'fonetico' => implode('', $this->normalizador->clavesFoneticas($valores)),
        ];
    }

    private function evaluarVariante(
        array $datosCliente,
        array $tokensLista,
        string $variante,
        float $umbralGlobal,
        float $umbralCobertura,
        float $umbralApellido
    ): ?array {
        $matriz = [];

        foreach ($datosCliente['tokens'] as $indiceCliente => $tokenCliente) {
            foreach ($tokensLista as $indiceLista => $tokenLista) {
                $matriz[] = [
                    'cliente' => $indiceCliente,
                    'lista' => $indiceLista,
                    'score' => $this->compararToken($tokenCliente['token'], $tokenLista),
                ];
            }
        }

        $mejorPorLista = [];

        foreach ($matriz as $par) {
            $mejorPorLista[$par['lista']] = max($mejorPorLista[$par['lista']] ?? 0.0, $par['score']);
        }

        usort($matriz, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $clientesUsados = [];
        $listasUsadas = [];
        $asignados = [];

        foreach ($matriz as $par) {
            if (isset($clientesUsados[$par['cliente']]) || isset($listasUsadas[$par['lista']])) {
                continue;
            }

            $clientesUsados[$par['cliente']] = true;
            $listasUsadas[$par['lista']] = true;
            $asignados[$par['cliente']] = $par['score'];
        }

        $cobertura = 0.0;
        $pesoTotal = 0.0;
        $camposPonderados = [];
        $camposTotales = [];

        foreach ($datosCliente['tokens'] as $indice => $token) {
            $score = $asignados[$indice] ?? 0.0;
            $peso = $token['peso'];
            $cobertura += $score * $peso;
            $pesoTotal += $peso;

            $camposPonderados[$token['campo']] = ($camposPonderados[$token['campo']] ?? 0.0) + ($score * $peso);
            $camposTotales[$token['campo']] = ($camposTotales[$token['campo']] ?? 0.0) + $peso;
        }

        $cobertura = $pesoTotal > 0 ? $cobertura / $pesoTotal : 0.0;

        $pesoListaTotal = 0.0;
        $coberturaLista = 0.0;

        foreach ($tokensLista as $indiceLista => $tokenLista) {
            $peso = max(3, mb_strlen($tokenLista));
            $coberturaLista += ($mejorPorLista[$indiceLista] ?? 0.0) * $peso;
            $pesoListaTotal += $peso;
        }

        $coberturaLista = $pesoListaTotal > 0 ? $coberturaLista / $pesoListaTotal : 0.0;

        $camposScore = [];
        foreach ($camposPonderados as $campo => $valor) {
            $camposScore[$campo] = $camposTotales[$campo] > 0
                ? $valor / $camposTotales[$campo]
                : 0.0;
        }

        $tokensListaOrdenados = $tokensLista;
        sort($tokensListaOrdenados);

        $textoLista = implode(' ', $tokensLista);
        $textoListaOrdenado = implode(' ', $tokensListaOrdenados);
        $compactoLista = $this->normalizador->compacto($tokensLista);
        $foneticoLista = implode('', $this->normalizador->clavesFoneticas($tokensLista));

        $similitudNombre = max(
            $this->similitud($datosCliente['texto'], $textoLista),
            $this->similitud($datosCliente['textoOrdenado'], $textoListaOrdenado),
            $this->similitud($datosCliente['compacto'], $compactoLista),
            $this->similitud($datosCliente['fonetico'], $foneticoLista)
        );

        $score = (0.55 * $cobertura) + (0.30 * $coberturaLista) + (0.15 * $similitudNombre);

        if ($score < $umbralGlobal || $cobertura < $umbralCobertura) {
            return null;
        }

        if (! $this->cumpleReglasTipoPersona(
            $datosCliente,
            $tokensLista,
            $camposScore,
            $asignados,
            $coberturaLista,
            $score,
            $similitudNombre,
            $umbralGlobal,
            $umbralApellido
        )) {
            return null;
        }

        return [
            'score' => round($score, 4),
            'cobertura' => round($cobertura, 4),
            'coberturaLista' => round($coberturaLista, 4),
            'similitudNombre' => round($similitudNombre, 4),
            'campos' => array_map(fn (float $valor): float => round($valor, 4), $camposScore),
        ];
    }

    private function cumpleReglasTipoPersona(
        array $datosCliente,
        array $tokensLista,
        array $camposScore,
        array $asignados,
        float $coberturaLista,
        float $score,
        float $similitudNombre,
        float $umbralGlobal,
        float $umbralApellido
    ): bool {
        if ($coberturaLista < 0.50) {
            return false;
        }

        if ($datosCliente['tipoPersona'] === 1) {
            $paterno = $camposScore['apellidoPaterno'] ?? null;
            $materno = $camposScore['apellidoMaterno'] ?? null;

            if ($paterno !== null && $paterno < $umbralApellido) {
                return false;
            }

            if ($paterno === null && $materno !== null && $materno < $umbralApellido) {
                return false;
            }

            if ($paterno === null && $materno === null) {
                return $score >= max($umbralGlobal, 0.92) && $similitudNombre >= 0.90;
            }

            if ($paterno !== null && $materno !== null && count($tokensLista) >= 3) {
                if ($paterno < 0.70 || $materno < 0.70) {
                    return false;
                }
            }

            return true;
        }

        $tokensCoincidentes = 0;

        foreach ($asignados as $scoreToken) {
            if ($scoreToken >= 0.80) {
                $tokensCoincidentes++;
            }
        }

        $cantidadTokensCliente = count($datosCliente['tokens']);

        if ($cantidadTokensCliente >= 2 && $tokensCoincidentes < 2) {
            return false;
        }

        if ($cantidadTokensCliente === 1) {
            $unicoScore = $asignados[0] ?? 0.0;

            if ($unicoScore < 0.90) {
                return false;
            }
        }

        return $similitudNombre >= 0.80;
    }

    public function contarCoincidenciasTokens(array $tokensCliente, array $tokensLista, float $minimo = 0.78): int
    {
        $coincidencias = 0;

        foreach ($tokensCliente as $tokenCliente) {
            $mejor = 0.0;

            foreach ($tokensLista as $tokenLista) {
                $mejor = max($mejor, $this->compararToken($tokenCliente, $tokenLista));

                if ($mejor >= $minimo) {
                    break;
                }
            }

            if ($mejor >= $minimo) {
                $coincidencias++;
            }
        }

        return $coincidencias;
    }

    private function compararToken(string $tokenCliente, string $tokenLista): float
    {
        if ($tokenCliente === $tokenLista) {
            return 1.0;
        }

        $claveCliente = $this->normalizador->claveFonetica($tokenCliente);
        $claveLista = $this->normalizador->claveFonetica($tokenLista);

        if ($claveCliente !== '' && $claveCliente === $claveLista) {
            return 0.95;
        }

        $scoreTexto = $this->similitud($tokenCliente, $tokenLista);
        $scoreFonetico = mb_strlen($claveCliente) >= 3 && mb_strlen($claveLista) >= 3
            ? $this->similitud($claveCliente, $claveLista) * 0.95
            : 0.0;

        $score = max($scoreTexto, $scoreFonetico);
        $longitudMayor = max(mb_strlen($tokenCliente), mb_strlen($tokenLista));
        $longitudMenor = min(mb_strlen($tokenCliente), mb_strlen($tokenLista));

        if ($longitudMayor > 0 && ($longitudMenor / $longitudMayor) < 0.60) {
            $score *= 0.85;
        }

        return max(0.0, min(1.0, $score));
    }

    private function similitud(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        $a = substr($a, 0, 255);
        $b = substr($b, 0, 255);
        $longitud = max(strlen($a), strlen($b));

        if ($longitud === 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, 1 - (levenshtein($a, $b) / $longitud)));
    }
}
