<?php

namespace App\Services\ListasNegras\QeQ;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\ParametriaPLD\ParametriaPLDService;
use Illuminate\Support\Facades\DB;
use App\Models\CatCategoriaPersonasBloqueadas;
use Exception;

class QeQ {
    private string $urlAuth;
    private string $urlQry;
    private string $clientID;
    private string $username;
    private string $tokenAPI;
    private float $percentMatch;

    private ?int $tipoPersona = null;
    private string $rfc = '';
    private string $nombre = '';
    private string $apaterno = '';
    private string $amaterno = '';
    private string $razonSocial = '';
    private string $archivoEvidencia = '';
    private array $listaObtenidas = [];
    private array $listasBloqueadasCorreo = [];
    private array $matchCorreo = [];
    private int $IDCategoria = -1;
    private array $listasBloqueadas = [];

    public function __construct() {
        try {
            $this->urlAuth   = (string) ParametriaPLDService::get('urlAuthQeQ', '');
            $this->urlQry    = (string) ParametriaPLDService::get('urlQryQeQ', '');
            $this->clientID  = (string) ParametriaPLDService::get('clientIDQeQ', '');
            $this->username  = (string) ParametriaPLDService::get('usernameQeQ', '');
            $this->tokenAPI  = (string) ParametriaPLDService::get('tokenAPIQeQ', '');
            $this->percentMatch = (float) ParametriaPLDService::get('percentMatchQeQ', 80);
        } catch (Exception $e) {
            Log::error("Error al inicializar QeQ: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function reiniciaValores(): void {
        $this->tipoPersona = null;
        $this->rfc = '';
        $this->nombre = '';
        $this->apaterno = '';
        $this->amaterno = '';
        $this->razonSocial = '';
        $this->listaObtenidas = [];
        $this->listasBloqueadasCorreo = [];
        $this->matchCorreo = [];
        $this->IDCategoria = -1;
    }

    // Obtiene token temporal
    private function autenticar(): ?string {
            $url = $this->urlAuth . '?client_id=' . urlencode($this->clientID);
                        
            $response = Http::withoutVerifying()
                ->withToken($this->tokenAPI, 'Bearer')
                ->timeout(10)
                ->get($url);
            
            if (!$response->successful()) {
                Log::error("Error HTTP en autenticación QeQ", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $body = trim($response->body());

            if (strpos($body, '|') === false) {
                Log::error("Formato de token inválido", ['body' => $body]);
                return null;
            }
        
            $parts = explode('|', $body);
            $tokenTemporal = trim($parts[1]);

            if (empty($tokenTemporal)) {
                Log::error("Token temporal vacío", ['body' => $body]);
                return null;
            }

            Log::debug("Token temporal obtenido", [
                'length' => strlen($tokenTemporal),
                'preview' => substr($tokenTemporal, 0, 20) . '...'
            ]);

            return $tokenTemporal;
    }

    //Cosume la API de QeQ para buscar coincidencias
    public function ejecutarConsulta(array $params): array {
            $this->reiniciaValores();
            $this->setParametros($params);

            // Obtener token temporal
            $tokenTemporal = $this->autenticar();
            if (!$tokenTemporal) {
                return ['error' => 'No se pudo autenticar con QeQ'];
            }

            $queryParams = $this->construirParametrosBusqueda();
            $urlConsulta = $this->urlQry . '?' . http_build_query($queryParams);

            $response = Http::withoutVerifying()
                ->withToken($tokenTemporal, 'Bearer')
                ->timeout(30)
                ->get($urlConsulta);

            if (!$response->successful()) {
                return ['error' => 'Error consultando QeQ: HTTP ' . $response->status()];
            }

            $body = $response->body();    
            $bodyCorregido = str_replace('\/', '/', $body);
            
            $data = json_decode($bodyCorregido, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("Respuesta no es JSON válido", [
                    'body' => $body,
                    'error' => json_last_error_msg()
                ]);
                $data = ['raw_response' => $body];
            }

            // Guardar evidencia
            $this->guardarEvidencia($data);
            $this->listaObtenidas = $this->analizaRespuestaQeQ($data);
            
            Log::info("Consulta QeQ completada exitosamente", [
                'evidencia' => $this->archivoEvidencia,
                'resultados' => isset($data['data']) ? count($data['data']) : 0
            ]);

            return $this->analizarRespuesta($data);
    }

    //Construye los parámetros de búsqueda según el tipo de persona
    private function construirParametrosBusqueda(): array {
        if ($this->tipoPersona === 1) {
            $nameQuery = trim($this->nombre . ' ' . $this->apaterno . ' ' . $this->amaterno);
        } else {
            $nameQuery = $this->razonSocial;
        }
        
        // Parámetros base
        $queryParams = [
            'client_id' => $this->clientID,
            'username'  => $this->username,
            'name'      => $nameQuery,
            'percent'   => $this->percentMatch
        ];

        $rfcsGenericos = ['XAXX010101000', 'XEXX010101000', 'XAXC010101000'];
        if ($this->rfc && !in_array($this->rfc, $rfcsGenericos)) {
            $queryParams['rfc'] = $this->rfc;
        }
        
        return $queryParams;
    }


    // Establece los parámetros de búsqueda
    private function setParametros(array $data): void {
        $this->tipoPersona = $data['tipoPersona'] ?? null;
        $this->rfc         = trim($data['rfc'] ?? '');
        $this->nombre      = trim($data['nombre'] ?? '');
        $this->apaterno    = trim($data['apaterno'] ?? '');
        $this->amaterno    = trim($data['amaterno'] ?? '');
        $this->razonSocial = trim($data['razonSocial'] ?? '');
    }

    // Guarda el json cómo evidencia de la consulta
    private function guardarEvidencia(array $data): void {
            $timestamp = date('Ymd_His');
            $rfcPart = $this->rfc ?: 'sin_rfc';

            if ($this->tipoPersona === 1) {
                $nombrePart = substr($this->nombre . '_' . $this->apaterno, 0, 50);
            } else {
                $nombrePart = substr($this->razonSocial, 0, 50);
            }

            $nombrePart = $this->normaliza($nombrePart);
            
            $directorio = storage_path('app/evidencias');
            
            if (!file_exists($directorio)) {
                mkdir($directorio, 0755, true);
            }
            
            $fileName = "QeQ_{$rfcPart}_{$nombrePart}_{$timestamp}.json";
            $rutaCompleta = $directorio . DIRECTORY_SEPARATOR . $fileName;
            
            $contenido = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (strpos($contenido, '\\/') !== false) {
                $contenido = str_replace('\\/', '/', $contenido);
            }
            
            $resultado = file_put_contents($rutaCompleta, $contenido);
            
            if ($resultado !== false) {
                $this->archivoEvidencia = "evidencias/{$fileName}";
            }
            
    }

    private function normaliza(string $string): string {
        $string = trim($string);

        $replacements = [
            'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','Á'=>'A','À'=>'A','Â'=>'A','Ä'=>'A',
            'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','Í'=>'I','Ì'=>'I','Ï'=>'I','Î'=>'I',
            'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','Ó'=>'O','Ò'=>'O','Ö'=>'O','Ô'=>'O',
            'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
            'ñ'=>'n','Ñ'=>'N','ç'=>'c','Ç'=>'C'
        ];

        $string = strtr($string, $replacements);
        $string = str_replace(',', '', $string);
        return preg_replace('/[^A-Za-z0-9_]/', '_', $string);
    }

    public function analizaRespuestaQeQ($respuestaListas): array {
        $listaObtenidas = [];

        if (!isset($respuestaListas['data']) || !is_array($respuestaListas['data'])) {
            return $listaObtenidas;
        }

        foreach ($respuestaListas['data'] as $registro) {
            $listaObtenidas[] = [
                'lista' => $registro['LISTA'] ?? null,
                'estatus' => $registro['ESTATUS'] ?? null,
                'rfc' => $registro['RFC'] ?? null,
                'nombre' => $registro['NOMBRECOMP'] ?? ($registro['NOMBRE'] ?? null),
                'cargo' => $registro['PUESTO'] ?? null,
                'estado' => $registro['ENTIDAD'] ?? null,
            ];
        }

        return $listaObtenidas;
    }
    
    public function analizarRespuesta(array $data): array {
        if (empty($this->listaObtenidas) && isset($data['data'])) {
            $this->listaObtenidas = $this->analizaRespuestaQeQ($data);
        }

        $categoriaPLD = $this->obtieneCategoriaPLD();

        $detalleListaBloqueadas = [];
        $hayPPE = false;

        if (!empty($this->listaObtenidas)) {
            foreach ($this->listaObtenidas as $registro) {
                $lista = $registro['lista'] ?? '';
                $estatus = $registro['estatus'] ?? '';
                $nombre = $registro['nombre'] ?? '';
                $cargo = $registro['cargo'] ?? '';
                $estado = $registro['estado'] ?? '';
                $esPPE = ($lista === 'PPE');
                $ppeActivo = $esPPE && in_array($estatus, ['Activo', 'ACTIVO', 'activo']);

                if ($esPPE) {
                    $hayPPE = true;
                }

                $detalleListaBloqueadas[] = [
                    'lista' => $lista,
                    'nombreDetectado' => $nombre,
                    'IDListaOrigen' => '1',
                    'cargo' => $cargo,
                    'estado' => $estado,
                    'PPEActivo' => $ppeActivo,
                    'estatus' => $estatus,
                    'rfc' => $registro['rfc'] ?? '',
                ];

                Log::info("Detalle de lista bloqueada procesada", [
                    'lista' => $lista,
                    'nombreDetectado' => $nombre,
                    'IDListaOrigen' => '1',
                    'cargo' => $cargo,
                    'estado' => $estado,
                    'PPEActivo' => $ppeActivo,
                    'estatus' => $estatus,
                    'rfc' => $registro['rfc'] ?? '',
                ]);
            }
        }

        $esPPE = $hayPPE;

        $personaBloqueada = in_array($categoriaPLD['IDCategoria'], [1, 2]);

        $resultado = [
            'success' => true,
            'esPPE' => $esPPE,
            'personaBloqueada' => $personaBloqueada,
            'detalleListaBloqueadas' => $detalleListaBloqueadas,
            'totalCoincidencias' => count($detalleListaBloqueadas),
            'archivoEvidencia' => $this->archivoEvidencia,
            'IDCategoria' => $categoriaPLD['IDCategoria'],
            // 'listasBloquedasCorreo' => $categoriaPLD['listasBloquedasCorreo'],
            'matchCorreo' => $categoriaPLD['matchCorreo'],
        ];

        Log::info("Análisis de respuesta QeQ completado", [
            'esPPE' => $resultado['esPPE'],
            'personaBloqueada' => $resultado['personaBloqueada'],
            'IDCategoria' => $resultado['IDCategoria'],
            'totalCoincidencias' => $resultado['totalCoincidencias']
        ]);

        return $resultado;
    }

    public function getArchivoEvidencia(): string {
        return $this->archivoEvidencia;
    }

    public function getListaObtenidas(): array {
        return $this->listaObtenidas;
    }

    private function generarNotificacionPersona() {
        $data = array ();

        // Persona Física
        if ($this->tipoPersona == 1){
            $data['persona'] = trim($this->nombre . ' ' . $this->apaterno . ' ' . $this->amaterno);
            $data['subject'] = 'Aviso SPLD - Persona detectada en listas Quién es Quién';
            $data['template'] = 'BienvenidaMail';
        // Persona Moral
        } else if ($this->tipoPersona == 2){
            $data['persona'] = trim($this->razonSocial);
            $data['subject'] = 'Aviso SPLD - Empresa detectada en listas Quién es Quién';
            $data['template'] = 'BienvenidaMail';
        }

        $data['list'] = implode(',', $this->matchCorreo);
        $data['noMatches'] = count($this->matchCorreo);
        $data['matches'] = implode(", ", $this->listasBloqueadasCorreo);
        $data['date'] = date('d'). 'de' . date('m'). 'de'. date('Y');
        $data['hour'] = date('H:i');
        $data['catPLD_desc'] = CatCategoriaPersonasBloqueadas::getCategoriaPLD($this->IDCategoria);
	    $data['comments'] = CatCategoriaPersonasBloqueadas::getComentariosDeteccion($this->IDCategoria);

        return $data;
    }

    public function obtieneCategoriaPLD(): array {
        $tam = count($this->listaObtenidas);
        $match_nombre = false;
        $match_PPE = false;
        $match_VENC = false;
        $match_bloqueado = false;

        $this->listasBloqueadasCorreo ??= [];
        $this->matchCorreo ??= [];
        $this->IDCategoria = -1;

        if ($tam === 0) {
            $this->IDCategoria = 0;
        } else {
            foreach ($this->listaObtenidas as $registro) {
                $this->listasBloqueadasCorreo[] = $registro['nombre'] ?? '';
                $this->matchCorreo[] = $registro['lista'] ?? '';

                $lista = $registro['lista'] ?? '';
                $estatus = $registro['estatus'] ?? '';

                if ($lista === 'PPE' && in_array($estatus, ['Activo', 'Inactivo'])) {
                    $match_PPE = true;
                }

                if ($estatus === 'VENC') {
                    $match_VENC = true;
                }
            }

            $listasUnicas = array_unique(array_filter(array_column($this->listaObtenidas, 'lista')));
            
            if (!empty($listasUnicas)) {
                try {
                    $count = DB::table('catlistasnotificaqeq')
                        ->whereIn('Lista', $listasUnicas)
                        ->count();
                    
                    if ($count > 0) {
                        $match_bloqueado = true;
                    }
                } catch (Exception $e) {
                    Log::error("Error consultando catlistasnotificaqeq: " . $e->getMessage());
                }
            }

            // Determinar categoría
            if(!$match_nombre && !$match_PPE && !$match_VENC && !$match_bloqueado) {
                $this->IDCategoria = 0;
            }
            if($match_VENC && !$match_PPE && $this->IDCategoria === -1) {
                $this->IDCategoria = 0;
            }
            if($match_nombre && $match_bloqueado && $this->IDCategoria === -1) {
                $this->IDCategoria = 1;
            }
            if(!$match_nombre && $match_bloqueado && $this->IDCategoria === -1) {
                $this->IDCategoria = 2;
            }
            if($match_nombre && $match_PPE && $this->IDCategoria === -1) {
                $this->IDCategoria = 3;
            }
            if(!$match_nombre && $match_PPE && $this->IDCategoria === -1) {
                $this->IDCategoria = 4;
            }
            if($this->IDCategoria == -1 && $tam > 0) {
                $this->IDCategoria = 6;
            }
        }

        return [
            'IDCategoria' => $this->IDCategoria,
            'listasBloqueadasCorreo' => $this->listasBloqueadasCorreo,
            'matchCorreo' => $this->matchCorreo,
            'archivoEvidencia' => $this->archivoEvidencia,
        ];
    }
}