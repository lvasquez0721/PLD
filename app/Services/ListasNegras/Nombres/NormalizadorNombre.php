<?php

namespace App\Services\ListasNegras\Nombres;

class NormalizadorNombre
{
    private const REEMPLAZOS = [
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'Ñ' => 'N', 'Ç' => 'C',
    ];

    private const PALABRAS_VACIAS = [
        'DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'E', 'DA', 'DAS', 'DO', 'DOS',
        'VAN', 'VON', 'DER', 'DEN', 'EL', 'LO', 'A', 'AL', 'THE', 'OF', 'AND',
        'AKA', 'FKA',
    ];

    private const SUFIJOS_SOCIETARIOS = [
        'SA', 'S', 'RL', 'CV', 'SAPI', 'LTDA', 'SAB', 'SAS', 'SC', 'SRL',
    ];

    public function normalizar(string $valor): string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return '';
        }

        $valor = mb_strtoupper($valor, 'UTF-8');
        $valor = strtr($valor, self::REEMPLAZOS);
        $valor = preg_replace('/\b[AF]\.?\s*K\.?\s*A\.?(?=\s|$)/iu', ' ', $valor) ?? $valor;
        $valor = preg_replace('/\[[^\]]*\]/u', ' ', $valor) ?? $valor;
        $valor = preg_replace('/\([^)]*\)/u', ' ', $valor) ?? $valor;
        $valor = preg_replace('/[^A-Z0-9]+/u', ' ', $valor) ?? $valor;
        $valor = preg_replace('/\s+/u', ' ', $valor) ?? $valor;

        return trim($valor);
    }

    public function variantes(string $valor): array
    {
        $partes = preg_split('/\b[AF]\.?\s*K\.?\s*A\.?(?=\s|$)/iu', $valor) ?: [$valor];
        $variantes = [];

        foreach ($partes as $parte) {
            $normalizada = $this->normalizar($parte);

            if ($normalizada !== '') {
                $variantes[] = $normalizada;
            }
        }

        $completa = $this->normalizar($valor);
        if ($completa !== '') {
            $variantes[] = $completa;
        }

        return array_values(array_unique($variantes));
    }

    public function tokens(string $valor): array
    {
        $normalizado = $this->normalizar($valor);

        if ($normalizado === '') {
            return [];
        }

        $tokens = explode(' ', $normalizado);
        $tokens = array_filter($tokens, function (string $token): bool {
            return $token !== ''
                && ! in_array($token, self::PALABRAS_VACIAS, true)
                && ! in_array($token, self::SUFIJOS_SOCIETARIOS, true);
        });

        return array_values($tokens);
    }

    public function claveFonetica(string $token): string
    {
        $token = $this->normalizar($token);
        $token = preg_replace('/[^A-Z]/', '', $token) ?? '';

        if ($token === '') {
            return '';
        }

        $token = strtr($token, [
            'PH' => 'F',
            'QU' => 'K',
            'LL' => 'Y',
            'CH' => 'X',
            'V' => 'B',
            'Z' => 'S',
            'H' => '',
            'W' => 'U',
            'X' => 'S',
        ]);

        $token = preg_replace('/C([EI])/', 'S$1', $token) ?? $token;
        $token = str_replace('C', 'K', $token);
        $token = preg_replace('/G([EI])/', 'J$1', $token) ?? $token;
        $token = preg_replace('/GU([EI])/', 'G$1', $token) ?? $token;

        $primera = $token[0] ?? '';
        $resto = substr($token, 1);
        $resto = preg_replace('/[AEIOUY]/', '', $resto) ?? '';
        $token = $primera.$resto;
        $token = preg_replace('/(.)\1+/', '$1', $token) ?? $token;

        return $token;
    }

    public function compacto(array $tokens): string
    {
        return implode('', $tokens);
    }

    public function clavesFoneticas(array $tokens): array
    {
        return array_values(array_filter(array_map(
            fn (string $token): string => $this->claveFonetica($token),
            $tokens
        )));
    }
}
