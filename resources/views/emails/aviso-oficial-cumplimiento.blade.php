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
            Le informamos que se registró la {{ $datos['tipo'] ?? 'persona' }}
            <strong>{{ $datos['nombre'] ?? '—' }}</strong> en la base de datos de persona del PLD
            y fue detectado en listas de Quién es Quién el {{ $datos['fecha'] ?? '' }} a las {{ $datos['hora'] ?? '' }} hrs.
        </p>

        <p style="margin:0 0 16px;">Información detallada:</p>

        <table role="presentation" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">
            <tr>
                <td style="vertical-align:top;white-space:nowrap;">Nombre registrado en PLD:&nbsp;&nbsp;</td>
                <td style="vertical-align:top;">{{ $datos['nombre'] ?? '—' }}</td>
            </tr>
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
        </table>

        <p style="margin:24px 0 8px;">Atentamente:</p>
        <p style="margin:0 0 24px;">PLD - Tláloc Seguros S.A.</p>

        <p style="margin:0;font-size:12px;">Mensaje enviado automáticamente, por favor no responder</p>
    </div>
</body>
</html>
