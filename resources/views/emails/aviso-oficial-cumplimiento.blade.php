<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aviso de Cumplimiento - PLD</title>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#000000;font-size:14px;line-height:1.6;">
    <div style="padding:24px;">
        <p style="margin:0 0 16px;">Estimado Usuario:</p>

        @php
            $patrones = $datos['patrones'] ?? [];
            $esAltoRiesgo = ! empty($datos['esAltoRiesgo']);
            $factoresRiesgo = $datos['factoresRiesgo'] ?? [];
            $registrosEncontrados = (int) ($datos['registrosEncontrados'] ?? 0);
            $urlDetalle = $datos['urlDetalleCliente'] ?? '';
        @endphp

        <p style="margin:0 0 16px;">
            @if ($registrosEncontrados > 0)
                Le informamos que se registró la {{ $datos['tipo'] ?? 'persona' }}
                <strong>{{ $datos['nombre'] ?? '—' }}</strong> en la base de datos de persona del PLD
                y fue detectado en listas de Quién es Quién el {{ $datos['fecha'] ?? '' }} a las {{ $datos['hora'] ?? '' }} hrs.
            @else
                Le informamos que se registró la {{ $datos['tipo'] ?? 'persona' }}
                <strong>{{ $datos['nombre'] ?? '—' }}</strong> en la base de datos de persona del PLD
                y se emitieron alertamientos de PLD el {{ $datos['fecha'] ?? '' }} a las {{ $datos['hora'] ?? '' }} hrs.
            @endif
        </p>

        @if ($esAltoRiesgo)
            <p style="margin:0 0 16px;padding:12px;border:1px solid #d64545;background-color:#fdf2f2;">
                <strong>Se detectó un perfil de alto riesgo</strong> en el cliente
                <strong>{{ $datos['nombre'] ?? '—' }}</strong> (IDCliente {{ $datos['idCliente'] ?? '—' }}).
                @if (! empty($factoresRiesgo))
                    <br>
                    Factores de alto riesgo:
                    <ul style="margin:6px 0 0 18px;padding:0;">
                        @foreach ($factoresRiesgo as $factor)
                            <li>{{ $factor }}</li>
                        @endforeach
                    </ul>
                @endif
            </p>
        @endif

        <p style="margin:0 0 16px;">Información detallada:</p>

        <table role="presentation" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">
            <tr>
                <td style="vertical-align:top;white-space:nowrap;">Nombre registrado en PLD:&nbsp;&nbsp;</td>
                <td style="vertical-align:top;">{{ $datos['nombre'] ?? '—' }}</td>
            </tr>
            <tr>
                <td style="vertical-align:top;white-space:nowrap;">ID Cliente:&nbsp;&nbsp;</td>
                <td style="vertical-align:top;">{{ $datos['idCliente'] ?? '—' }}</td>
            </tr>
            <tr>
                <td style="vertical-align:top;white-space:nowrap;">Perfil de riesgo:&nbsp;&nbsp;</td>
                <td style="vertical-align:top;">
                    @if ($esAltoRiesgo)
                        <strong>Alto riesgo</strong>
                    @else
                        Sin factores de alto riesgo
                    @endif
                </td>
            </tr>
            @if (! empty($patrones))
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Patrones de alertamiento:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">
                        @foreach ($patrones as $patron)
                            {{ $patron['patron'] ?? '—' }}@if (! $loop->last), @endif
                        @endforeach
                    </td>
                </tr>
            @endif
            @if ($registrosEncontrados > 0)
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Registros encontrados:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['registrosEncontrados'] ?? '0' }}</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Nombres detectados:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['nombresDetectados'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Listas detectadas:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['listasDetectadas'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Categoría en PLD:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['categoria'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Observaciones:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['observaciones'] ?? '—' }}</td>
                </tr>
            @endif
            @if ($urlDetalle !== '')
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Detalles del cliente:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">
                        <a href="{{ $urlDetalle }}" style="color:#1d4ed8;">{{ $urlDetalle }}</a>
                    </td>
                </tr>
            @endif
        </table>

        @if (! empty($patrones))
            <p style="margin:24px 0 8px;">Detalle de los alertamientos emitidos:</p>

            <table role="presentation" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;border:1px solid #ddd;margin-bottom:16px;">
                <thead>
                    <tr style="background-color:#f2f2f2;">
                        <th style="border:1px solid #ddd;text-align:left;padding:6px;">Patrón</th>
                        <th style="border:1px solid #ddd;text-align:left;padding:6px;">Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($patrones as $patron)
                        <tr>
                            <td style="border:1px solid #ddd;padding:6px;vertical-align:top;">{{ $patron['patron'] ?? '—' }}</td>
                            <td style="border:1px solid #ddd;padding:6px;vertical-align:top;">{{ $patron['descripcion'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p style="margin:24px 0 8px;">Atentamente:</p>
        <p style="margin:0 0 24px;">PLD - Tláloc Seguros S.A.</p>

        <p style="margin:0;font-size:12px;">Mensaje enviado automáticamente, por favor no responder</p>
    </div>
</body>
</html>
