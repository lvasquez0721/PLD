<?php

namespace App\Http\Controllers;

use App\Models\CatCorreoNotificacion;
use App\Services\NotificacionCumplimientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConfiguracionCumplimientoApiController extends Controller
{
    public function getConfig()
    {
        $config = CatCorreoNotificacion::where('Archivo', NotificacionCumplimientoService::ARCHIVO_OFICIAL)->first();

        return response()->json([
            'codigoError' => 0,
            'config' => [
                'correo' => $config->Correo ?? '',
                'nombre' => $config->Nombre ?? '',
                'activo' => $config ? (bool) $config->Activo : true,
            ],
        ], 200);
    }

    public function actualizar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'nombre' => 'nullable|string|max:255',
            'activo' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'codigoError' => 1,
                'message' => 'Faltan datos obligatorios o el correo no es válido.',
                'errors' => $validator->errors(),
            ], 422);
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

        return response()->json([
            'codigoError' => 0,
            'message' => 'Correo del oficial de cumplimiento actualizado correctamente.',
            'config' => [
                'correo' => $request->correo,
                'nombre' => $request->nombre ?? '',
                'activo' => $request->boolean('activo'),
            ],
        ], 200);
    }
}
