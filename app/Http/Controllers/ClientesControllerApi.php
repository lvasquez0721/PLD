<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; // <-- Add this import
use App\Models\Clientes\TbClientes;

class ClientesControllerApi extends Controller
{
    public function storeMasivo(Request $request)
    {
        $clientesData = $request->input('clientes');

        if (!is_array($clientesData)) {
            return response()->json([
                'message' => 'El parámetro "clientes" debe ser un arreglo de objetos de cliente.'
            ], 400);
        }

        $reglas = [
            'IDSolicitante'    => 'nullable|numeric',
            'no_cliente'       => 'required|string|unique:clientes,no_cliente',
            'nombre'           => 'required|string|max:255',
            'apellido_p'       => 'required|string|max:255',
            'apellido_m'       => 'nullable|string|max:255',
            'tipo_persona'     => 'required|in:FISICA,MORAL',
            'curp'             => 'nullable|string|max:18',
            'rfc'              => 'nullable|string|max:13',
            'ocupacion_giro'   => 'nullable|string|max:255',
            'estado_radica'    => 'nullable|string|max:255',
            'fecha_nacimiento' => 'nullable|date',
            'edad'             => 'nullable|integer|min:0',
            'calle'            => 'nullable|string|max:255',
            'no_exterior'      => 'nullable|string|max:16',
            'colonia'          => 'nullable|string|max:255',
            'cp'               => 'nullable|string|max:8',
            'id_estado'        => 'nullable|integer',
            'id_municipio'     => 'nullable|integer',
            'id_localidad'     => 'nullable|integer',
        ];

        $errores = [];
        $clientesInsertados = [];
        foreach ($clientesData as $i => $cliente) {
            $validator = Validator::make($cliente, $reglas);

            if ($validator->fails()) {
                $errores[$i] = $validator->errors()->all();
                continue;
            }
            $clientesInsertados[] = $cliente;
        }

        if (!empty($errores)) {
            return response()->json([
                'message' => 'Algunos clientes no cumplen los criterios de validación.',
                'errores' => $errores,
            ], 422);
        }

        try {
            $insertados = \App\Models\Cliente::insert($clientesInsertados);

            return response()->json([
                'message' => 'Clientes insertados correctamente.',
                'insertados' => $clientesInsertados
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al insertar los clientes: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function guardarCliente(Request $request) {
        $validator = Validator::make($request->all(), [
            'RFC'                 => 'nullable|string|max:13',
            'Nombre'              => 'required|string|max:255',
            'ApellidoPaterno'     => 'nullable|string|max:255',
            'ApellidoMaterno'     => 'nullable|string|max:255',
            'RazonSocial'         => 'nullable|string|max:255',
            'IDTipoPersona'       => 'required|integer',
            'CURP'                => 'nullable|string|max:18',
            'IDOcupacionGiro'     => 'nullable|integer',
            'FechaNacimiento'     => 'nullable|date',
            'FechaConstitucion'   => 'nullable|date',
            'FolioMercantil'      => 'nullable|string|max:255',
            'CoincideEnListasNegras' => 'boolean',
            'EsPPEActivo'         => 'boolean',
            'IDNacionalidad'      => 'nullable|integer',
            'IDEstadoNacimiento'  => 'nullable|integer',
            'Activo'              => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            
            $data['IDCliente'] = TbClientes::max('IDCliente') + 1;
            
            $cliente = TbClientes::create($data);
            
            return response()->json([
                'message' => 'Cliente guardado correctamente.',
                'cliente' => $cliente,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al guardar el cliente: ' . $e->getMessage(),
            ], 500);
        }
    }
}
