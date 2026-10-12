<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aviso de Cumplimiento - PLD</title>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#000000;font-size:14px;line-height:1.6;">
    <div style="padding:24px;">
        <p style="margin:0 0 16px;">Estimado Usuario:</p>

        <p style="margin:0 0 16px;">
            Le informamos que se emitió la operación
            <strong>{{ $datos['FolioPoliza'] ?? '—' }}</strong>
            para el cliente <strong>{{ $datos['nombre'] ?? '—' }}</strong>
            (IDCliente {{ $datos['idCliente'] ?? '—' }}),
            cuyo perfil de riesgo es alto (Perfil {{ $datos['Perfil'] ?? '—' }})
            el {{ $datos['fecha'] ?? '' }} a las {{ $datos['hora'] ?? '' }} hrs.
        </p>

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
        </table>

        <p style="margin:24px 0 8px;">Atentamente:</p>
        <p style="margin:0 0 24px;">PLD - Tláloc Seguros S.A.</p>

        <p style="margin:0;font-size:12px;">Mensaje enviado automáticamente, por favor no responder</p>
    </div>
</body>
</html>
