<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IDRRPLD;
use Illuminate\Support\Facades\Validator;

class IDRRPLDController extends Controller
{
    /**
     * Inserta de forma masiva uno o más registros de IDRRPLD.
     */
    public function bulkInsert(Request $request)
    {
        $data = $request->input('registros');

        if (!is_array($data)) {
            return response()->json([
                'error' => 'El formato de entrada es incorrecto. Se esperaba un arreglo de registros bajo la clave "registros".'
            ], 400);
        }

        $rules = [
            '*.TipoReporte' => 'required|string',
            '*.PeriodoReporte' => 'required|string',
            '*.Folio' => 'required|string',
            '*.OrganoSupervisor' => 'nullable|string',
            '*.CveSujetoObligado' => 'nullable|string',
            '*.Localidad' => 'nullable|string',
            '*.Sucursal' => 'nullable|string',
            '*.TipoOperacion' => 'nullable|string',
            '*.InstrumentoMonetario' => 'nullable|string',
            '*.NoPoliza' => 'nullable|string',
            '*.Monto' => 'required|numeric',
            '*.Moneda' => 'nullable|string',
            '*.FechaOperacion' => 'required|date',
            '*.FechaDeteccion' => 'nullable|date',
            '*.Nacionalidad' => 'nullable|string',
            '*.TipoPersona' => 'nullable|string',
            '*.RazonSocial' => 'nullable|string',
            '*.Nombre' => 'nullable|string',
            '*.APaterno' => 'nullable|string',
            '*.AMaterno' => 'nullable|string',
            '*.RFC' => 'nullable|string',
            '*.CURP' => 'nullable|string',
            '*.FechaNacimiento' => 'nullable|date',
            '*.Domicilio' => 'nullable|string',
            '*.Colonia' => 'nullable|string',
            '*.Ciudad' => 'nullable|string',
            '*.Telefono' => 'nullable|string',
            '*.Ocupacion' => 'nullable|string',
            '*.NombreAgente' => 'nullable|string',
            '*.APaternoAgente' => 'nullable|string',
            '*.AMaternoAgente' => 'nullable|string',
            '*.RFCAgente' => 'nullable|string',
            '*.CURPAgente' => 'nullable|string',
            '*.Cuenta' => 'nullable|string',
            '*.NoPolizaCuenta' => 'nullable|string',
            '*.CveSujetoObl' => 'nullable|string',
            '*.NombreTitular' => 'nullable|string',
            '*.APaternoTitular' => 'nullable|string',
            '*.AMaternoTitular' => 'nullable|string',
            '*.Descripcion' => 'nullable|string',
            '*.Razon' => 'nullable|string',
            '*.Estatus' => 'nullable|string',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            IDRRPLD::insert($data);
            return response()->json([
                'message' => 'Registros insertados correctamente',
                'success' => true
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al insertar los registros.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
