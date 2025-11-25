<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\CatCampos;
use App\Models\TbPerfilTransaccional;
use App\Models\CatParametrosPerfilTrans;
use Illuminate\Support\Facades\DB;

class PerfilTransaccionalController extends Controller
{
    public function index()
    {
        // Campos dinámicos
        $campos = CatCampos::where('IDModulo', 2)
            ->where('Seccion', 1)
            ->orderBy('Orden')
            ->get();

        // Periodos disponibles
        $periodos = TbPerfilTransaccional::selectRaw("
                DISTINCT FechaEjecucción,
                DATE_FORMAT(FechaEjecucción, '%d/%m/%Y') AS PeriodoFormateado
            ")
            ->where('IDTipoEjecuccion', 1)
            ->orderByDesc('FechaEjecucción')
            ->get();

        return Inertia::render('PerfilTransaccional/Index', [
            'campos' => $campos,
            'periodos' => $periodos,
        ]);
    }

    // Registrar Perfil
    public function insert(Request $request)
    {
        // dd($request->all()); // Ver los datos que llegan (opcional para depuración)
        try {
            $data = $request->all();

            CatParametrosPerfilTrans::create([
                'IDRegistroParametro'         => rand(1000, 9999), // o genera tu propio consecutivo
                'PorcentajeNacimiento'        => $data['PorNacimiento'] ?? 0,
                'PorcentajeResidencia'        => $data['PorResidencia'] ?? 0,
                'PorcentajePredio'            => $data['PorPredio'] ?? 0,
                'PorcentajeNacionalidad'      => $data['PorNacionalidad'] ?? 0,
                'PorcentajeAmbitoLaboral'     => $data['PorAmbitoLaboral'] ?? 0,
                'PorcentajeOrigenRecursos'    => $data['PorOrigenRecursos'] ?? 0,
                'PorcentajeIngresosEstimados' => $data['PorIngresosEstimados'] ?? 0,
                'PorcentajePromedioUR'        => $data['PorPromedioUR'] ?? 0,
                'PorcentajeUbicacion'         => $data['PorUbicacion'] ?? 35, // no viene del form, lo dejamos 30
                'PorcentajeDatosEconomicos'   => $data['PorDatosEconomicos'] ?? 35,
                'PorcentajeDatosLaborales'    => $data['PorDatosLaborales'] ?? 30,
                'FechaActualizacion'          => $data['fechaaplicacion'] ?? '',
                'TimeStampAlta'               => now(),
                'Activo'                      => 1,
            ]);

            return redirect()->back()->with('success', 'Perfil guardado correctamente');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al guardar el perfil: ' . $e->getMessage());
        }
    }

    public function buscar(Request $request)
    {
        try {
            $periodo = $request->input('Periodo');
            $idCliente = $request->input('IDCliente');

            // Caso 1: Buscar por IDCliente (para API / Postman)
            if (!empty($idCliente) && empty($periodo)) {
                // $registro = TbPerfilTransaccional::where('IDCliente', $idCliente)->first();
                $registro = TbPerfilTransaccional::select(
                    'tbperfiltransaccional.*',
                    'tbclientes.Nombre',
                    'tbclientes.ApellidoPaterno',
                    'tbclientes.ApellidoMaterno'
                )
                ->leftJoin('tbclientes', 'tbclientes.IDCliente', '=', 'tbperfiltransaccional.IDCliente')
                ->where('tbperfiltransaccional.IDCliente', $idCliente)
                ->first();

                if (!$registro) {
                    return response()->json([
                        'success' => false,
                        'mensaje' => 'No se encontró información para el cliente especificado.'
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'mensaje' => 'Datos del cliente obtenidos correctamente.',
                    'perfilTransaccional' => (float) $registro->Perfil,
                    'IDRiesgoPerfil' => ($registro->IDRegistroPerfil ?? 0)
                ]);
            }

            // 🔹 Caso 2: Buscar por periodo (para generar CSV)
            if (empty($periodo)) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'Debe enviar al menos el campo Periodo o IDCliente.'
                ], 400);
            }

            // $datos = TbPerfilTransaccional::whereDate('FechaEjecucción', $periodo)->get();
            $datos = TbPerfilTransaccional::select(
                'tbperfiltransaccional.*',
                'tbclientes.Nombre',
                'tbclientes.ApellidoPaterno',
                'tbclientes.ApellidoMaterno'
            )
            ->leftJoin('tbclientes', 'tbclientes.IDCliente', '=', 'tbperfiltransaccional.IDCliente')
            ->whereDate('FechaEjecucción', $periodo)
            ->get();

            if ($datos->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'mensaje' => 'No se encontraron registros para ese periodo.',
                    'datos' => []
                ]);
            }

            // Generar CSV temporal
            $nombreArchivo = "perfil_transaccional_{$periodo}.csv";
            $ruta = storage_path("app/public/{$nombreArchivo}");
            $archivo = fopen($ruta, 'w');

            // Encabezado CSV
            fputcsv($archivo, [
                'IDCliente','Nombre', 'EdoNacimiento', 'NivelRiesgoNac', 'CalculoNacimiento',
                'EdoDomicilio', 'NivelRiesgoDoc', 'CalculoResidencia',
                'EdoLabora', 'NivelRiesgoResidencia', 'CalculoLaboral', 'TotalUbicacion',
                'Origen', 'ORecursos', 'Ingresos', 'PromedioHA', 'TotalEconomico',
                'OcupGiro', 'NivelRiesgo', 'CalculoOcupacion', 'Perfil', 'Periodo'
            ]);

            foreach ($datos as $fila) {
                fputcsv($archivo, [
                    $fila->IDCliente,
                    $fila->Nombre,
                    // '', // Nombre vacío
                    $fila->IDEstadoNacimiento,
                    $fila->NivelRiesgoNac,
                    $fila->CalculoNacimiento,
                    $fila->IDEstadoDomicilio,
                    $fila->NivelRiesgoDoc,
                    $fila->CalculoResidencia,
                    $fila->IDEstadoLabora,
                    $fila->NivelRiesgoResidencia,
                    $fila->CalculoLaboral,
                    $fila->TotalUbicacion,
                    $fila->Origen,
                    $fila->ORecursos,
                    $fila->IngresosMensuales,
                    $fila->PromedioHA,
                    $fila->TotalEconomico,
                    $fila->OcupGiro,
                    $fila->NivelRiesgo,
                    $fila->CalculoOcupacion,
                    $fila->Perfil,
                    $fila->FechaEjecucción,
                ]);
            }

            fclose($archivo);

            return response()->json([
                'success' => true,
                'mensaje' => 'Datos encontrados correctamente.',
                'csvUrl' => asset("storage/{$nombreArchivo}"),
                'total_registros' => $datos->count(),
                'datos' => $datos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al buscar información: ' . $e->getMessage()
            ], 500);
        }
    }

    /* Ejecutar perfil transaccional (si se necesita lanzar SP u otro proceso)*/
    public function ejecutar(Request $request)
    {
        try {
            // Ejecuta SP sin parámetros (como tu ejemplo)
            DB::statement("CALL SP_PerfilTransIndividual()");

            return response()->json([
                'success' => true,
                'mensaje' => 'Ejecución completada correctamente'
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'mensaje' => 'Error al ejecutar el perfil: ' . $e->getMessage()
            ], 500);
        }
    }

}