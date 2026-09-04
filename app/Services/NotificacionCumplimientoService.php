<?php

namespace App\Services;

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
}
