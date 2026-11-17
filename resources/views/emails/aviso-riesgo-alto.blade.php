<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aviso de Cumplimiento - PLD</title>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#000000;font-size:14px;line-height:1.6;">
    <div style="padding:24px;">
        @php
            $tienePerfil = isset($datos['Perfil']) && $datos['Perfil'] !== '' && $datos['Perfil'] !== null;
            $tieneUbicacion = ! empty($datos['UbicacionRiesgo']);
        @endphp

        <p style="margin:0 0 16px;">Estimado Usuario:</p>

        @if ($tienePerfil)
            <p style="margin:0 0 16px;">
                Le informamos que se emitió la operación
                <strong>{{ $datos['FolioPoliza'] ?? '—' }}</strong>
                para el cliente <strong>{{ $datos['nombre'] ?? '—' }}</strong>
                (IDCliente {{ $datos['idCliente'] ?? '—' }}),
                cuyo perfil de riesgo es alto (Perfil {{ $datos['Perfil'] ?? '—' }})
                el {{ $datos['fecha'] ?? '' }} a las {{ $datos['hora'] ?? '' }} hrs.
            </p>
        @endif

        @if ($tieneUbicacion)
            <p style="margin:0 0 16px;">
                Le informamos que se emitió la operación
                <strong>{{ $datos['FolioPoliza'] ?? '—' }}</strong>
                para el cliente <strong>{{ $datos['nombre'] ?? '—' }}</strong>
                (IDCliente {{ $datos['idCliente'] ?? '—' }}), quien tiene domicilio en una
                zona de alto riesgo ({{ $datos['UbicacionRiesgo'] ?? '—' }}).
                La prima de la operación convertida a USD es
                <strong>{{ number_format((float) ($datos['PrimaUSD'] ?? 0), 2) }}</strong>,
                igual o mayor al umbral configurado
                ({{ number_format((float) ($datos['UmbralUSD'] ?? 0), 2) }} USD),
                el {{ $datos['fecha'] ?? '' }} a las {{ $datos['hora'] ?? '' }} hrs.
            </p>
        @endif

        @if (! $tienePerfil && ! $tieneUbicacion)
            <p style="margin:0 0 16px;">
                Le informamos que se emitió la operación
                <strong>{{ $datos['FolioPoliza'] ?? '—' }}</strong>
                para el cliente <strong>{{ $datos['nombre'] ?? '—' }}</strong>
                (IDCliente {{ $datos['idCliente'] ?? '—' }})
                el {{ $datos['fecha'] ?? '' }} a las {{ $datos['hora'] ?? '' }} hrs.
            </p>
        @endif

        <p style="margin:0 0 16px;">Información detallada:</p>

        <table role="presentation" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">
            <tr>
                <td style="vertical-align:top;white-space:nowrap;">Cliente:&nbsp;&nbsp;</td>
                <td style="vertical-align:top;">{{ $datos['nombre'] ?? '—' }} (ID {{ $datos['idCliente'] ?? '—' }})</td>
            </tr>
            <tr>
                <td style="vertical-align:top;white-space:nowrap;">IDOperación:&nbsp;&nbsp;</td>
                <td style="vertical-align:top;">{{ $datos['IDOperacion'] ?? '—' }}</td>
            </tr>
            <tr>
                <td style="vertical-align:top;white-space:nowrap;">Folio Póliza:&nbsp;&nbsp;</td>
                <td style="vertical-align:top;">{{ $datos['FolioPoliza'] ?? '—' }}</td>
            </tr>
            <tr>
                <td style="vertical-align:top;white-space:nowrap;">Folio Endoso:&nbsp;&nbsp;</td>
                <td style="vertical-align:top;">{{ $datos['FolioEndoso'] ?? '—' }}</td>
            </tr>
            @if ($tienePerfil)
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Perfil de riesgo:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['Perfil'] ?? '—' }} (alto riesgo: mayor o igual a 2)</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">IDRegistroPerfil:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['IDRegistroPerfil'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Total Ubicación:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['TotalUbicacion'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Total Económico:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['TotalEconomico'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Cálculo Ocupación:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['CalculoOcupacion'] ?? '—' }}</td>
                </tr>
            @endif
            @if ($tieneUbicacion)
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Ubicación de riesgo:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ $datos['UbicacionRiesgo'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Prima de la operación:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ number_format((float) ($datos['PrimaTotal'] ?? 0), 2) }} {{ $datos['IDMoneda'] ?? '' }}</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Prima en USD:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ number_format((float) ($datos['PrimaUSD'] ?? 0), 2) }} USD</td>
                </tr>
                <tr>
                    <td style="vertical-align:top;white-space:nowrap;">Umbral configurado:&nbsp;&nbsp;</td>
                    <td style="vertical-align:top;">{{ number_format((float) ($datos['UmbralUSD'] ?? 0), 2) }} USD</td>
                </tr>
            @endif
        </table>

        <p style="margin:24px 0 8px;">Atentamente:</p>
        <p style="margin:0 0 24px;">PLD - Tláloc Seguros S.A.</p>

        <p style="margin:0;font-size:12px;">Mensaje enviado automáticamente, por favor no responder</p>
    </div>
</body>
</html>
