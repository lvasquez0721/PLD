<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ListasNegrasUIF;
use Illuminate\Support\Facades\Validator;

class ListasNegrasUIFController extends Controller
{
    /**
     * Inserción masiva de registros en la tabla tbListasNegrasUIF
     *
     * Endpoint: POST /listas-negras-uif/bulk-insert
     * Acepta uno o varios registros
     */
    public function bulkInsert(Request $request)
    {
        $data = $request->all();

        // Support for both single object or array of objects
        $records = isset($data[0]) && is_array($data) ? $data : [$data];

        $rules = [
            'Buscador'         => 'required|string',
            'RFCCURP'          => 'required|string',
            'FechaNac'         => 'nullable|date',
            'FechaPubAcuerdo'  => 'nullable|date',
            'Acuerdo'          => 'nullable|string',
            'NoOficioUIF'      => 'nullable|string',
            'AnioLista'        => 'nullable|integer',
        ];

        $inserted = [];
        $errors = [];

        foreach ($records as $index => $item) {
            $validator = Validator::make($item, $rules);

            if ($validator->fails()) {
                $errors[$index] = $validator->errors();
                continue;
            }

            $created = ListasNegrasUIF::create($item);
            $inserted[] = $created;
        }

        return response()->json([
            'inserted' => $inserted,
            'errors' => $errors,
        ]);
    }
}
