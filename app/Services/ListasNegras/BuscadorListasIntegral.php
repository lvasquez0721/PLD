<?php

namespace App\Services\ListasNegras;

use App\Services\ListasNegras\UIF\BuscadorUIF;
use App\Services\ListasNegras\CNSF\BuscadorCNSF;
use App\Services\ListasNegras\QeQ\QeQ;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class BuscadorListasIntegral {
    private $IDTipoPersona;
    private $RFC;
    private $nombre;
    private $apellidoPaterno;
    private $apellidoMaterno;
    private $razonSocial;
    private $pathEvidencia;
    private $nuevoIDCliente;

    public function __construct() {
        $this->IDTipoPersona = null;
        $this->RFC = '';
        $this->nombre = '';
        $this->apellidoPaterno = '';
        $this->apellidoMaterno = '';
        $this->razonSocial = '';
        $this->pathEvidencia = null;
        $this->nuevoIDCliente = null;
    }

    public function __destruct() {
        unset($this->IDTipoPersona);
        unset($this->RFC);
        unset($this->nombre);
        unset($this->apellidoPaterno);
        unset($this->apellidoMaterno);
        unset($this->razonSocial);
        unset($this->pathEvidencia);
        unset($this->nuevoIDCliente);
    }

    public function realizaBusqueda($nuevoIDCliente, $IDTipoPersona, $RFC, $nombre, $apellidoPaterno, $apellidoMaterno, $razonSocial, $pathEvidencia, $modo = 1) {
        Log::info('Iniciando búsqueda en listas negras para el cliente ID: ' . $nuevoIDCliente);
        
        $this->nuevoIDCliente = $nuevoIDCliente;
        $this->IDTipoPersona = $IDTipoPersona;
        $this->rfc = $RFC;
        $this->nombre = $nombre;
        $this->apellidoPaterno = $apellidoPaterno;
        $this->apellidoMaterno = $apellidoMaterno;
        $this->razonSocial = $razonSocial;
        $this->pathEvidencia = $pathEvidencia;

        // Ejecutar búsquedas en todas las listas
        $resultadoUIF = $this->buscarUIF();
        $resultadoCNSF = $this->buscarCNSF();
        // $resultadoQeQ = $this->buscarQeQ();

        // Consolidar resultados
        $detalleListaBloqueadas = array_merge(
            $resultadoUIF['detalleListaBloqueadas'] ?? [],
            $resultadoCNSF['detalleListaBloqueadas'] ?? [],
            $resultadoQeQ['detalleListaBloqueadas'] ?? []
        );

        // Eliminar duplicados
        $detalleListaBloqueadas = $this->eliminarDuplicados($detalleListaBloqueadas);

        $resultado = [
            'esPPE' => $resultadoQeQ['esPPE'] ?? false,
            'personaBloqueada' => (
                ($resultadoUIF['personaBloqueada'] ?? false) ||
                ($resultadoCNSF['personaBloqueada'] ?? false) ||
                ($resultadoQeQ['personaBloqueada'] ?? false)
            ),
            'IDCategoria' => max(
                $resultadoUIF['IDCategoria'] ?? 0,
                $resultadoCNSF['IDCategoria'] ?? 0,
                $resultadoQeQ['IDCategoria'] ?? 0
            ),
            'totalCoincidencias' => count($detalleListaBloqueadas),
            'detalleListaBloqueadas' => $detalleListaBloqueadas,
        ];

        Log::info("Resultado consolidado desde BuscadorListasIntegral:", [
            'esPPE' => $resultado['esPPE'],
            'personaBloqueada' => $resultado['personaBloqueada'],
            'IDCategoria' => $resultado['IDCategoria'],
            'totalCoincidencias' => $resultado['totalCoincidencias']
        ]);

        return $resultado;
    }

    private function eliminarDuplicados(array $detalles): array {
        $unicos = [];
        $llaves = [];

        foreach ($detalles as $detalle) {
            $llave = sprintf(
                '%s|%s|%s',
                $detalle['lista'] ?? '',
                $detalle['nombreDetectado'] ?? '',
                $detalle['rfc'] ?? ''
            );

            if (!isset($llaves[$llave])) {
                $llaves[$llave] = true;
                $unicos[] = $detalle;
            }
        }

        return $unicos;
    }

    private function buscarUIF() {
        $resultado = [
            'IDCategoria' => 0,
            'personaBloqueada' => false,
            'detalleListaBloqueadas' => [],
        ];

            $obj = new BuscadorUIF();
            $respuesta = $obj->doBusqueda(
                $this->IDTipoPersona,
                $this->nombre,
                $this->apellidoPaterno,
                $this->apellidoMaterno,
                $this->razonSocial
            );

            $resultado = $respuesta;
            $totalResultados = count($resultado['detalleListaBloqueadas'] ?? []);

            if ($totalResultados > 0) {
                $this->crearAlertaUIF($resultado['detalleListaBloqueadas']);

                // Registrar en bitácora
                $nombresDetectados = implode(',', array_column($resultado['detalleListaBloqueadas'], 'nombreDetectado'));
                $this->ingresaBitacoraLista(
                    Lista: 'UIF', 
                    NombreDetectado: $nombresDetectados, 
                    Origen: '3'
                );
            }

            Log::info("Búsqueda UIF completada", [
                'IDCategoria' => $resultado['IDCategoria'],
                'totalResultados' => $totalResultados
            ]);
        return $resultado;
    }

    private function buscarCNSF() {
        $resultado = [
            'IDCategoria' => 0,
            'personaBloqueada' => false,
            'detalleListaBloqueadas' => [],
        ];

            $obj = new BuscadorCNSF();
            $respuesta = $obj->doBusqueda(
                $this->IDTipoPersona,
                $this->nombre,
                $this->apellidoPaterno,
                $this->apellidoMaterno,
                $this->razonSocial
            );

            $resultado = $respuesta;

            $totalResultados = count($resultado['detalleListaBloqueadas'] ?? []);

            if ($totalResultados > 0) {
                $this->crearAlertaUIF($resultado['detalleListaBloqueadas']);

                $nombresDetectados = implode(',', array_column($resultado['detalleListaBloqueadas'] ?? [], 'nombreDetectado'));
                $origenes = 'CNSF';

                $this->ingresaBitacoraLista(
                    Lista: 'CNSF', 
                    NombreDetectado: $nombresDetectados, 
                    Origen: '2'
                );
            }

            Log::info('Búsqueda CNSF completada', [
                'IDCategoria' => $resultado['IDCategoria'],
                'totalResultados' => $totalResultados
            ]);

        return $resultado;
    }

    private function buscarQeQ() {
        $resultado = [
            'esPPE' => false,
            'personaBloqueada' => false,
            'IDCategoria' => 0,
            'detalleListaBloqueadas' => [],
            'totalCoincidencias' => 0,
            'archivoEvidencia' => null,
        ];

            $obj = new QeQ;
            
            $params = [
                'tipoPersona' => $this->IDTipoPersona,
                'rfc' => $this->rfc,
                'nombre' => $this->nombre,
                'apaterno' => $this->apellidoPaterno,
                'amaterno' => $this->apellidoMaterno,
                'razonSocial' => $this->razonSocial,
            ];
            
            $respuesta = $obj->ejecutarConsulta($params);
            
            if (isset($respuesta['error'])) {
                Log::error('Error en consulta QeQ: ' . $respuesta['error']);
                return $resultado;
            }

            $resultado = $respuesta;
            $totalResultados = $respuesta['totalCoincidencias'] ?? 0;
            
            if ($totalResultados > 0) {

                foreach ($respuesta['detalleListaBloqueadas'] as &$detalle) {
                    $nombreDetectado = $detalle['nombreDetectado'] ?? '';
                    $lista = $detalle['lista'] ?? '';
                    
                    Log::info('Registros detectados en QeQ:', $respuesta['detalleListaBloqueadas']);
                    
                    $this->ingresaBitacoraLista(
                        Lista: $lista, 
                        NombreDetectado: $nombreDetectado, 
                        Origen: '1'
                    );
                    }

                if (intval($respuesta['IDCategoria']) == 4 || ($respuesta['esPPE'] ?? false)) {
                    $this->insertarClientePPE($respuesta['archivoEvidencia'] ?? null);
                }
            }

            Log::info('Búsqueda QeQ completada', [
                'IDCategoria' => $resultado['IDCategoria'],
                'esPPE' => $resultado['esPPE'],
                'totalResultados' => $totalResultados
            ]);
        
        return $respuesta;
    }

    private function crearAlertaUIF(array $detalles) {
        $cadena = $this->obtenerCadenaPersona();
        
        $nombresDetectados = implode(', ', array_column($detalles, 'nombreDetectado'));
        
        DB::table('tbalertas')->insert([
            'Folio' => null,
            'Patron' => 'Personas Bloqueadas',
            'IDCliente' => $this->nuevoIDCliente,
            'Cliente' => $cadena,
            'FechaDeteccion' => now()->format('Y-m-d'),
            'HoraDeteccion' => now()->format('H:i:s'),
            'FechaOperacion' => now()->format('Y-m-d'),
            'HoraOperacion' => now()->format('H:i:s'),
            'Estatus' => 'Generado',
            'Descripcion' => 'Lista de Personas Bloqueadas',
            'Razones' => "Se ha detectado coincidencia con '{$cadena}' en listas negras. Nombres detectados: {$nombresDetectados}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        Log::info('Alerta de listas negras creada exitosamente', [
            'IDCliente' => $this->nuevoIDCliente,
            'Cliente' => $cadena,
            'totalCoincidencias' => count($detalles),
            'nombresDetectados' => $nombresDetectados
        ]);
    }

    private function insertarClientePPE($evidencia = null) {
        try {
            DB::table('tbclientesppe')->insert([
                'IDCliente' => $this->nuevoIDCliente,
                'Lista' => 'QeQ',
                'Cargo' => $respuesta['PUESTO'] ?? null,
                'Estado' => $respuesta['ENTIDAD'],
                'FechaDeteccion' => now(),
                'Origen' => '1',
                'TimeStampRegistro' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Cliente PPE insertado en tbclientesppe', [
                'IDCliente' => $this->nuevoIDCliente,
                'Lista' => 'QeQ',
                'Estado' => null
            ]);
        } catch (Exception $e) {
            Log::error('Error al insertar cliente PPE: ' . $e->getMessage());
        }
    }

    // Insertar en logDetectClientesListas
    private function ingresaBitacoraLista($Lista, $NombreDetectado, $Origen) {
        try {
            DB::table('logDetectClientesListas')->insert([
                'IDCliente' => $this->nuevoIDCliente,
                'Lista' => $Lista,
                'NombreDetectado' => $NombreDetectado,
                'Origen' => $Origen,
                'TimeStampDeteccion' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info('Bitácora de lista insertada', [
                'Lista' => $Lista,
                'IDCliente' => $this->nuevoIDCliente,
                'NombreDetectado' => $NombreDetectado
            ]);

        } catch (Exception $e) {
            Log::error('Error al insertar en logDetectClientesListas: ' . $e->getMessage());
        }
    }

    private function obtenerCadenaPersona(): string {
        if ($this->IDTipoPersona == 2) {
            return $this->razonSocial;
        }

        return trim($this->nombre . ' ' . $this->apellidoPaterno . ' ' . $this->apellidoMaterno);
    }

    private function getCategoria($IDCategorias) {
        sort($IDCategorias, SORT_NUMERIC);
        $total = count($IDCategorias);
        $IDCategoriaFinal = 0;
        $asignado = false;

        for ($i = 0; $i < $total; $i++) {
            if($IDCategorias[$i] != 0 && $asignado == false){
                $IDCategoriaFinal = $IDCategorias[$i];
            }
        }

        return $IDCategoriaFinal;
    }
}