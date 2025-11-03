<?php

namespace App\Http\Controllers;

use App\Models\CatParametriaPLD;
use App\Models\ParametriaPLD;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ParametriaPLDController extends Controller
{
    public function index()
    {
        // Carga todos los parámetros activos
        $parametros = CatParametriaPLD::where('Activo', 1)
            ->pluck('Valor', 'Parametro')
            ->toArray();

        return Inertia::render('ParametriaPLD/Index', [
            'parametros' => [
                'operacionesRelevantes' => $parametros['Operaciones relevantes'] ?? '',
                'desviacionEstandarInusualidad' => $parametros['Desviacion Estandar Inusualidad'] ?? '',
                'aniosConsideradosInusualidad' => $parametros['Anios considerados Inusualidad'] ?? '',
                'montoMinimoAlerta' => $parametros['Monto minimo alerta'] ?? '',
                'toleranciaPagosFraccionados' => $parametros['Tolerancia Porcentaje Pagos Fraccionados'] ?? '',
                'riesgoAltoPerfil' => $parametros['Riesgo Alto Perfil'] ?? '',
                'reporteadorMontoAcumulado' => $parametros['Reporteador Monto Acumulado'] ?? '',
                'umbralBuscadorUIF' => $parametros['Porcentaje Match Buscador UIF'] ?? '',
                'umbralBuscadorCNSF' => $parametros['Porcentaje Match Buscador CNSF'] ?? '',
                'montoAutorizaPagoEfectivoPF' => $parametros['Monto Autorizacion Pago Efectivo PF'] ?? '',
                'montoAutorizaPagoEfectivoPM' => $parametros['Monto Autorizacion Pago Efectivo PM'] ?? '',
            ]
        ]);
    }

    public function actualizar(Request $request)
    {
        // Mapeo de claves del formulario a nombres exactos en BD
        $mapeoParametros = [
            'operacionesRelevantes' => 'Operaciones relevantes',
            'desviacionEstandarInusualidad' => 'Desviacion Estandar Inusualidad',
            'aniosConsideradosInusualidad' => 'Anios considerados Inusualidad',
            'montoMinimoAlerta' => 'Monto minimo alerta',
            'toleranciaPagosFraccionados' => 'Tolerancia Porcentaje Pagos Fraccionados',
            'riesgoAltoPerfil' => 'Riesgo Alto Perfil',
            'reporteadorMontoAcumulado' => 'Reporteador Monto Acumulado',
            'umbralBuscadorUIF' => 'Porcentaje Match Buscador UIF',
            'umbralBuscadorCNSF' => 'Porcentaje Match Buscador CNSF',
            'montoAutorizaPagoEfectivoPF' => 'Monto Autorizacion Pago Efectivo PF',
            'montoAutorizaPagoEfectivoPM' => 'Monto Autorizacion Pago Efectivo PM',
        ];

        $data = $request->all();
        $actualizados = 0;

        foreach ($data as $clave => $valor) {
            // Verifica si la clave existe en el mapeo
            if (isset($mapeoParametros[$clave])) {
                $nombreParametro = $mapeoParametros[$clave];

                // Actualiza el parámetro específico
                $updated = CatParametriaPLD::where('Parametro', $nombreParametro)
                    ->where('Activo', 1)
                    ->update(['Valor' => $valor]);

                if ($updated) {
                    $actualizados++;
                    Log::info("Parámetro actualizado: {$nombreParametro} = {$valor}");
                }
            }
        }

        Log::info("Total de parámetros actualizados: {$actualizados}");

        return redirect()->route('parametria-pld.index')
            ->with('toast', [
                'message' => "Parámetros actualizados correctamente ({$actualizados} campos)",
                'type' => 'success'
            ]);
    }
}
