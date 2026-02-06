<?php

namespace App\Services\ParametriaPLD;

use App\Models\CatParametriaPLD;

class ParametriaPLDService
{
    public static function get($parametro, $default = null)
    {
        return CatParametriaPLD::where('Parametro', $parametro)
            ->where('Activo', 1)
            ->value('Valor') ?? $default;
    }
}
