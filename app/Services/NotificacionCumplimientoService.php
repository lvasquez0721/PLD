<?php

namespace App\Services;

use App\Mail\AvisoAlertaConsolidada;
use App\Mail\AvisoOficialCumplimiento;
use App\Models\CatCorreoNotificacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

                return false;
            }

            $destinatario = $config->Nombre ? $config->Nombre : $config->Correo;

            Mail::to($config->Correo, $destinatario)->send(new AvisoOficialCumplimiento($datos));

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar aviso al oficial de cumplimiento: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'idCliente' => $datos['idCliente'] ?? null,
            ]);

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

                return false;
            }

            $destinatario = $config->Nombre ? $config->Nombre : $config->Correo;

            $datos = [
                'asunto' => 'Alerta PLD - '.count($alertas).' alerta(s) generada(s) - '.now()->format('d/m/Y H:i'),
                'alertas' => $alertas,
                'operacion' => $operacion,
                'cliente' => $cliente,
                'fecha' => now()->format('d/m/Y'),
                'hora' => now()->format('H:i'),
            ];

            Mail::to($config->Correo, $destinatario)->send(new AvisoAlertaConsolidada($datos));

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

            return false;
        }
    }
}
