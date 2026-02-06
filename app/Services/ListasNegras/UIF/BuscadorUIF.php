<?php

namespace App\Services\ListasNegras\UIF;

use App\Models\ListasBloqueadas\TbListasNegrasUIF;
use App\Services\ListasNegras\Comparador\SmithWatermanGotoh;
use App\Services\ParametriaPLD\ParametriaPLDService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuscadorUIF
{
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

    public function __construct()
    {
        try {
            $this->porcentajeMatch = (float) ParametriaPLDService::get('Porcentaje Match Buscador UIF', 80) / 100;
        } catch (Exception $e) {
            Log::error('Error al inicializar BuscadorUIF: '.$e->getMessage());
            $this->porcentajeMatch = 0.80;
        }
    }

    private function reiniciaValores(): void
    {
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

    public function doBusqueda($tipoPersona, $nombre, $apaterno, $amaterno, $razonSocial): array
    {
        $this->reiniciaValores();

        $this->tipoPersona = $tipoPersona;
        $this->nombre = strtoupper(trim($nombre));
        $this->apaterno = strtoupper(trim($apaterno));
        $this->amaterno = strtoupper(trim($amaterno));
        $this->razonSocial = strtoupper(trim($razonSocial));

        // Construir cadenas de búsqueda
        $nombreOpcion1 = trim("{$this->apaterno} {$this->amaterno} {$this->nombre}");
        $nombreOpcion2 = trim("{$this->nombre} {$this->apaterno} {$this->amaterno}");

        $cadena = ($tipoPersona == 2) ? $this->razonSocial : $nombreOpcion1;
        $cadena1 = ($tipoPersona == 2) ? $this->razonSocial : $nombreOpcion2;

        $this->cadenaBuscar = strtoupper($cadena);
        $this->cadena = $cadena;

        Log::info('Iniciando búsqueda UIF', [
            'tipoPersona' => $tipoPersona,
            'cadenaBuscar' => $this->cadenaBuscar,
            'cadenaAlternativa' => $this->cadena,
        ]);

        $this->ejecutarBusqueda($cadena, $cadena1);

        // Analizar respuesta y generar resultado
        return $this->analizarRespuesta();

    }

    private function ejecutarBusqueda(string $cadena, string $cadena1): void
    {
        $comparador = new SmithWatermanGotoh;
        $this->listaHit = [];

        $terminos = [];

        if ($this->tipoPersona == 1) {
            // Persona Física
            if (! empty($this->nombre)) {
                $terminos[] = $this->nombre;
            }
            if (! empty($this->apaterno)) {
                $terminos[] = $this->apaterno;
            }
            if (! empty($this->amaterno)) {
                $terminos[] = $this->amaterno;
            }
        } else {
            // Persona Moral
            if (! empty($this->razonSocial)) {
                $terminos[] = $this->razonSocial;
            }
        }

        if (empty($terminos)) {
            Log::warning('No hay términos de búsqueda para UIF');

            return;
        }

        // Búsqueda en base de datos
        $registros = TbListasNegrasUIF::query()
            ->where(function ($query) use ($terminos) {
                foreach ($terminos as $termino) {
                    $query->orWhere('Nombre', 'LIKE', "%{$termino}%");
                }
            })
            ->get();

        foreach ($registros as $registro) {
            $cadenaBD = strtoupper($registro->Nombre);

            $porcentajetmp1 = $comparador->compare($cadena, $cadenaBD);
            $porcentajetmp2 = $comparador->compare($cadena1, $cadenaBD);
            $porcentaje = max($porcentajetmp1, $porcentajetmp2);

            $porcentajeLongitud = strlen($cadenaBD) / strlen($cadena);

            // Validar umbral de coincidencia
            if ($porcentaje >= $this->porcentajeMatch && $porcentajeLongitud >= $this->porcentajeMatch) {
                $this->listaHit[] = [
                    'Persona' => $cadenaBD,
                    'RFC' => $registro->RFC ?? '',
                    'CURP' => $registro->CURP ?? '',
                    'fechaNac' => $registro->FechaNacimiento ?? '',
                    'fechaPubAcuerdo' => $registro->FechaPubAcuerdo ?? '',
                    'acuerdo' => $registro->Acuerdo ?? '',
                    'oficioUIF' => $registro->NoOficioUIF ?? '',
                    'anioLista' => $registro->AnioLista ?? '',
                    'porcentaje' => $porcentaje,
                ];
            }
        }

        Log::info('Búsqueda UIF completada', [
            'totalCoincidencias' => count($this->listaHit),
        ]);

        unset($comparador);
    }

    private function analizarRespuesta(): array
    {
        $categoriaPLD = $this->obtieneCategoriaPLD();

        $detalleListaBloqueadas = [];

        foreach ($this->listaHit as $hit) {
            $detalleListaBloqueadas[] = [
                'lista' => 'UIF',
                'nombreDetectado' => $hit['Persona'] ?? '',
                'IDListaOrigen' => 3,
                'rfc' => $hit['RFC'] ?? '',
                'curp' => $hit['CURP'] ?? '',
                'coincidencia' => $hit['porcentaje'] ?? 0,
                'fechaNacimiento' => $hit['fechaNac'] ?? '',
                'fechaPublicacion' => $hit['fechaPubAcuerdo'] ?? '',
                'acuerdo' => $hit['acuerdo'] ?? '',
                'oficioUIF' => $hit['oficioUIF'] ?? '',
                'anioLista' => $hit['anioLista'] ?? '',
            ];
        }

        return [
            'success' => true,
            'personaBloqueada' => $categoriaPLD > 0,
            'IDCategoria' => $categoriaPLD,
            'detalleListaBloqueadas' => $detalleListaBloqueadas,
            'totalCoincidencias' => count($detalleListaBloqueadas),
            'listaHit' => $this->listaHit,
        ];
    }

    private function generarRespuestaVacia(): array
    {
        return [
            'success' => false,
            'personaBloqueada' => false,
            'IDCategoria' => 0,
            'detalleListaBloqueadas' => [],
            'totalCoincidencias' => 0,
            'listaHit' => [],
        ];
    }

    public function obtieneCategoriaPLD(): int
    {
        $totalHit = count($this->listaHit);

        if ($totalHit == 0) {
            $this->IDCategoria = 0;
        } else {
            $this->IDCategoria = 2;
        }

        return $this->IDCategoria;
    }

    public function enviaNotificacionUIF(): void
    {
        try {
            $correos = $this->obtenerCorreosNotificacion();

            if (empty($correos)) {
                Log::warning('No hay correos configurados para notificaciones UIF');

                return;
            }

            $datos = $this->generarNotificacion();

            $data = [
                'typeNotification' => 'MAIL',
                'template' => $datos['template'],
                'listAddress' => $correos,
                'subject' => $datos['subject'],
                'params' => [
                    'user_system' => 'Usuario',
                    'register_person' => htmlentities($datos['person'], ENT_QUOTES, 'UTF-8'),
                    'detection_date' => $datos['date'],
                    'detection_hour' => $datos['hour'],
                    'matches' => $datos['noMatches'],
                    'names_found' => htmlentities($datos['matches'], ENT_QUOTES, 'UTF-8'),
                    'oficios_found' => $datos['listOficios'],
                    'type_sit_person' => htmlentities($datos['catPLD_desc'], ENT_QUOTES, 'UTF-8'),
                    'comments' => htmlentities($datos['comments'], ENT_QUOTES, 'UTF-8'),
                ],
            ];

            // Implementar sistema de notificaciones
            // $notificacion = new NotificationDAO();
            // $notificacion->configureMailNotificaction($data);
            // $notificacion->sendNotification();

            Log::info('Notificación UIF preparada', [
                'totalCorreos' => count($correos),
                'totalCoincidencias' => count($this->listaHit),
            ]);

        } catch (Exception $e) {
            Log::error('Error enviando notificación UIF: '.$e->getMessage());
        }
    }

    private function obtenerCorreosNotificacion(): array
    {
        try {
            $correos = DB::table('sit.catEnvioNotificaciones')
                ->where('Archivo', 'BuscadorUIF.class')
                ->select('Correo', 'Alias')
                ->get();

            return $correos->map(function ($correo) {
                return [
                    'address' => $correo->Correo,
                    'addressAlias' => $correo->Alias,
                ];
            })->toArray();

        } catch (Exception $e) {
            Log::error('Error obteniendo correos de notificación: '.$e->getMessage());

            return [];
        }
    }

    private function generarNotificacion(): array
    {
        $data = [];

        $data['subject'] = 'Aviso SPLD - Persona detectada en listas UIF';
        $data['template'] = 'PLD003';
        $data['person'] = $this->cadenaBuscar;
        $data['date'] = date('d').' de '.$this->parseMonth(date('n')).' de '.date('Y');
        $data['hour'] = date('H:i');
        $data['noMatches'] = count($this->listaHit);
        $data['matches'] = implode(', ', array_column($this->listaHit, 'Persona'));
        $data['listOficios'] = implode(', ', array_column($this->listaHit, 'oficioUIF'));
        $data['catPLD_desc'] = $this->getCategoriaPLD($this->IDCategoria);
        $data['comments'] = $this->getComentariosDeteccion($this->IDCategoria);

        return $data;
    }

    private function parseMonth(int $mes): string
    {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return $meses[$mes] ?? '';
    }

    private function getCategoriaPLD(int $categoria): string
    {
        $categorias = [
            0 => 'Sin coincidencias',
            1 => 'Persona bloqueada con coincidencia de nombre',
            2 => 'Persona bloqueada sin coincidencia exacta de nombre',
            3 => 'PPE con coincidencia de nombre',
            4 => 'PPE sin coincidencia exacta de nombre',
            6 => 'Otra categoría',
        ];

        return $categorias[$categoria] ?? 'Categoría desconocida';
    }

    private function getComentariosDeteccion(int $categoria): string
    {
        $comentarios = [
            0 => 'No se encontraron coincidencias en las listas.',
            1 => 'Se encontró coincidencia exacta en lista de personas bloqueadas.',
            2 => 'Se encontró coincidencia aproximada en lista de personas bloqueadas.',
            3 => 'Se encontró coincidencia exacta en lista de PPE.',
            4 => 'Se encontró coincidencia aproximada en lista de PPE.',
            6 => 'Se encontró en otras listas.',
        ];

        return $comentarios[$categoria] ?? 'Sin comentarios disponibles.';
    }

    public function getListaHit(): array
    {
        return $this->listaHit;
    }

    public function getCadenaBuscar(): string
    {
        return $this->cadenaBuscar;
    }
}
