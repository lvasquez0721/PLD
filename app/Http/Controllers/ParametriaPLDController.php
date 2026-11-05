<?php

namespace App\Http\Controllers;

use App\Models\CatParametriaPLD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ParametriaPLDController extends Controller
{
    public function index()
    {
        $this->autorizarAcceso();

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
            ],
        ]);
    }

    public function actualizar(Request $request)
    {
        $this->autorizarAcceso();

        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('parametria-pld.index')
                ->withErrors($validator)
                ->with('toast', [
                    'message' => 'Ingresa el token de autorización para guardar los cambios.',
                    'type' => 'error',
                ]);
        }

        $tokenConfigurado = (string) config('services.parametria.token');

        if ($tokenConfigurado === '' || ! hash_equals($tokenConfigurado, (string) $request->input('token'))) {
            return redirect()->route('parametria-pld.index')
                ->withErrors(['token' => 'Token de autorización inválido.'])
                ->with('toast', [
                    'message' => 'Token de autorización inválido.',
                    'type' => 'error',
                ]);
        }

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
            if (isset($mapeoParametros[$clave])) {
                $nombreParametro = $mapeoParametros[$clave];

                $updated = CatParametriaPLD::where('Parametro', $nombreParametro)
                    ->where('Activo', 1)
                    ->update(['Valor' => $valor]);

                if ($updated) {
                    $actualizados++;
                }
            }
        }

        return redirect()->route('parametria-pld.index')
            ->with('toast', [
                'message' => "Parámetros actualizados correctamente ({$actualizados} campos)",
                'type' => 'success',
            ]);
    }

    /**
     * Solo el Oficial de cumplimiento y el Administrador pueden acceder.
     */
    private function autorizarAcceso(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['Administrador', 'Oficial de cumplimiento']),
            403,
            'No tienes permiso para acceder a Parametría PLD.'
        );
    }
}
