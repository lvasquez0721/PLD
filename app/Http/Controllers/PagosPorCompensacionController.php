<?php

namespace App\Http\Controllers;

use App\Models\PagoPorCompensación;
use Illuminate\Http\Request;

class PagosPorCompensacionController extends Controller
{
    /**
     * Inserción masiva de pagos por compensación.
     * POST /api/pagosporcompensacion/masivo
     */
    public function storeMasivo(Request $request)
    {
        $datos = $request->input('pagosPorCompensacion');

        if (! is_array($datos) || empty($datos)) {
            return response()->json([
                'message' => 'El parámetro "pagosPorCompensacion" debe ser un arreglo de objetos de pago.',
            ], 400);
        }

        // Reglas mínimas de validación (ajusta según tus necesidades)
        $reglas = [
            'Poliza' => 'required',
            'Fecha_Pago' => 'required',
            'Importe_pago' => 'required',
            // Puedes agregar más campos requeridos según tu modelo
        ];

        $errores = [];
        $registrosAInsertar = [];

        foreach ($datos as $i => $item) {
            foreach ($reglas as $campo => $regla) {
                if (strpos($regla, 'required') !== false && (! isset($item[$campo]) || $item[$campo] === null || $item[$campo] === '')) {
                    $errores[$i][] = "El campo $campo es obligatorio.";
                }
            }
            if (isset($errores[$i])) {
                continue;
            }
            $registrosAInsertar[] = $item;
        }

        if (! empty($errores)) {
            return response()->json([
                'message' => 'Algunos objetos no cumplen los criterios de validación.',
                'errores' => $errores,
            ], 422);
        }

        try {
            PagoPorCompensación::insert($registrosAInsertar);

            return response()->json([
                'message' => 'Pagos por compensación insertados correctamente.',
                'insertados' => $registrosAInsertar,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al insertar los pagos por compensación: '.$e->getMessage(),
            ], 500);
        }
    }
}
