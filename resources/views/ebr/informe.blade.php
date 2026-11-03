<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe EBR {{ $ejercicio->anio }}</title>
    <style>
        @page { margin: 32px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 18px 0 6px; color: #065f46; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
        h3 { font-size: 11px; margin: 12px 0 4px; }
        p { line-height: 1.45; margin: 4px 0; }
        .muted { color: #6b7280; }
        .cover { text-align: center; padding-top: 100px; }
        .cover h1 { font-size: 26px; color: #064e3b; }
        .cover .year { font-size: 42px; color: #059669; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        th { background: #ecfdf5; color: #065f46; font-size: 8px; text-align: left; padding: 4px; border: 1px solid #d1d5db; }
        td { padding: 4px; border: 1px solid #e5e7eb; vertical-align: top; }
        .right { text-align: right; }
        .center { text-align: center; }
        .badge { display: inline-block; padding: 1px 5px; border-radius: 8px; font-size: 8px; background: #f3f4f6; }
        .bajo { background: #d1fae5; color: #065f46; }
        .medio { background: #fef3c7; color: #92400e; }
        .alto { background: #ffedd5; color: #9a3412; }
        .muyalto { background: #fee2e2; color: #991b1b; }
        .page-break { page-break-before: always; }
        .footer { position: fixed; bottom: -16px; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
    </style>
</head>
<body>
@php
    $elementos = $r['elementos'] ?? [];
    $institucion = $r['institucion'] ?? [];
    $nivelClase = function ($nivel) {
        $n = mb_strtolower((string) $nivel);
        if (str_contains($n, 'muy alto')) return 'muyalto';
        if (str_contains($n, 'alto')) return 'alto';
        if (str_contains($n, 'medio')) return 'medio';
        return 'bajo';
    };

    $mitigantes = collect(config('ebr.mitigantes'))->map(function ($base) {
        $nivel = $base['nivel'];
        $efectividad = (float) (config('ebr.niveles_efectividad')[$nivel] ?? 1);

        return [
            'id' => (int) $base['id'],
            'nombre' => $base['nombre'],
            'scope' => $base['scope'],
            'max' => (float) $base['max'],
            'nivel' => $nivel,
            'efecto' => round((float) $base['max'] * $efectividad, 6),
        ];
    });
    $mitigantesPorScope = $mitigantes->groupBy('scope');

    $efectoMitigante = fn ($mitigante) => (float) ($mitigante['efecto'] ?? 0);

    $efectoScope = function ($scope) use ($mitigantesPorScope, $efectoMitigante) {
        return (float) (($mitigantesPorScope->get($scope) ?? collect())->sum($efectoMitigante));
    };

    $productoMitigante = $efectoScope('producto_1_2');
    $clienteActividadMitigante = $efectoScope('cliente_actividad');
    $clienteElementoMitigante = $efectoScope('cliente_elemento');
    $entidadMitigantes = $mitigantesPorScope->get('entidad', collect());
    $entidadMitiganteTotal = $efectoScope('entidad');
@endphp

<div class="cover">
    <h1>Informe anual de exposición a riesgos de<br>lavado de dinero y financiamiento al terrorismo</h1>
    <div class="year">{{ $ejercicio->anio }}</div>
    <p style="font-size: 13px;">Tláloc Seguros, S.A.</p>
    <p class="muted">Periodo del {{ $ejercicio->fecha_inicio?->format('d/m/Y') }} al {{ $ejercicio->fecha_fin?->format('d/m/Y') }}</p>
    <p class="muted">Documento generado el {{ now()->format('d/m/Y H:i') }}</p>
</div>

<div class="page-break"></div>
<h1>Informe anual de exposición a riesgos de LD/FT {{ $ejercicio->anio }}</h1>

<h2>1. Antecedentes</h2>
<p>El presente informe presenta los resultados de la actualización de la cartera de pólizas y clientes utilizando la metodología de enfoque basado en riesgos para Tláloc Seguros, aprobada por el Comité de Comunicación y Control.</p>
<p>De conformidad con la Guía para la elaboración de la metodología de evaluación de riesgos en materia de prevención de operaciones con recursos de procedencia ilícita y financiamiento al terrorismo, se consideró información de doce meses: del {{ $ejercicio->fecha_inicio?->format('d/m/Y') }} al {{ $ejercicio->fecha_fin?->format('d/m/Y') }}.</p>
<p>La información utilizada comprende número de operaciones, número de pólizas, primas de seguros como operaciones de entrada y pago de siniestros como operaciones de salida.</p>
<p class="muted">Resumen de insumos: {{ number_format($r['resumen_datos']['polizas'] ?? 0) }} pólizas, {{ number_format($r['resumen_datos']['clientes'] ?? 0) }} clientes, {{ number_format($r['resumen_datos']['operaciones_entrada'] ?? 0) }} operaciones de entrada, {{ number_format($r['resumen_datos']['operaciones_salida'] ?? 0) }} operaciones de salida y {{ number_format($r['resumen_datos']['alertas'] ?? 0) }} alertas. De estas alertas, {{ number_format($r['resumen_datos']['alertas_procedentes'] ?? 0) }} concluyeron en reporte y {{ number_format($r['resumen_datos']['alertas_relevantes'] ?? 0) }} corresponden a operaciones relevantes.</p>

<h2>2. Resultados de la implementación</h2>
<h3>2.1 Riesgo inherente por elemento</h3>
<table>
    <thead><tr><th>Elemento</th><th class="right">Peso</th><th class="right">Riesgo inherente</th><th>Nivel</th><th class="right">Riesgo residual</th><th>Nivel residual</th></tr></thead>
    <tbody>
    @foreach(($institucion['detalle'] ?? []) as $e)
        <tr>
            <td>{{ $e['elemento'] }}</td>
            <td class="right">{{ number_format($e['peso'] * 100, 0) }}%</td>
            <td class="right">{{ number_format($e['inherente'], 2) }}</td>
            <td><span class="badge {{ $nivelClase($e['nivel']) }}">{{ $e['nivel'] }}</span></td>
            <td class="right">{{ number_format($e['residual'], 2) }}</td>
            <td><span class="badge {{ $nivelClase($e['nivel_residual']) }}">{{ $e['nivel_residual'] }}</span></td>
        </tr>
    @endforeach
    </tbody>
</table>

<h3>2.1.1 Productos y Servicios</h3>
<table>
    <thead><tr><th>Producto</th><th class="right">Inherente</th><th>Nivel</th><th class="right">Residual</th><th>Nivel residual</th></tr></thead>
    <tbody>
    @foreach(($elementos['productos']['detalle'] ?? []) as $p)
        <tr><td>{{ $p['grupo'] }}</td><td class="right">{{ number_format($p['inherente'], 2) }}</td><td><span class="badge {{ $nivelClase($p['nivel']) }}">{{ $p['nivel'] }}</span></td><td class="right">{{ number_format($p['residual'], 2) }}</td><td><span class="badge {{ $nivelClase($p['nivel_residual']) }}">{{ $p['nivel_residual'] }}</span></td></tr>
    @endforeach
    </tbody>
</table>

<h3>2.1.2 Clientes</h3>
@foreach(['pf' => 'Personas Físicas', 'pm' => 'Personas Morales'] as $clave => $titulo)
    <p><strong>{{ $titulo }}:</strong> {{ number_format($elementos['clientes'][$clave]['inherente'] ?? 0, 2) }} inherente / {{ number_format($elementos['clientes'][$clave]['residual'] ?? 0, 2) }} residual.</p>
    <table>
        <thead><tr><th>Indicador</th><th class="right">Peso</th><th class="right">Inherente</th><th>Nivel</th><th class="right">Mitigante</th><th class="right">Residual</th></tr></thead>
        <tbody>
        @foreach(($elementos['clientes'][$clave]['indicadores'] ?? []) as $i)
            <tr><td>{{ $i['nombre'] }}</td><td class="right">{{ number_format($i['peso'] * 100, 0) }}%</td><td class="right">{{ number_format($i['inherente'], 2) }}</td><td><span class="badge {{ $nivelClase($i['nivel']) }}">{{ $i['nivel'] }}</span></td><td class="right">{{ number_format($i['mitigante'] * 100, 0) }}%</td><td class="right">{{ number_format($i['residual'], 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endforeach

<h3>2.1.3 Países y Áreas Geográficas</h3>
<p><strong>País de mayor exposición:</strong> {{ $elementos['geografia']['pais']['pais'] ?? 'México' }} — {{ number_format($elementos['geografia']['pais']['inherente'] ?? 0, 2) }} inherente / {{ number_format($elementos['geografia']['pais']['residual'] ?? 0, 2) }} residual.</p>
@if(!empty($elementos['geografia']['paises']))
<table>
    <thead><tr><th>País</th><th class="right">Score</th><th class="right">Inherente</th><th>Nivel</th><th class="right">Residual</th><th>Nivel residual</th></tr></thead>
    <tbody>
    @foreach($elementos['geografia']['paises'] as $p)
        <tr><td>{{ $p['pais'] }}</td><td class="right">{{ number_format($p['score'], 2) }}</td><td class="right">{{ number_format($p['inherente'], 2) }}</td><td><span class="badge {{ $nivelClase($p['nivel']) }}">{{ $p['nivel'] }}</span></td><td class="right">{{ number_format($p['residual'], 2) }}</td><td><span class="badge {{ $nivelClase($p['nivel_residual']) }}">{{ $p['nivel_residual'] }}</span></td></tr>
    @endforeach
    </tbody>
</table>
@endif
<table>
    <thead><tr><th>Estado</th><th class="right">Inherente</th><th>Nivel</th><th class="right">Residual</th><th>Nivel residual</th></tr></thead>
    <tbody>
    @foreach(($elementos['geografia']['estados'] ?? []) as $e)
        <tr><td>{{ $e['estado'] }}</td><td class="right">{{ number_format($e['inherente'], 2) }}</td><td><span class="badge {{ $nivelClase($e['nivel']) }}">{{ $e['nivel'] }}</span></td><td class="right">{{ number_format($e['residual'], 2) }}</td><td><span class="badge {{ $nivelClase($e['nivel_residual']) }}">{{ $e['nivel_residual'] }}</span></td></tr>
    @endforeach
    </tbody>
</table>

<h3>2.1.4 Canales de envío y distribución</h3>
<table>
    <thead><tr><th>Canal</th><th class="right">Inherente</th><th>Nivel</th><th class="right">Residual</th><th>Nivel residual</th></tr></thead>
    <tbody>
    @foreach(($elementos['canales']['detalle'] ?? []) as $c)
        <tr><td>{{ $c['grupo'] }}</td><td class="right">{{ number_format($c['inherente'], 2) }}</td><td><span class="badge {{ $nivelClase($c['nivel']) }}">{{ $c['nivel'] }}</span></td><td class="right">{{ number_format($c['residual'], 2) }}</td><td><span class="badge {{ $nivelClase($c['nivel_residual']) }}">{{ $c['nivel_residual'] }}</span></td></tr>
    @endforeach
    </tbody>
</table>

<h3>2.1.5 Transacciones</h3>
<table>
    <thead><tr><th>Indicador</th><th class="right">Puntaje</th><th class="right">Alertas</th><th class="right">Impacto</th><th class="right">Inherente</th><th>Nivel</th></tr></thead>
    <tbody>
    @foreach(($elementos['transacciones']['indicadores'] ?? []) as $t)
        <tr><td>{{ $t['nombre'] }}</td><td class="right">{{ number_format($t['puntaje'], 2) }}</td><td class="right">{{ $t['alertas'] }}</td><td class="right">{{ number_format($t['impacto'], 4) }}</td><td class="right">{{ number_format($t['inherente'], 2) }}</td><td><span class="badge {{ $nivelClase($t['nivel']) }}">{{ $t['nivel'] }}</span></td></tr>
    @endforeach
    </tbody>
</table>

<h2>2.2 Mitigantes</h2>
<p>Solamente existen mitigantes definidos a nivel indicador para los elementos Productos y Servicios y Clientes. Derivado de lo anterior, estos dos elementos tienen una reducción en su exposición.</p>

<h3>Productos y Servicios</h3>
<table>
    <thead><tr><th>Elemento</th><th>Productos</th><th class="right">Mitigante por indicador</th><th class="right">Mitigante elemento</th><th class="right">Riesgo residual elemento</th><th>Nivel residual</th></tr></thead>
    <tbody>
        <tr>
            <td>Productos y Servicios</td>
            <td>{{ implode(', ', array_column($elementos['productos']['detalle'] ?? [], 'grupo')) }}</td>
            <td class="right">{{ number_format($productoMitigante * 100, 0) }}%</td>
            <td class="right">0%</td>
            <td class="right">{{ number_format($elementos['productos']['residual'] ?? 0, 2) }}</td>
            <td><span class="badge {{ $nivelClase($elementos['productos']['nivel_residual'] ?? '') }}">{{ $elementos['productos']['nivel_residual'] ?? '' }}</span></td>
        </tr>
    </tbody>
</table>
<p class="muted">El mitigante “Pago en moneda nacional” reduce 20% el indicador 1.2 (uso de efectivo o divisas).</p>

<h3>Clientes</h3>
@foreach(['pf' => 'Personas Físicas', 'pm' => 'Personas Morales'] as $clave => $titulo)
    <h4>{{ $titulo }}</h4>
    <table>
        <thead><tr><th>Indicador</th><th class="right">Mitigante por indicador</th><th class="right">Riesgo residual por indicador</th><th>Nivel</th></tr></thead>
        <tbody>
        @foreach(($elementos['clientes'][$clave]['indicadores'] ?? []) as $i)
            <tr>
                <td>{{ $i['nombre'] }}</td>
                <td class="right">{{ number_format(($i['mitigante'] ?? 0) * 100, 0) }}%</td>
                <td class="right">{{ number_format($i['residual'] ?? 0, 2) }}</td>
                <td><span class="badge {{ $nivelClase($i['nivel_residual'] ?? '') }}">{{ $i['nivel_residual'] ?? '' }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endforeach
<p><strong>Mitigante elemento Clientes (Debida Diligencia del Cliente):</strong> {{ number_format($clienteElementoMitigante * 100, 0) }}% · <strong>Riesgo residual elemento:</strong> {{ number_format($elementos['clientes']['residual'] ?? 0, 2) }} ({{ $elementos['clientes']['nivel_residual'] ?? '' }}).</p>
<p class="muted">El mitigante de actividad económica ({{ number_format($clienteActividadMitigante * 100, 0) }}%) se aplica a personas físicas y morales; los demás indicadores no tienen mitigante.</p>

<h3>Elementos sin mitigantes</h3>
<table>
    <thead><tr><th>Elemento</th><th class="right">Riesgo inherente</th><th class="right">Mitigante</th><th class="right">Riesgo residual</th><th>Nivel</th></tr></thead>
    <tbody>
    @foreach(['geografia' => 'Países y Áreas Geográficas', 'canales' => 'Canales de Envío y Distribución', 'transacciones' => 'Transacciones'] as $clave => $titulo)
        <tr>
            <td>{{ $titulo }}</td>
            <td class="right">{{ number_format($elementos[$clave]['inherente'] ?? 0, 2) }}</td>
            <td class="right">0%</td>
            <td class="right">{{ number_format($elementos[$clave]['residual'] ?? 0, 2) }}</td>
            <td><span class="badge {{ $nivelClase($elementos[$clave]['nivel_residual'] ?? '') }}">{{ $elementos[$clave]['nivel_residual'] ?? '' }}</span></td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>3. Riesgo residual de la institución</h2>
<table>
    <thead><tr><th>Concepto</th><th class="right">Valor</th><th>Nivel</th></tr></thead>
    <tbody>
        <tr><td>Riesgo inherente institución</td><td class="right">{{ number_format($institucion['inherente'] ?? 0, 2) }}</td><td><span class="badge {{ $nivelClase($institucion['nivel_inherente'] ?? '') }}">{{ $institucion['nivel_inherente'] ?? '' }}</span></td></tr>
        <tr><td>Riesgo residual s/ mitigantes a nivel indicador</td><td class="right">{{ number_format($institucion['residual'] ?? 0, 2) }}</td><td><span class="badge {{ $nivelClase($institucion['nivel_residual'] ?? '') }}">{{ $institucion['nivel_residual'] ?? '' }}</span></td></tr>
        <tr><td>Mitigantes de entidad</td><td class="right">{{ number_format(($institucion['mitigante_entidad'] ?? 0) * 100, 0) }}%</td><td></td></tr>
        <tr><td>Riesgo residual final</td><td class="right">{{ number_format($institucion['residual_final'] ?? 0, 2) }}</td><td><span class="badge {{ $nivelClase($institucion['nivel'] ?? '') }}">{{ $institucion['nivel'] ?? '' }}</span></td></tr>
    </tbody>
</table>

<h3>3.3 Mitigantes de entidad</h3>
<table>
    <thead><tr><th>No.</th><th>Mitigante</th><th class="right">Máximo</th><th>Nivel</th><th class="right">Efecto</th></tr></thead>
    <tbody>
    @foreach($entidadMitigantes as $m)
        <tr>
            <td>{{ $m['id'] ?? '' }}</td>
            <td>{{ $m['nombre'] ?? '' }}</td>
            <td class="right">{{ number_format(($m['max'] ?? 0) * 100, 0) }}%</td>
            <td>{{ $m['nivel'] ?? '' }}</td>
            <td class="right">{{ number_format($efectoMitigante($m) * 100, 0) }}%</td>
        </tr>
    @endforeach
        <tr>
            <td></td>
            <td><strong>Total mitigantes de entidad</strong></td>
            <td></td>
            <td></td>
            <td class="right"><strong>{{ number_format($entidadMitiganteTotal * 100, 0) }}%</strong></td>
        </tr>
    </tbody>
</table>
<p class="muted">Riesgo residual s/ mitigantes de entidad: {{ number_format($institucion['residual'] ?? 0, 2) }} → Riesgo residual final: {{ number_format($institucion['residual_final'] ?? 0, 2) }} ({{ $institucion['nivel'] ?? '' }}).</p>

<h2>4. Conclusiones</h2>
<p>Los resultados obtenidos de la implementación del enfoque basado en riesgos para el periodo {{ $ejercicio->anio }} ubican a la Institución en un nivel de riesgo residual final <strong>{{ strtolower($institucion['nivel'] ?? '') }}</strong> ({{ number_format($institucion['residual_final'] ?? 0, 2) }}).</p>
@if(!empty($ejercicio->observaciones))
    <p>{!! nl2br(e($ejercicio->observaciones)) !!}</p>
@else
    <p>El ejercicio anual de evaluación de riesgos permite conocer la exposición a los riesgos de LD/FT y reforzar el monitoreo de alertas, la capacitación, la auditoría y los controles internos de la Institución.</p>
@endif

@if(!empty($r['advertencias']))
    <h2>Anexo. Advertencias del cálculo</h2>
    <ul>
        @foreach($r['advertencias'] as $advertencia)
            <li>{{ $advertencia }}</li>
        @endforeach
    </ul>
@endif

<div class="footer">Tláloc Seguros, S.A. · Informe EBR {{ $ejercicio->anio }} · Generado por el sistema PLD</div>
</body>
</html>
