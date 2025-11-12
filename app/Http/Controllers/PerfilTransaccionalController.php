<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\CatCampos;
use App\Models\TbPerfilTransaccional;
use App\Models\CatParametrosPerfilTrans;

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
        // dd($request->all()); // Ver los datos que llegan (opcional para depuración)

        try {
            $periodo = $request->input('Periodo');

            if (empty($periodo)) {
                return response()->json(['error' => 'Debe seleccionar un periodo'], 400);
            }

            // Buscar datos de la tabla de perfiles transaccionales
            $datos = TbPerfilTransaccional::whereDate('FechaEjecucción', $periodo)
                // ->select('IDRegistroPerfil', 'IDCliente', 'Perfil', 'FechaEjecucción')
                ->get();

            if ($datos->isEmpty()) {
                return response()->json(['mensaje' => 'No se encontraron registros para ese periodo']);
            }

            // Generar CSV temporal
            $nombreArchivo = "perfil_transaccional_{$periodo}.csv";
            $ruta = storage_path("app/public/{$nombreArchivo}");
            $archivo = fopen($ruta, 'w');

            // Encabezado completo sin cliente
            fputcsv($archivo, [
                'IDCliente','Nombre', 'EdoNacimiento', 'NivelRiesgoNac', 'CalculoNacimiento',
                'EdoDomicilio', 'NivelRiesgoDoc', 'CalculoResidencia',
                'EdoLabora', 'NivelRiesgoResidencia', 'CalculoLaboral', 'TotalUbicacion',
                'Origen', 'ORecursos', 'Ingresos', 'PromedioHA', 'TotalEconomico',
                'OcupGiro', 'NivelRiesgo', 'CalculoOcupacion', 'Perfil', 'Periodo'
            ]);

            // Escribir los datos
            foreach ($datos as $fila) {
                fputcsv($archivo, [
                    $fila->IDCliente,
                    '', // Nombre temporalmente vacío
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
            
            if (!file_exists($ruta)) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'No se pudo generar el CSV en la ruta: ' . $ruta
                ]);
            }

            return response()->json([
                'success' => true,
                'mensaje' => 'Datos encontrados correctamente',
                'csvUrl' => asset("storage/{$nombreArchivo}"),
                'datos' => $datos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al buscar información: ' . $e->getMessage()
            ], 500);
        }
    }


    // public function buscar(Request $request)
    // {
    //     try {
    //         $periodo = $request->input('Periodo');

    //         if (empty($periodo)) {
    //             return response()->json(['error' => 'Debe seleccionar un periodo'], 400);
    //         }

    //         // Buscar datos de la tabla de perfiles transaccionales
    //         $datos = TbPerfilTransaccional::whereDate('FechaEjecucción', $periodo)
    //             ->select('IDRegistroPerfil', 'IDCliente', 'Perfil', 'FechaEjecucción')
    //             ->get();

    //         if ($datos->isEmpty()) {
    //             return response()->json(['mensaje' => 'No se encontraron registros para ese periodo']);
    //         }

    //         // Generar CSV temporal
    //         $nombreArchivo = "perfil_transaccional_{$periodo}.csv";
    //         $ruta = storage_path("app/public/{$nombreArchivo}");
    //         $archivo = fopen($ruta, 'w');

    //         // Encabezado
    //         fputcsv($archivo, ['IDRegistroPerfil', 'IDCliente', 'NivelRiesgo', 'AVGPrimaTotal', 'AVGHaTotal', 'FechaEjecucción']);

    //         foreach ($datos as $fila) {
    //             fputcsv($archivo, [
    //                 $fila->IDRegistroPerfil,
    //                 $fila->IDCliente,
    //                 $fila->NivelRiesgo,
    //                 $fila->AVGPrimaTotal,
    //                 $fila->AVGHaTotal,
    //                 $fila->FechaEjecucción,
    //             ]);
    //         }

    //         fclose($archivo);

    //         return response()->json([
    //             'success' => true,
    //             'mensaje' => 'Datos encontrados correctamente',
    //             'csvUrl' => asset("storage/{$nombreArchivo}"),
    //             'datos' => $datos
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'mensaje' => 'Error al buscar información: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }
    // ---------------------------------------------------------------------
    // Ejecutar perfil transaccional (si se necesita lanzar SP u otro proceso)
    // ---------------------------------------------------------------------
    // public function ejecutar(Request $request)
    // {
    //     try {
    //         // Ejemplo de ejecución de SP o cálculo
    //         $resultado = DB::statement("EXEC pld.SP_PerfilTransIndividual_BICV");

    //         return redirect()->back()->with('success', 'Ejecución completada correctamente');
    //     } catch (\Exception $e) {
    //         return redirect()->back()->with('error', 'Error al ejecutar perfil: ' . $e->getMessage());
    //     }
    // }



}
