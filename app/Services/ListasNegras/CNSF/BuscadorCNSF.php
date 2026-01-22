<?php

namespace App\Services\ListasNegras\CNSF;

use App\Models\ListasBloqueadas\TbListasNegraCNSF;
use App\Services\ParametriaPLD\ParametriaPLDService;
use App\Services\ListasNegras\Comparador\SmithWatermanGotoh;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class BuscadorCNSF {

    private string $cadena = '';
    private float $porcentajeMatch = 0;
    private array $listaHit = [];
    private string $cadenaBuscar = '';
    private int $IDCategoria = -1;
    private ?int $tipoPersona = null;
    private string $nombre = '';
    private string $apaterno = '';
    private string $amaterno = '';
    private string $razonSocial = '';

    public function __construct() {
        try {
            $this->porcentajeMatch = (float) ParametriaPLDService::get('Porcentaje Match Buscador CNSF', 80) / 100;
        } catch (Exception $e) {
            Log::error("Error al inicializar BuscadorCNSF: " . $e->getMessage());
            $this->porcentajeMatch = 0.80;
        }
    }

    private function reiniciaValores(): void {
        $this->cadena = '';
        $this->listaHit = [];
        $this->cadenaBuscar = '';
        $this->IDCategoria = -1;
        $this->tipoPersona = null;
        $this->nombre = '';
        $this->apaterno = '';
        $this->amaterno = '';
        $this->razonSocial = '';
    }

    public function doBusqueda($tipoPersona, $nombre, $apaterno, $amaterno, $razonSocial): array {
        $this->reiniciaValores();

        $this->tipoPersona = $tipoPersona;
        $this->nombre = strtoupper(trim($nombre));
        $this->apaterno = strtoupper(trim($apaterno));
        $this->amaterno = strtoupper(trim($amaterno));
        $this->razonSocial = strtoupper(trim($razonSocial));

        $nombreOpcion1 = trim("{$this->nombre} {$this->apaterno} {$this->amaterno}");
        $nombreOpcion2 = trim("{$this->apaterno} {$this->amaterno} {$this->nombre}");

        $cadena = trim($tipoPersona == 2) ? $this->razonSocial : $nombreOpcion1;

        $this->cadenaBuscar = $cadena;

        Log::info("Iniciando búsqueda CNSF", [
            'tipoPersona' => $tipoPersona,
            'cadenaBuscar' => $this->cadenaBuscar,
            'nombre' => $this->nombre,
            'apaterno' => $this->apaterno,
            'amaterno' => $this->amaterno
        ]);

        $this->ejecutarBusqueda($cadena);

        return [
            'personaBloqueada'       => count($this->listaHit) > 0,
            'IDCategoria'            => $this->obtieneCategoriaPLD(),
            'detalleListaBloqueadas' => array_map(function ($hit) {
                return [
                    'lista'            => 'CNSF',
                    'nombreDetectado'  => $hit['persona'],
                    'IDListaOrigen' => 2,
                    'rfc'              => $hit['rfc'] ?? null,
                    'curp'             => $hit['curp'] ?? null,
                    'coincidencia'       => $hit['porcentaje'],
                ];
            }, $this->listaHit),
            'totalCoincidencias' => count($this->listaHit)
        ];
    }

    private function ejecutarBusqueda(string $cadena): void{
        $comparador = new SmithWatermanGotoh();
        $this->listaHit = [];

            $registros = TbListasNegraCNSF::query()
                ->select('Nombre', 'IDRegistroListaCNSF', 'RFC')
                ->where('Nombre', 'LIKE', "%{$this->nombre}%{$this->apaterno}%{$this->amaterno}%")
                ->unionAll(
                    TbListasNegraCNSF::query()
                        ->select('Nombre', 'IDRegistroListaCNSF', 'RFC')
                        ->where('Nombre', 'LIKE', "%{$this->apaterno}%{$this->amaterno}%{$this->nombre}%")
                )
            ->get();

            if ($registros->isEmpty()) {
                Log::info("No se encontraron registros en CNSF para la búsqueda.");
                return;
            }

            foreach ($registros as $registro) {
                $cadenaBD = strtoupper($registro->Nombre);
                $registroNombre = $cadenaBD;
                $encuentra = false;

                if ($this->tipoPersona == 1) {
                    $coincidenciaNom = strpos($cadenaBD, $this->nombre);
                    $coincidenciaApat = strpos($cadenaBD, $this->apaterno);
                    $coincidenciaAmat = strpos($cadenaBD, $this->amaterno);

                    if ($coincidenciaNom !== false || $coincidenciaApat !== false || $coincidenciaAmat !== false) {
                        $encuentra = true;
                    }
                } else {
                    $coincidenciaRazon = strpos($cadenaBD, $this->razonSocial);
                    if ($coincidenciaRazon !== false) {
                        $encuentra = true;
                    }
                }

                if ($encuentra) {
                    $registroNombre = strtoupper(trim($cadenaBD));

                    if ($registroNombre === '') {
                        continue;
                    }

                    $porcentaje = $comparador->compare($cadena, $registroNombre);
                    $porcentajeLongitud = strlen($registroNombre) / strlen($cadena);

                    if ($porcentaje >= $this->porcentajeMatch && $porcentajeLongitud >= $this->porcentajeMatch) {
                        $this->listaHit[] = [
                            'lista'       => 'CNSF',
                            'persona'     => $registroNombre,
                            'rfc'         => $registro->RFC ?? null,
                            'curp'        => $registro->CURP ?? null,
                            'porcentaje'  => round($porcentaje, 2),
                        ];
                    }
                }
            }

            Log::info("Búsqueda CNSF completada", [
                'totalHits' => count($this->listaHit)
            ]);
    }

    public function obtieneCategoriaPLD(): int {
        $totalHit = count($this->listaHit);

        if ($totalHit == 0) {
            $this->IDCategoria = 0;
        } else {
            $this->IDCategoria = 7;
        }

        return $this->IDCategoria;
    }

    // public function enviaNotificacionCNSF(): void {
    //     try {
    //         // Obtener correos de notificación
    //         $correos = DB::table('sit.catEnvioNotificaciones')
    //             ->where('Archivo', 'BuscadorCNSF.class')
    //             ->get(['Correo', 'Alias']);

    //         if ($correos->isEmpty()) {
    //             Log::warning("No hay correos configurados para notificaciones CNSF");
    //             return;
    //         }

    //         $listAddress = $correos->map(function($correo) {
    //             return [
    //                 'address' => $correo->Correo,
    //                 'addressAlias' => $correo->Alias
    //             ];
    //         })->toArray();

    //         $datos = $this->generNotificacion();

    //         $data = [
    //             'typeNotification' => 'MAIL',
    //             'template' => $datos['template'],
    //             'listAddress' => $listAddress,
    //             'subject' => $datos['subject'],
    //             'params' => [
    //                 'user_system' => 'Usuario',
    //                 'register_person' => htmlentities($datos['person'], ENT_QUOTES, "UTF-8"),
    //                 'detection_date' => $datos['date'],
    //                 'detection_hour' => $datos['hour'],
    //                 'matches' => $datos['noMatches'],
    //                 'names_found' => htmlentities($datos['matches'], ENT_QUOTES, "UTF-8"),
    //                 'type_sit_person' => htmlentities($datos['catPLD_desc'], ENT_QUOTES, "UTF-8"),
    //                 'comments' => htmlentities($datos['comments'], ENT_QUOTES, "UTF-8")
    //             ]
    //         ];

    //         // Aquí deberías usar tu servicio de notificaciones de Laravel
    //         // Por ejemplo: NotificationService::send($data);
            
    //         Log::info("Notificación CNSF enviada", [
    //             'destinatarios' => count($listAddress),
    //             'matches' => $datos['noMatches']
    //         ]);

    //     } catch (Exception $e) {
    //         Log::error("Error al enviar notificación CNSF: " . $e->getMessage());
    //     }
    // }

    // private function generNotificacion(): array {
    //     $data = [];

    //     $data['subject'] = 'Aviso SIT - Persona detectada en listas CNSF';
    //     $data['template'] = 'PLD004';
    //     $data['person'] = $this->cadenaBuscar;
    //     $data['date'] = now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    //     $data['hour'] = now()->format('H:i');
    //     $data['noMatches'] = count($this->listaHit);
    //     $data['matches'] = implode(", ", array_column($this->listaHit, "Persona"));
        
    //     // Estas funciones deberás migrarlas también o adaptarlas
    //     $data['catPLD_desc'] = $this->getCategoriaPLDDescripcion($this->IDCategoria);
    //     $data['comments'] = $this->getComentariosDeteccion($this->IDCategoria);

    //     return $data;
    // }

    // Métodos auxiliares que necesitarás implementar según tu lógica
    // private function getCategoriaPLDDescripcion(int $categoria): string {
    //     // Implementa la lógica de PLDNotificaciones::getCategoriaPLD
    //     // Puedes crear un servicio o helper para esto
    //     return "Categoría {$categoria}";
    // }

    // private function getComentariosDeteccion(int $categoria): string {
    //     // Implementa la lógica de PLDNotificaciones::getComentariosDeteccion
    //     return "Comentarios para categoría {$categoria}";
    // }

    // Getters útiles
    public function getListaHit(): array {
        return $this->listaHit;
    }

    public function getCadenaBuscar(): string {
        return $this->cadenaBuscar;
    }

    public function getTotalHits(): int {
        return count($this->listaHit);
    }



}