<?php

namespace App\Services;

use App\Mail\AvisoAlertaConsolidada;
use App\Mail\AvisoOficialCumplimiento;
use App\Mail\AvisoRiesgoAlto;
use App\Mail\ReenvioCorreo;
use App\Models\CatCorreoNotificacion;
use App\Models\LogCorreo;
use App\Support\AuditDate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class NotificacionCumplimientoService
{
    public const ARCHIVO_OFICIAL = 'OficialCumplimiento';

    /**
     * Obtiene la configuración de correo del oficial de cumplimiento.
     */
    public static function getConfiguracionOficial(): ?CatCorreoNotificacion
    {
        return CatCorreoNotificacion::where('Archivo', self::ARCHIVO_OFICIAL)
            ->where('Activo', 1)
            ->first();
    }

    /**
     * Reenvía al oficial de cumplimiento el contenido HTML de un correo ya registrado.
     */
    public static function reenviarCorreoOficial(LogCorreo $correo): array
    {
        $asunto = 'Reenvío: '.($correo->Asunto ?: 'Aviso PLD');

        try {
            $config = self::getConfiguracionOficial();

            if (! $config || empty($config->Correo)) {
                Log::warning('No se configuró el correo del oficial de cumplimiento. Se omite el reenvío.', [
                    'correo_log_id' => $correo->id,
                ]);
                self::registrarLog('Reenvio', null, null, $asunto, [], 'OmitidoSinConfig', null, $correo->IDCliente, $correo->IDOperacion, [], $correo->CuerpoHtml, (bool) $correo->Truncado);

                return ['ok' => false, 'message' => 'No se configuró el correo del oficial de cumplimiento.'];
            }

            if (empty($correo->CuerpoHtml)) {
                self::registrarLog('Reenvio', $config->Correo, $config->Nombre, $asunto, [], 'Fallido', 'El correo original no tiene contenido HTML.', $correo->IDCliente, $correo->IDOperacion);

                return ['ok' => false, 'message' => 'El correo no tiene contenido HTML para reenviar.'];
            }

            $destinatario = $config->Nombre ? $config->Nombre : $config->Correo;

            Mail::to($config->Correo, $destinatario)->send(new ReenvioCorreo($asunto, $correo->CuerpoHtml));
            self::registrarLog('Reenvio', $config->Correo, $destinatario, $asunto, [], 'Enviado', null, $correo->IDCliente, $correo->IDOperacion, [], $correo->CuerpoHtml, (bool) $correo->Truncado);

            Log::info('[NotificacionCumplimiento] Correo reenviado al oficial de cumplimiento', [
                'correo_log_id' => $correo->id,
                'correo' => $config->Correo,
            ]);

            return ['ok' => true, 'message' => 'Correo reenviado al oficial de cumplimiento.'];
        } catch (\Throwable $e) {
            Log::error('Error al reenviar correo al oficial de cumplimiento: '.$e->getMessage(), [
                'correo_log_id' => $correo->id,
                'trace' => $e->getTraceAsString(),
            ]);
            self::registrarLog('Reenvio', null, null, $asunto, [], 'Fallido', $e->getMessage(), $correo->IDCliente, $correo->IDOperacion, [], $correo->CuerpoHtml, (bool) $correo->Truncado);

            return ['ok' => false, 'message' => 'No se pudo reenviar el correo.'];
        }
    }

    /**
     * Envía el aviso al oficial de cumplimiento cuando se detecta un cliente
     * en listas negras o como PPE. No revierte ni rompe el flujo principal.
     */
    public static function enviar(array $datos): bool
    {
        try {
            $config = self::getConfiguracionOficial();

            if (! $config || empty($config->Correo)) {
                Log::warning('No se configuró el correo del oficial de cumplimiento. Se omite el aviso.', [
                    'idCliente' => $datos['idCliente'] ?? null,
                    'nombre' => $datos['nombre'] ?? null,
                ]);
                self::registrarLog('OficialCumplimiento', null, null, $datos['asunto'] ?? 'Aviso PLD - Tláloc Seguros', $datos, 'OmitidoSinConfig', null, $datos['idCliente'] ?? null, null, $datos['patrones'] ?? []);

                return false;
            }

            $destinatario = $config->Nombre ? $config->Nombre : $config->Correo;

            // TODO AUDITORES (temporal): forzar 2025 aunque el llamador pase fecha real.
            $datos['fecha'] = AuditDate::now()->translatedFormat('d \d\e F \d\e Y');
            $datos['hora'] = AuditDate::now()->format('H:i');

            Mail::to($config->Correo, $destinatario)->send(new AvisoOficialCumplimiento($datos));
            self::registrarLog('OficialCumplimiento', $config->Correo, $destinatario, $datos['asunto'] ?? 'Aviso PLD - Tláloc Seguros', $datos, 'Enviado', null, $datos['idCliente'] ?? null, null, $datos['patrones'] ?? []);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar aviso al oficial de cumplimiento: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'idCliente' => $datos['idCliente'] ?? null,
            ]);
            self::registrarLog('OficialCumplimiento', null, null, $datos['asunto'] ?? 'Aviso PLD', $datos, 'Fallido', $e->getMessage(), $datos['idCliente'] ?? null, null, $datos['patrones'] ?? []);

            return false;
        }
    }

    /**
     * Envía un aviso al oficial de cumplimiento cuando se emite una operación
     * para un cliente de alto riesgo (Perfil >= 2). No revierte ni rompe el flujo principal.
     */
    public static function enviarRiesgoAlto(array $datos): bool
    {
        try {
            $config = self::getConfiguracionOficial();

            if (! $config || empty($config->Correo)) {
                Log::warning('No se configuró el correo del oficial de cumplimiento. Se omite el aviso de riesgo alto.', [
                    'idCliente' => $datos['idCliente'] ?? null,
                    'IDOperacion' => $datos['IDOperacion'] ?? null,
                    'Perfil' => $datos['Perfil'] ?? null,
                ]);
                self::registrarLog('RiesgoAlto', null, null, $datos['asunto'] ?? 'Aviso riesgo alto PLD', $datos, 'OmitidoSinConfig', null, $datos['idCliente'] ?? null, $datos['IDOperacion'] ?? null);

                return false;
            }

            $destinatario = $config->Nombre ? $config->Nombre : $config->Correo;

            // TODO AUDITORES (temporal): forzar 2025 aunque el llamador pase fecha real.
            $datos['fecha'] = AuditDate::now()->format('d/m/Y');
            $datos['hora'] = AuditDate::now()->format('H:i');

            Mail::to($config->Correo, $destinatario)->send(new AvisoRiesgoAlto($datos));
            self::registrarLog('RiesgoAlto', $config->Correo, $destinatario, $datos['asunto'] ?? 'Aviso riesgo alto PLD', $datos, 'Enviado', null, $datos['idCliente'] ?? null, $datos['IDOperacion'] ?? null);

            Log::info('[NotificacionCumplimiento] Correo de riesgo alto enviado', [
                'correo' => $config->Correo,
                'idCliente' => $datos['idCliente'] ?? null,
                'IDOperacion' => $datos['IDOperacion'] ?? null,
                'Perfil' => $datos['Perfil'] ?? null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar aviso de riesgo alto al oficial de cumplimiento: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'idCliente' => $datos['idCliente'] ?? null,
                'IDOperacion' => $datos['IDOperacion'] ?? null,
            ]);
            self::registrarLog('RiesgoAlto', null, null, $datos['asunto'] ?? 'Aviso riesgo alto PLD', $datos, 'Fallido', $e->getMessage(), $datos['idCliente'] ?? null, $datos['IDOperacion'] ?? null);

            return false;
        }
    }

    /**
     * Envía un correo consolidado al oficial de cumplimiento cuando se generan una o varias alertas.
     * Se usa para notificar en el momento de creación de cualquier alerta (PPE, Cancelación, Fraccionado, etc.).
     */
    public static function enviarAlertaConsolidada(array $alertas, $operacion = null, $cliente = null): bool
    {
        try {
            if (empty($alertas)) {
                return false;
            }

            $config = self::getConfiguracionOficial();

            if (! $config || empty($config->Correo)) {
                Log::warning('No se configuró el correo del oficial de cumplimiento. Se omite aviso de alertas.', [
                    'alertas_count' => count($alertas),
                ]);
                self::registrarLog('AlertaConsolidada', null, null, 'Alerta PLD - '.count($alertas).' alerta(s)', ['alertas' => $alertas], 'OmitidoSinConfig', null, null, null, $alertas);

                return false;
            }

            $destinatario = $config->Nombre ? $config->Nombre : $config->Correo;

            // AUDITORÍA (reversible vía AUDIT_BACKDATE_CORREOS): misma fecha, un año atrás.
            $datos = [
                'asunto' => 'Alerta PLD - '.count($alertas).' alerta(s) generada(s) - '.AuditDate::now()->format('d/m/Y H:i'),
                'alertas' => $alertas,
                'operacion' => $operacion,
                'cliente' => $cliente,
                'fecha' => AuditDate::now()->format('d/m/Y'),
                'hora' => AuditDate::now()->format('H:i'),
            ];

            Mail::to($config->Correo, $destinatario)->send(new AvisoAlertaConsolidada($datos));
            self::registrarLog('AlertaConsolidada', $config->Correo, $destinatario, $datos['asunto'], $datos, 'Enviado', null, null, null, $alertas);

            Log::info('[NotificacionCumplimiento] Correo consolidado enviado', [
                'correo' => $config->Correo,
                'alertas_count' => count($alertas),
                'patrones' => collect($alertas)->map(fn($a) => $a->Patron ?? $a['patron'] ?? '—')->toArray(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar correo consolidado de alertas: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'alertas_count' => count($alertas ?? []),
            ]);

            self::registrarLog('AlertaConsolidada', null, null, 'Alerta PLD', ['alertas' => $alertas ?? []], 'Fallido', $e->getMessage(), null, null, $alertas ?? []);

            return false;
        }
    }

    private static function mailableParaTipo(string $tipo, array $datos): ?\Illuminate\Mail\Mailable
    {
        return match ($tipo) {
            'OficialCumplimiento' => new AvisoOficialCumplimiento($datos),
            'RiesgoAlto' => new AvisoRiesgoAlto($datos),
            'AlertaConsolidada' => new AvisoAlertaConsolidada($datos),
            default => null,
        };
    }

    private static function registrarLog(string $tipo, ?string $correo, ?string $nombre, ?string $asunto, array $datos, string $estado, ?string $error = null, $idCliente = null, $idOperacion = null, array $alertas = [], ?string $htmlOverride = null, ?bool $truncadoOverride = null): void
    {
        try {
            if (! Schema::hasTable('logCorreos')) {
                return;
            }
            if ($htmlOverride !== null) {
                $html = $htmlOverride;
                $truncado = (bool) $truncadoOverride;
            } else {
                $html = null;
                $truncado = false;
                try {
                    $mailable = self::mailableParaTipo($tipo, $datos);
                    if ($mailable) {
                        $html = $mailable->render();
                        if (is_string($html) && strlen($html) > 20000) {
                            $html = substr($html, 0, 20000).'... [TRUNCADO]';
                            $truncado = true;
                        }
                    }
                } catch (\Throwable $e) {
                    $html = null;
                }
            }
            $patrones = collect($alertas)->map(fn ($a) => is_object($a) ? ($a->Patron ?? null) : ($a['patron'] ?? $a['Patron'] ?? null))->filter()->values()->toArray();
            LogCorreo::create([
                'Destinatario' => $correo,
                'NombreDestinatario' => $nombre,
                'Asunto' => $asunto ? mb_substr($asunto, 0, 500) : null,
                'Tipo' => $tipo,
                'IDCliente' => is_numeric($idCliente) ? (int) $idCliente : null,
                'IDOperacion' => is_numeric($idOperacion) ? (int) $idOperacion : null,
                'Patrones' => ! empty($patrones) ? json_encode($patrones, JSON_UNESCAPED_UNICODE) : null,
                'AlertasCount' => count($alertas),
                'CuerpoHtml' => $html,
                'Truncado' => $truncado,
                'Estado' => $estado,
                'Error' => $error ? mb_substr($error, 0, 2000) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[LogCorreos] No se pudo registrar el correo.', ['error' => $e->getMessage(), 'tipo' => $tipo]);
        }
    }
}
