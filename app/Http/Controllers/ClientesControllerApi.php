<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; // <-- Add this import
use App\Models\Clientes\TbClientes;

class ClientesControllerApi extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'RFC' => 'required|string|max:13',
            'nombre' => 'required|string|max:100',
            'apellidoPaterno' => 'nullable|string|max:100',
            'apellidoMaterno' => 'nullable|string|max:100',
            'razonSocial' => 'nullable|string|max:255',
            'IDTipoPersona' => 'required|integer',
            'CURP' => 'nullable|string|max:18',
            'IDOcupacionGiro' => 'nullable|integer',
            'fechaNacimiento' => 'nullable|date',
            'fechaConstitucion' => 'nullable|date',
            'folioMercantil' => 'nullable|string|max:50',
            'IDNacionalidad' => 'nullable|integer',
            'IDEstadoNacimiento' => 'nullable|integer',
            'Preguntas' => 'nullable|string',
            'domicilios' => 'required|array|min:1',
            'domicilios.*.calle' => 'required_without:domicilios.*.Calle|string|max:255',
            'domicilios.*.Calle' => 'required_without:domicilios.*.calle|string|max:255',
            'domicilios.*.noExterior' => 'required|string|max:20',
            'domicilios.*.noInterior' => 'nullable|string|max:20',
            'domicilios.*.colonia' => 'required|string|max:100',
            'domicilios.*.CP' => 'required|string|max:10',
            'domicilios.*.IDEstado' => 'required|integer',
            'domicilios.*.municipio' => 'required|string|max:100',
            'domicilios.*.localidad' => 'nullable|string|max:100',
            'domicilios.*.telefono' => 'nullable|string|max:20',
            'domicilios.*.principal' => 'nullable|boolean',
            'IDSistemaOrigen' => 'nullable|string|max:100',
            'NoClienteSistema' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $cliente = TbClientes::create([
            "RFC" => $validator['RFC'],
            "Nombre" => $validator['Nombre'],
            "ApellidoPaterno" => $validator['ApellidoPaterno'],
            "ApellidoMaterno" => $validator['ApellidoMaterno'],
            "RazonSocial" => $validator['RazonSocial'],
            "IDTipoPersona" => $validator['IDTipoPersona'],
            "CURP" => $validator['CURP'],
            "IDOcupacionGiro" => $validator['IDOcupacionGiro'],
            "FechaNacimiento" => $validator['FechaNacimiento'],
            "FechaConstitucion" => $validator['FechaConstitucion'],
            "FolioMercantil" => $validator['FolioMercantil'],
            "IDNacionalidad" => $validator['IDNacionalidad'],
            "IDEstadoNacimiento" => $validator['IDEstadoNacimiento'],
            "Activo" => 1,
            "Preguntas" => $validator['Preguntas'],
            "EsPPEActivo" => true,
        ]);

        // Aquí terminaría la función por instrucción (solo validate).
    }
}
