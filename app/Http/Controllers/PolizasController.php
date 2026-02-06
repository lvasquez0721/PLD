<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PolizasController extends Controller
{
    //
    /**
     * Almacenado masivo de pólizas
     *
     * Recibe un array de pólizas y las inserta masivamente.
     * POST /api/polizas/masivo
     */
    public function storeMasivo(Request $request)
    {
        $polizasData = $request->input('polizas');

        if (! is_array($polizasData) || empty($polizasData)) {
            return response()->json([
                'message' => 'El parámetro "polizas" debe ser un arreglo de objetos de póliza.',
            ], 400);
        }

        // Opcional: agregar validación de campos requeridos
        $reglas = [
            // Foreign keys
            'IDSolicitud' => 'nullable|integer',
            'IDCiclo' => 'nullable|integer',
            'IDModalidad' => 'nullable|integer',
            'IDCultivo' => 'nullable|integer',
            'IDEstado' => 'nullable|integer',
            'IDRamo' => 'nullable|integer',
            'IDSubramo' => 'nullable|integer',
            'IDProducto' => 'nullable|integer',
            'IDMetodoEva' => 'nullable|integer',
            'IDUnidadRiesgo' => 'nullable|integer',
            'IDMoneda' => 'nullable|integer',
            'IDOficina' => 'nullable|integer',
            'IDAgente' => 'nullable|integer',
            'IDFormaPago' => 'nullable|integer',
            'IDConvenio' => 'nullable|integer',
            'IDFunciones' => 'nullable|integer',
            'IDEspecies' => 'nullable|integer',
            // Policy information
            'NoPoliza' => 'required|string|max:255',
            'NoRRP' => 'nullable|string|max:255',
            'FEmisionPoliza' => 'required|date',
            'StatusPoliza' => 'nullable|string|max:100',
            'SuperAsegurada' => 'nullable|boolean',
            'SumaAsegurada' => 'nullable|numeric',
            'SumaASeguradaTotal' => 'nullable|numeric',
            'PrimaTotal' => 'nullable|numeric',
            // Dates
            'FIV' => 'nullable|date',
            'FFV' => 'nullable|date',
            'FLPPoliza' => 'nullable|date',
            // Premium details
            'PrimaAsegurado' => 'nullable|numeric',
            'PrimaGob' => 'nullable|numeric',
            'PrimaApoyos' => 'nullable|numeric',
            'Tarifa' => 'nullable|numeric',
            'Rendimiento' => 'nullable|numeric',
            'Precio' => 'nullable|numeric',
            'GastoEmision' => 'nullable|numeric',
            // Subsidy and support information
            'SubSidioGobSiNo' => 'nullable|boolean',
            'PorcentajeGob' => 'nullable|numeric',
            'ApoyoCruzadaSiNo' => 'nullable|boolean',
            'PorcentajeApoyos' => 'nullable|numeric',
            // Beneficiaries and payment
            'Beneficiarios' => 'nullable|string|max:255',
            'FechaPago' => 'nullable|date',
            'FormaPago' => 'nullable|string|max:100',
            'MontoPagado' => 'nullable|numeric',
            'ConceptoPago' => 'nullable|string|max:255',
            'IndemnizacionTotal' => 'nullable|numeric',
            // Agent commission
            'ComisionAgente' => 'nullable|numeric',
            // User and year
            'Usuario' => 'nullable|string|max:255',
            'año' => 'nullable|integer',
            // Additional policy details
            'FLDFolioPoliza' => 'nullable|string|max:255',
            'Coaseguro' => 'nullable|boolean',
            'Franquicia' => 'nullable|boolean',
            'Cve_convenios' => 'nullable|string|max:100',
            'Conv_prop' => 'nullable|numeric',
            'TipoPoliza' => 'nullable|string|max:100',
            'ObservacionesCaratula' => 'nullable|string|max:500',
            'PagoPorlintermediario' => 'nullable|boolean',
            'FacturaPorProductor' => 'nullable|boolean',
            'FolioSolicitud' => 'nullable|string|max:100',
            // File information
            'PathArchivo' => 'nullable|string|max:255',
            'NombreArchivo' => 'nullable|string|max:255',
            // Insurance legend
            'LeyendaAseguramiento' => 'nullable|string|max:400',
        ];

        $errores = [];
        $polizasInsertadas = [];

        foreach ($polizasData as $i => $poliza) {
            // Solo validación superficial, si quieres más estricta usa Validator Facade
            foreach ($reglas as $campo => $regla) {
                if (strpos($regla, 'required') !== false && (! isset($poliza[$campo]) || $poliza[$campo] === null || $poliza[$campo] === '')) {
                    $errores[$i][] = "El campo $campo es obligatorio.";
                }
            }
            if (isset($errores[$i])) {
                continue;
            }
            $polizasInsertadas[] = $poliza;
        }

        if (! empty($errores)) {
            return response()->json([
                'message' => 'Algunas pólizas no cumplen los criterios de validación.',
                'errores' => $errores,
            ], 422);
        }

        try {
            $insertados = \App\Models\Poliza::insert($polizasInsertadas);

            return response()->json([
                'message' => 'Pólizas insertadas correctamente.',
                'insertadas' => $polizasInsertadas,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al insertar las pólizas: '.$e->getMessage(),
            ], 500);
        }
    }
}
