<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ListasUIFController extends Controller
{
    public function index()
    {
        return Inertia::render('ListasUIF/Index');
    }

    public function altaListas(Request $request)
    {
        $datosCliente = $request->only([
            'nombre',
            'RFCCURP',
            'fechaNacimiento',
            'fechaPublicacionAcuerdo',
            'acuerdo',
            'noOficioUIF',
        ]);

        if (
            isset($datosCliente['nombre']) &&
            isset($datosCliente['RFCCURP']) &&
            isset($datosCliente['fechaNacimiento']) &&
            isset($datosCliente['fechaPublicacionAcuerdo']) &&
            isset($datosCliente['acuerdo']) &&
            isset($datosCliente['noOficioUIF'])
        ) {
            return response()->json([
                'message' => 'ok',
                'received' => $datosCliente,
            ], 200);
        }

        return response()->json([
            'message' => 'faltan campos',
            'received' => $datosCliente,
        ], 400);
    }

    public function bajaListas(Request $request)
    {
        $datosCliente = $request->only([
            'nombre',
            'RFCCURP',
            'fechaNacimiento',
            'fechaPublicacionAcuerdo',
            'acuerdo',
            'noOficioUIF',
        ]);

        if (
            isset($datosCliente['nombre']) &&
            isset($datosCliente['RFCCURP']) &&
            isset($datosCliente['fechaNacimiento']) &&
            isset($datosCliente['fechaPublicacionAcuerdo']) &&
            isset($datosCliente['acuerdo']) &&
            isset($datosCliente['noOficioUIF'])
        ) {
            return response()->json([
                'message' => 'ok',
                'received' => $datosCliente,
            ], 200);
        }

        return response()->json([
            'message' => 'faltan campos',
            'received' => $datosCliente,
        ], 400);
    }

    public function actualizaListas(Request $request)
    {
        $datosCliente = $request->only([
            'nombre',
            'RFCCURP',
            'fechaNacimiento',
            'fechaPublicacionAcuerdo',
            'acuerdo',
            'noOficioUIF',
        ]);

        if (
            isset($datosCliente['nombre']) &&
            isset($datosCliente['RFCCURP']) &&
            isset($datosCliente['fechaNacimiento']) &&
            isset($datosCliente['fechaPublicacionAcuerdo']) &&
            isset($datosCliente['acuerdo']) &&
            isset($datosCliente['noOficioUIF'])
        ) {
            return response()->json([
                'message' => 'ok',
                'received' => $datosCliente,
            ], 200);
        }

        return response()->json([
            'message' => 'faltan campos',
            'received' => $datosCliente,
        ], 400);
    }

    public function getConsultaListas(Request $request)
    {
        $datosCliente = $request->only([
            'nombre',
            'RFCCURP',
        ]);

        if (
            isset($datosCliente['nombre']) &&
            isset($datosCliente['RFCCURP'])
        ) {
            return response()->json([
                'message' => 'ok',
                'received' => $datosCliente,
            ], 200);
        }

        return response()->json([
            'message' => 'faltan campos',
            'received' => $datosCliente,
        ], 400);
    }
}
