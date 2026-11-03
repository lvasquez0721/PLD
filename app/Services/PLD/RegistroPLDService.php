<?php

namespace App\Services\PLD;

use App\Http\Controllers\Helpers\ClienteHelper;
use App\Models\CatCategoriaPersonasBloqueadas;
use App\Models\Clientes\LogDetectClientesListas;
use App\Models\Clientes\TbClientes;
use App\Models\Clientes\TbClientesPPE;
use App\Models\TbAlertas;
use App\Services\ListasNegras\AnalizadorListasNegras;
use App\Services\ListasNegras\DetectorPPE;
use App\Services\NotificacionCumplimientoService;
use App\Services\ParametriaPLD\ParametriaPLDService;
use App\Support\AuditDate;
use Illuminate\Support\Facades\Log;

class RegistroPLDService
{
    /**
     * Analiza listas, PPE y factores de riesgo, persiste los resultados y genera alertas.
     *
     * @param  array<int, array<string, mixed>>  $domicilios
     * @return array<string, mixed>
     */
    public function analizarYRegistrar(
        TbClientes $persona,
        array $domicilios,
        string $estatusLista = 'Generado',
        string $origen = 'PLD'
    ): array {
        $analisis = app(AnalizadorListasNegras::class)->analizar([
            'IDTipoPersona' => $persona->IDTipoPersona,
            'RFC' => $persona->RFC,
            'CURP' => $persona->CURP,
            'nombre' => $persona->Nombre,
            'apellidoPaterno' => $persona->ApellidoPaterno,
            'apellidoMaterno' => $persona->ApellidoMaterno,
            'razonSocial' => $persona->RazonSocial,
        ]);

        $personaBloqueada = (bool) ($analisis['personaBloqueada'] ?? false);
        $detalleListaBloqueadas = $analisis['detalleListaBloqueadas'] ?? [];
        $listasDetectadas = $analisis['listasDetectadas'] ?? [];

        [$esPPE, $dataPPE] = $this->obtenerDatosPPE($this->nombreParaBusqueda($persona));

        if ($esPPE) {
            $detalleListaBloqueadas = array_merge(
                $detalleListaBloqueadas,
                $this->construirDetallesPPE($dataPPE)
            );
        }

        $datosRiesgo = app(EvaluadorRiesgoCliente::class)->evaluar(
            $persona->IDOcupacionGiro,
            $domicilios,
            $personaBloqueada,
            $esPPE
        );

        $persona->CoincideEnListasNegras = $personaBloqueada;
        $persona->EsPPEActivo = $esPPE;
        $persona->EsAltoRiesgo = (bool) ($datosRiesgo['esAltoRiesgo'] ?? false);
        $persona->save();

        $alertasGeneradas = [];

        if ($personaBloqueada) {
            $motivo = 'Detectado en listas negras: '.implode(', ', $listasDetectadas);

            $alertasGeneradas[] = TbAlertas::create([
                'Folio' => null,
                'Patron' => 'Personas Bloqueadas',
                'IDCliente' => $persona->IDCliente,
                'Cliente' => TbAlertas::nombreParaCliente($persona),
                'Poliza' => null,
                'FechaDeteccion' => now()->toDateString(),
                'HoraDeteccion' => now()->toTimeString(),
                'FechaOperacion' => null,
                'HoraOperacion' => null,
                'MontoOperacion' => null,
                'InstrumentoMonetario' => null,
                'RFCAgente' => null,
                'Agente' => null,
                'Estatus' => $estatusLista,
                'Descripcion' => $motivo,
                'Razones' => "El registro con RFC {$persona->RFC} coincide en: ".implode(', ', $listasDetectadas),
                'Evidencias' => '',
                'IDReporteOP' => null,
            ]);
        }

        foreach ($datosRiesgo['alertas'] ?? [] as $alertaRiesgo) {
            $alertasGeneradas[] = $this->crearAlertaCliente(
                $persona,
                $alertaRiesgo['patron'],
                $alertaRiesgo['descripcion'],
                $alertaRiesgo['razones']
            );
        }

        foreach ($detalleListaBloqueadas as $deteccion) {
            LogDetectClientesListas::create([
                'IDCliente' => $persona->IDCliente,
                'Lista' => $deteccion['lista'] ?? $deteccion['fuente'] ?? 'Desconocida',
                'NombreDetectado' => $deteccion['nombreDetectado'] ?? $deteccion['Nombre'] ?? null,
                'Origen' => $origen,
                'TimeStampDeteccion' => now(),
            ]);
        }

        if ($esPPE) {
            TbClientesPPE::create([
                'IDCliente' => $persona->IDCliente,
                'Lista' => 'PPE',
                'Cargo' => null,
                'Estado' => 'Detectado',
                'FechaDeteccion' => now()->toDateString(),
                'Origen' => $origen,
                'TimeStampRegistro' => now(),
            ]);
        }

        return [
            'personaBloqueada' => $personaBloqueada,
            'esPPE' => $esPPE,
            'esAltoRiesgo' => (bool) ($datosRiesgo['esAltoRiesgo'] ?? false),
            'factoresRiesgo' => $datosRiesgo['factores'] ?? [],
            'listasDetectadas' => $listasDetectadas,
            'detalleListaBloqueadas' => $detalleListaBloqueadas,
            'alertasGeneradas' => $alertasGeneradas,
        ];
    }

    /**
     * Envía el aviso al oficial de cumplimiento cuando hubo detecciones.
     *
     * @param  array<string, mixed>  $resultado
     */
    public function notificar(TbClientes $persona, array $resultado): void
    {
        $esPPE = (bool) ($resultado['esPPE'] ?? false);
        $alertasGeneradas = $resultado['alertasGeneradas'] ?? [];

        if (empty($alertasGeneradas) && ! $esPPE) {
            return;
        }

        try {
            $this->enviarAvisoCumplimiento($persona, $resultado);
        } catch (\Throwable $e) {
            Log::warning('Error al enviar aviso de cumplimiento: '.$e->getMessage());
        }
    }

    private function nombreParaBusqueda(TbClientes $persona): string
    {
        $nombre = trim(implode(' ', array_filter([
            $persona->Nombre,
            $persona->ApellidoPaterno,
            $persona->ApellidoMaterno,
        ])));

        return $nombre !== '' ? $nombre : trim((string) $persona->RazonSocial);
    }

    /**
     * @return array{0: bool, 1: ?array}
     */
    private function obtenerDatosPPE(string $nombre): array
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return [false, null];
        }

        try {
            $porcentajePPE = (float) ParametriaPLDService::get('percentMatchPPE', DetectorPPE::COINCIDENCIA_MINIMA);
            $tokenPPE = ClienteHelper::getTokenPPE();

            if (empty($tokenPPE)) {
                Log::warning('No se pudo obtener token para búsqueda PPE.', ['nombre' => $nombre]);

                return [false, null];
            }

            $dataPPE = ClienteHelper::getPPE($tokenPPE, $nombre, $porcentajePPE);

            if (! is_array($dataPPE)) {
                Log::warning('Respuesta inválida de Q-Detect para búsqueda PPE.', [
                    'nombre' => $nombre,
                    'percent' => $porcentajePPE,
                ]);

                return [false, $dataPPE];
            }

            $detectorPPE = app(DetectorPPE::class);
            $coincidenciasPPE = $detectorPPE->filtrar($dataPPE, $porcentajePPE);
            $dataPPE['data'] = $coincidenciasPPE;

            Log::info('Búsqueda PPE en Q-Detect completada.', [
                'nombre' => $nombre,
                'percent' => $porcentajePPE,
                'totalCoincidencias' => count($coincidenciasPPE),
            ]);

            return [$coincidenciasPPE !== [], $dataPPE];
        } catch (\Throwable $e) {
            Log::warning('Error al consultar PPE: '.$e->getMessage(), ['nombre' => $nombre]);

            return [false, null];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function construirDetallesPPE(?array $dataPPE): array
    {
        if (empty($dataPPE['data']) || ! is_array($dataPPE['data'])) {
            return [];
        }

        $detalles = [];
        $detectorPPE = app(DetectorPPE::class);

        foreach ($dataPPE['data'] as $registro) {
            $nombre = $registro['NOMBRECOMP'] ?? ($registro['NOMBRE'] ?? '');

            $detalles[] = [
                'lista' => $registro['LISTA'] ?? 'PPE',
                'fuente' => 'PPE',
                'nombreDetectado' => $nombre,
                'Nombre' => $nombre,
                'IDListaOrigen' => 1,
                'rfc' => $registro['RFC'] ?? '',
                'RFC' => $registro['RFC'] ?? '',
                'curp' => '',
                'CURP' => '',
                'cargo' => $registro['PUESTO'] ?? '',
                'estado' => $registro['ENTIDAD'] ?? '',
                'estatus' => $registro['ESTATUS'] ?? '',
                'PPEActivo' => $detectorPPE->estaActivo($registro),
                'metodo' => 'PPE',
                'coincidencia' => 100,
            ];
        }

        return $detalles;
    }

    private function crearAlertaCliente(
        TbClientes $persona,
        string $patron,
        string $descripcion,
        string $razones
    ): TbAlertas {
        return TbAlertas::create([
            'Folio' => null,
            'Patron' => $patron,
            'IDCliente' => $persona->IDCliente,
            'Cliente' => TbAlertas::nombreParaCliente($persona),
            'Poliza' => null,
            'FechaDeteccion' => now()->toDateString(),
            'HoraDeteccion' => now()->toTimeString(),
            'FechaOperacion' => null,
            'HoraOperacion' => null,
            'MontoOperacion' => null,
            'InstrumentoMonetario' => null,
            'RFCAgente' => null,
            'Agente' => null,
            'Estatus' => 'Generado',
            'Descripcion' => $descripcion,
            'Razones' => $razones,
            'Evidencias' => '',
            'IDReporteOP' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function enviarAvisoCumplimiento(TbClientes $persona, array $resultado): void
    {
        $esPPE = (bool) ($resultado['esPPE'] ?? false);
        $esAltoRiesgo = (bool) ($resultado['esAltoRiesgo'] ?? false);
        $detalleListaBloqueadas = $resultado['detalleListaBloqueadas'] ?? [];
        $listasDetectadas = $resultado['listasDetectadas'] ?? [];
        $alertasGeneradas = $resultado['alertasGeneradas'] ?? [];
        $factoresRiesgo = $resultado['factoresRiesgo'] ?? [];

        $nombreCompleto = $this->nombreParaBusqueda($persona);
        $tipo = (int) $persona->IDTipoPersona === 2 ? 'empresa' : 'persona';

        $nombresDetectados = [];
        $listasDetectadasFinal = [];

        foreach ($detalleListaBloqueadas as $deteccion) {
            $nombreDetectado = $deteccion['Nombre'] ?? $deteccion['nombreDetectado'] ?? '';
            if (! empty($nombreDetectado)) {
                $nombresDetectados[] = $nombreDetectado;
            }

            $lista = $deteccion['fuente'] ?? $deteccion['lista'] ?? '';
            if (! empty($lista)) {
                $listasDetectadasFinal[] = $lista;
            }
        }

        if (empty($listasDetectadasFinal)) {
            $listasDetectadasFinal = $listasDetectadas;
        }

        $idCategoria = $esPPE ? 4 : 2;

        $patrones = [];
        foreach ($alertasGeneradas as $alerta) {
            $patrones[] = [
                'patron' => $alerta->Patron ?? '—',
                'descripcion' => $alerta->Descripcion ?? '—',
            ];
        }

        if ($esPPE && empty(array_filter($patrones, fn ($p) => $p['patron'] === 'PPE'))) {
            $patrones[] = [
                'patron' => 'PPE',
                'descripcion' => 'Persona políticamente expuesta detectada en la consulta de Q-Detect.',
            ];
        }

        $asunto = $esAltoRiesgo
            ? 'Aviso de alertamiento PLD - sujeto de alto riesgo - '.$nombreCompleto
            : 'Aviso de detección en listas - PLD - '.$nombreCompleto;

        NotificacionCumplimientoService::enviar([
            'asunto' => $asunto,
            'idCliente' => $persona->IDCliente,
            'nombre' => $nombreCompleto ?: '—',
            'tipo' => $tipo,
            'fecha' => AuditDate::now()->translatedFormat('d \d\e F \d\e Y'),
            'hora' => AuditDate::now()->format('H:i'),
            'registrosEncontrados' => count($detalleListaBloqueadas),
            'nombresDetectados' => implode(', ', array_unique($nombresDetectados)),
            'listasDetectadas' => implode(', ', array_unique($listasDetectadasFinal)),
            'categoria' => $this->categoriaTexto($idCategoria),
            'observaciones' => CatCategoriaPersonasBloqueadas::getComentariosDeteccion($idCategoria),
            'esPPE' => $esPPE,
            'patrones' => $patrones,
            'esAltoRiesgo' => $esAltoRiesgo,
            'factoresRiesgo' => array_values($factoresRiesgo),
            'urlDetalleCliente' => $this->urlDetalle($persona),
        ]);
    }

    private function categoriaTexto(int $idCategoria): string
    {
        try {
            $categoria = CatCategoriaPersonasBloqueadas::where('IDCategoria', $idCategoria)->first();
            if ($categoria && ! empty($categoria->Categoria)) {
                return $idCategoria.': '.$categoria->Categoria;
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo leer catCategoriaPersonasBloqueadas: '.$e->getMessage());
        }

        $fallback = [
            2 => 'Persona / Empresa aparece en listas bloqueadas, necesita revisión',
            4 => 'Persona detectada en listas políticamente expuestas, necesita revisión',
        ];

        return $idCategoria.': '.($fallback[$idCategoria] ?? 'Necesita revisión');
    }

    private function urlDetalle(TbClientes $persona): string
    {
        $ruta = match ($persona->TipoPersona) {
            TbClientes::TIPO_AGENTE => 'agentes.ver-detalles',
            TbClientes::TIPO_COLABORADOR => 'colaboradores.ver-detalles',
            default => 'clientes.ver-detalles',
        };

        try {
            return route($ruta, ['id_cliente' => $persona->IDCliente]);
        } catch (\Throwable $e) {
            $base = match ($persona->TipoPersona) {
                TbClientes::TIPO_AGENTE => '/agentes',
                TbClientes::TIPO_COLABORADOR => '/colaboradores',
                default => '/clientes',
            };

            return url($base.'/ver-detalles/'.$persona->IDCliente);
        }
    }
}
