<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aviso de Cumplimiento - PLD</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background-color:#1d4ed8;padding:24px 32px;">
                            <h1 style="margin:0;color:#ffffff;font-size:20px;">Aviso de Cumplimiento - PLD</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;color:#334155;font-size:14px;line-height:1.6;">
                                El siguiente cliente presenta coincidencias que requieren su atención como Oficial de Cumplimiento.
                            </p>

                            <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="background-color:#f1f5f9;color:#475569;font-size:13px;font-weight:bold;width:40%;">Nombre</td>
                                    <td style="color:#0f172a;font-size:14px;">{{ $datos['nombre'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="background-color:#f1f5f9;color:#475569;font-size:13px;font-weight:bold;">RFC</td>
                                    <td style="color:#0f172a;font-size:14px;">{{ $datos['rfc'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="background-color:#f1f5f9;color:#475569;font-size:13px;font-weight:bold;">CURP</td>
                                    <td style="color:#0f172a;font-size:14px;">{{ $datos['curp'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="background-color:#f1f5f9;color:#475569;font-size:13px;font-weight:bold;">ID Cliente</td>
                                    <td style="color:#0f172a;font-size:14px;">{{ $datos['idCliente'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="background-color:#f1f5f9;color:#475569;font-size:13px;font-weight:bold;">PPE</td>
                                    <td style="color:#0f172a;font-size:14px;">
                                        {{ ! empty($datos['esPPE']) ? 'Sí' : 'No' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="background-color:#f1f5f9;color:#475569;font-size:13px;font-weight:bold;">Listas detectadas</td>
                                    <td style="color:#0f172a;font-size:14px;">
                                        {{ $datos['listas'] ?? '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="background-color:#f1f5f9;color:#475569;font-size:13px;font-weight:bold;">Fecha</td>
                                    <td style="color:#0f172a;font-size:14px;">{{ $datos['fecha'] ?? now()->format('d/m/Y H:i') }}</td>
                                </tr>
                            </table>

                            @if (! empty($datos['detalles']) && is_array($datos['detalles']))
                                <p style="margin:24px 0 8px;color:#334155;font-size:14px;font-weight:bold;">Detalle de coincidencias:</p>
                                <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
                                    @foreach ($datos['detalles'] as $detalle)
                                        <tr>
                                            <td style="background-color:#f8fafc;color:#475569;font-size:13px;font-weight:bold;width:40%;">{{ $detalle['tabla'] ?? 'Lista' }}</td>
                                            <td style="color:#0f172a;font-size:14px;">{{ $detalle['valor'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 32px;">
                            <p style="margin:0;color:#94a3b8;font-size:12px;">
                                Generado automáticamente por el sistema PLD. No responda a este correo.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
