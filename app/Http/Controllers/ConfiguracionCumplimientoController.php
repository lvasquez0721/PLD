<?php

namespace App\Http\Controllers;

use App\Models\CatCorreoNotificacion;
use App\Models\CatParametriaPLD;
use App\Services\NotificacionCumplimientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ConfiguracionCumplimientoController extends Controller
{
    public function index()
    {
        $config = CatCorreoNotificacion::where('Archivo', NotificacionCumplimientoService::ARCHIVO_OFICIAL)->first();

        return Inertia::render('ConfiguracionCumplimiento/Index', [
            'config' => [
                'correo' => $config->Correo ?? '',
                'nombre' => $config->Nombre ?? '',
                'activo' => $config ? (bool) $config->Activo : true,
                'entorno_desarrollo' => CatParametriaPLD::getEntornoDesarrolloActivo(),
            ],
        ]);
    }

    public function actualizar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'nombre' => 'nullable|string|max:255',
            'activo' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->route('configuracion-cumplimiento.index')
                ->with('toast', [
                    'message' => 'Verifica el correo ingresado.',
                    'type' => 'error',
                ]);
        }

        $config = CatCorreoNotificacion::where('Archivo', NotificacionCumplimientoService::ARCHIVO_OFICIAL)->first();

        $data = [
            'Correo' => $request->correo,
            'Nombre' => $request->nombre ?? '',
            'Activo' => $request->boolean('activo') ? 1 : 0,
        ];

        if ($config) {
            $config->update($data);
        } else {
            CatCorreoNotificacion::create(array_merge([
                'Archivo' => NotificacionCumplimientoService::ARCHIVO_OFICIAL,
            ], $data));
        }

        return redirect()->route('configuracion-cumplimiento.index')
            ->with('toast', [
                'message' => 'Correo del oficial de cumplimiento actualizado correctamente.',
                'type' => 'success',
            ]);
    }

    /**
     * Switch global de la etiqueta "Entorno de desarrollo".
     * Persiste en catParametriaPLD (ID 100) e invalida la caché compartida.
     */
    public function actualizarEntorno(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'activo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->route('configuracion-cumplimiento.index')
                ->with('toast', [
                    'message' => 'Valor inválido para la etiqueta de entorno.',
                    'type' => 'error',
                ]);
        }

        CatParametriaPLD::setEntornoDesarrolloActivo($request->boolean('activo'));
        Cache::forget('entorno_desarrollo_activo');

        return redirect()->route('configuracion-cumplimiento.index')
            ->with('toast', [
                'message' => $request->boolean('activo')
                    ? 'Etiqueta "Entorno de desarrollo" activada.'
                    : 'Etiqueta "Entorno de desarrollo" desactivada.',
                'type' => 'success',
            ]);
    }
}
