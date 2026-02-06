<?php

namespace App\Http\Controllers;

use App\Models\ListasBloqueadas\TbListasNegraCNSF;
use App\Models\ListasBloqueadas\TbListasNegrasUIF;
use Illuminate\Http\Request;

class ListasNegrasControllerApi extends Controller
{
    public function getConsultaListasByIDCliente(Request $request)
    {
        $id = $request->input('IDCliente');

        // Buscar cliente por su IDCliente
        $cliente = \App\Models\Clientes\TbClientes::find($id);

        if (! $cliente) {
            return response()->json([
                'error' => 'Cliente no encontrado',
                'message' => 'No se encontró un cliente con el ID proporcionado.',
            ], 404);
        }

        $rfc = $cliente->RFC;

        // Buscar en UIF solo por match exacto de RFC
        $listasUIF = TbListasNegrasUIF::query()
            ->where('RFC', $rfc)
            ->get();

        // Buscar en CNSF solo por match exacto de RFC
        $listasCNSF = TbListasNegraCNSF::query()
            ->where('RFC', $rfc)
            ->get();

        // Transformar resultados al formato solicitado
        $detalleListaBloqueadas = [];

        // Procesar resultados UIF
        foreach ($listasUIF as $uif) {
            $detalleListaBloqueadas[] = [
                'lista' => 'UIF',
                'nombreDetectado' => $uif->Nombre,
                'IDListaOrigen' => $uif->IDRegistroListaUIF,
                'cargo' => '',
                'PPEActivo' => true,
            ];
        }

        // Procesar resultados CNSF
        foreach ($listasCNSF as $cnsf) {
            $detalleListaBloqueadas[] = [
                'lista' => 'CNSF',
                'nombreDetectado' => $cnsf->Nombre,
                'IDListaOrigen' => $cnsf->IDRegistroListaCNSF,
                'cargo' => '',
                'PPEActivo' => true,
            ];
        }

        // Calcular total de registros encontrados
        $registrosEncontrados = count($detalleListaBloqueadas);

        // Retornar los resultados en el formato solicitado
        return response()->json([
            'registrosEncontrados' => $registrosEncontrados,
            'detalleListaBloqueadas' => $detalleListaBloqueadas,
        ], 200);
    }
}
