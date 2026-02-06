<?php

namespace App\Services\ListasUIF;

use Illuminate\Support\Facades\DB;

class ListasUIFService
{
    /**
     * Dar de alta cliente en listas UIF
     */
    public function altaListas(array $dataCliente): array
    {
        $query = DB::table('tbListasNegrasUIF')->insert([
            'Buscador' => $dataCliente['nombre'],
            'RFCCURP' => $dataCliente['RFCCURP'] ?? null,
            'FechaNac' => $dataCliente['fechaNacimiento'] ?? null,
            'FechaPubAcuerdo' => $dataCliente['fechaPublicacionAcuerdo'] ?? null,
            'Acuerdo' => $dataCliente['acuerdo'] ?? null,
            'NoOficioUIF' => $dataCliente['noOficioUIF'] ?? null,
            'AnioLista' => now()->year,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function bajaListas(array $dataCliente): array
    {
        $query = DB::table('tbListasNegrasUIF')
            ->where('Buscador', $dataCliente['nombre'])
            ->where('RFCCURP', $dataCliente['RFCCURP'] ?? null)
            ->where('FechaNac', $dataCliente['fechaNacimiento'] ?? null)
            ->where('FechaPubAcuerdo', $dataCliente['fechaPublicacionAcuerdo'] ?? null)
            ->where('Acuerdo', $dataCliente['acuerdo'] ?? null)
            ->where('NoOficioUIF', $dataCliente['noOficioUIF'] ?? null)
            ->where('AnioLista', now()->year)
            ->delete();

        return [
            'success' => $query > 0,
            'deleted_rows' => $query,
        ];
    }

    public function actualizaListas(array $dataCliente): array
    {
        $query = DB::table('tbListasNegrasUIF')
            ->where('Buscador', $dataCliente['nombre'])
            ->where('RFCCURP', $dataCliente['RFCCURP'] ?? null)
            ->update([
                'FechaNac' => $dataCliente['fechaNacimiento'] ?? null,
                'FechaPubAcuerdo' => $dataCliente['fechaPublicacionAcuerdo'] ?? null,
                'Acuerdo' => $dataCliente['acuerdo'] ?? null,
                'NoOficioUIF' => $dataCliente['noOficioUIF'] ?? null,
                'updated_at' => now(),
            ]);

        return [
            'success' => $query > 0,
            'updated_rows' => $query,
        ];
    }

    public function getConsultaListas(array $filtros): array
    {
        $filtroNombre = $filtros['nombre'] ?? null;
        $filtroRFCCURP = $filtros['RFCCURP'] ?? null;
        $query = DB::table('tbListasNegrasUIF')->select('*');
        if ($filtroNombre) {
            $query->where('Buscador', 'like', '%'.$filtroNombre.'%');
        }
        if ($filtroRFCCURP) {
            $query->where('RFCCURP', 'like', '%'.$filtroRFCCURP.'%');
        }

        return $query->get()->toArray();
    }
}
