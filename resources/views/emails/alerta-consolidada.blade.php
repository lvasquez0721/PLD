<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alerta PLD - Tláloc Seguros</title>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#000000;font-size:14px;line-height:1.6;">
    <div style="padding:24px;">
        <p style="margin:0 0 16px;">Estimado Oficial de Cumplimiento:</p>

        <p style="margin:0 0 16px;">
            Se han generado <strong>{{ count($datos['alertas'] ?? []) }}</strong> alerta(s) en el sistema PLD el {{ $datos['fecha'] ?? now()->format('d/m/Y') }} a las {{ $datos['hora'] ?? now()->format('H:i') }} hrs.
        </p>

        @if(!empty($datos['alertas']))
        <table role="presentation" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;border:1px solid #ddd;margin-bottom:16px;">
            <thead>
                <tr style="background-color:#f2f2f2;">
                    <th style="border:1px solid #ddd;text-align:left;padding:6px;">Patrón</th>
                    <th style="border:1px solid #ddd;text-align:left;padding:6px;">Póliza</th>
                    <th style="border:1px solid #ddd;text-align:left;padding:6px;">Cliente</th>
                    <th style="border:1px solid #ddd;text-align:left;padding:6px;">ID Operación</th>
                    <th style="border:1px solid #ddd;text-align:left;padding:6px;">Estatus</th>
                    <th style="border:1px solid #ddd;text-align:left;padding:6px;">Descripción</th>
                    <th style="border:1px solid #ddd;text-align:left;padding:6px;">Razones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($datos['alertas'] as $alerta)
                <tr>
                    <td style="border:1px solid #ddd;padding:6px;vertical-align:top;">{{ $alerta->Patron ?? $alerta['patron'] ?? '—' }}</td>
                    <td style="border:1px solid #ddd;padding:6px;vertical-align:top;">{{ $alerta->Poliza ?? $alerta['poliza'] ?? '—' }}</td>
                    <td style="border:1px solid #ddd;padding:6px;vertical-align:top;">{{ $alerta->Cliente ?? $alerta['cliente'] ?? '—' }}</td>
                    <td style="border:1px solid #ddd;padding:6px;vertical-align:top;">{{ $alerta->IDOperacion ?? $alerta['idOperacion'] ?? '—' }}</td>
                    <td style="border:1px solid #ddd;padding:6px;vertical-align:top;">{{ $alerta->Estatus ?? $alerta['estatus'] ?? '—' }}</td>
                    <td style="border:1px solid #ddd;padding:6px;vertical-align:top;">{{ $alerta->Descripcion ?? $alerta['descripcion'] ?? '—' }}</td>
                    <td style="border:1px solid #ddd;padding:6px;vertical-align:top;">{{ $alerta->Razones ?? $alerta['razones'] ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if(!empty($datos['operacion']))
        <p style="margin:0 0 8px;"><strong>Operación relacionada:</strong> ID {{ $datos['operacion']->IDOperacion ?? $datos['operacion']['IDOperacion'] ?? '—' }} - Póliza {{ $datos['operacion']->FolioPoliza ?? $datos['operacion']['FolioPoliza'] ?? '—' }}</p>
        @endif

        <p style="margin:24px 0 8px;">Atentamente:</p>
        <p style="margin:0 0 24px;">PLD - Tláloc Seguros S.A.</p>

        <p style="margin:0;font-size:12px;color:#666;">Mensaje enviado automáticamente, por favor no responder. Configuración: <a href="{{ url('/configuracion-cumplimiento') }}">{{ url('/configuracion-cumplimiento') }}</a></p>
    </div>
</body>
</html>
