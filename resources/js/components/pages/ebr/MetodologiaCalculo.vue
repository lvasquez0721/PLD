<script setup lang="ts">
import { riesgoClase, scopeLabel } from '@/lib/ebr';
import {
    AlertTriangle,
    ArrowLeftRight,
    BookOpen,
    Calculator,
    CheckCircle2,
    ClipboardCheck,
    Database,
    FileSpreadsheet,
    FileText,
    Globe,
    Info,
    Layers,
    Package,
    Radio,
    ShieldCheck,
    Sigma,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Ejercicio {
    id: number;
    anio: number;
    nombre: string;
    fecha_inicio: string;
    fecha_fin: string;
    estado: 'borrador' | 'calculado' | 'cerrado';
    version_metodologia: string;
    created_at: string | null;
    calculado_at: string | null;
    cerrado_at: string | null;
    importado_por_nombre: string | null;
    calculado_por_nombre: string | null;
    mitigantes: any[];
    resultados: any | null;
    advertencias: string[];
    polizas_count: number;
    siniestros_count: number;
    alertas_count: number;
}

const props = defineProps<{
    ejercicio: Ejercicio;
    metodologia: any;
    mitigantesDefault: any[];
    nivelesEfectividad: Record<string, number>;
    resultados: any | null;
}>();

const elementos = computed(() => props.resultados?.elementos ?? null);
const institucion = computed(() => props.resultados?.institucion ?? null);

function pct(valor: number | undefined, decimales = 0) {
    return `${(Number(valor ?? 0) * 100).toFixed(decimales)}%`;
}

function num(valor: number | undefined) {
    return Number(valor ?? 0).toFixed(2);
}

function fmt(valor?: string | null) {
    return valor || '—';
}

function valorIndicador(indicador: any) {
    if (indicador?.peso != null) {
        return pct(indicador.peso);
    }

    if (indicador?.puntaje != null) {
        return `Puntaje ${indicador.puntaje}`;
    }

    return '—';
}

const catalogoMitigantes = computed<any[]>(() => {
    if ((props.mitigantesDefault ?? []).length > 0) {
        return props.mitigantesDefault;
    }

    return props.ejercicio.mitigantes ?? [];
});

const mitigantesActuales = computed(() =>
    catalogoMitigantes.value.map((base: any) => {
        const nivel = base.nivel ?? 'Optimizado';
        const efectividad = Number(props.nivelesEfectividad?.[nivel] ?? 1);
        const max = Number(base.max ?? 0);

        return {
            ...base,
            nivel,
            efectividad,
            efecto: max * efectividad,
        };
    }),
);

const mitigantesEntidad = computed(() =>
    mitigantesActuales.value.filter((m) => m.scope === 'entidad'),
);

const totalEntidad = computed(() =>
    mitigantesEntidad.value.reduce(
        (total, m) => total + Number(m.efecto ?? 0),
        0,
    ),
);

const entidadDesviada = computed(
    () => Math.abs(totalEntidad.value - 0.35) > 0.0001,
);

const offsets = computed(() => ({
    productos: Number(props.metodologia?.productos?.offset ?? 0.4),
    clientesPf: Number(props.metodologia?.clientes?.pf?.offset ?? 0.4),
    clientesPm: Number(props.metodologia?.clientes?.pm?.offset ?? 0.35),
    canales: Number(props.metodologia?.canales?.offset ?? 0.2),
}));

const elementosCalculo = computed(() => [
    {
        clave: 'productos',
        titulo: 'Productos y Servicios',
        icono: Package,
        descripcion:
            'Mide qué tanto las características del producto pueden facilitar su uso en esquemas de LD/FT.',
        regla: `5 indicadores ponderados. Se resta un offset de ${offsets.value.productos.toFixed(2)} y el resultado se acota entre 0 y 5.`,
        mitigante: '20% en el indicador 1.2 (uso de efectivo o divisas).',
        resultado: elementos.value?.productos ?? null,
    },
    {
        clave: 'clientes',
        titulo: 'Clientes',
        icono: Users,
        descripcion:
            'Evalúa el perfil de personas físicas y morales: país, edad o antigüedad, relación comercial, domicilio, actividad y listas de riesgo.',
        regla: `6 indicadores ponderados. Offset de ${offsets.value.clientesPf.toFixed(2)} para personas físicas y ${offsets.value.clientesPm.toFixed(2)} para personas morales. El elemento se combina con las cinco métricas (clientes, operaciones de entrada/salida y montos de entrada/salida), tomando el mayor resultado. Para personas físicas, la métrica dominante se determina con la distribución de personas morales, conforme al Excel de implementación 2025.`,
        mitigante:
            '20% en Actividad Económica y 10% adicional al elemento por Debida Diligencia del Cliente.',
        resultado: elementos.value?.clientes ?? null,
    },
    {
        clave: 'geografia',
        titulo: 'Países y Áreas Geográficas',
        icono: Globe,
        descripcion:
            'Considera el riesgo del país de nacionalidad y el de los estados donde opera la institución.',
        regla: 'País por jerarquización Basilea. Estados: 60% índice de paz + 40% frontera. El elemento toma el mayor entre país y estados.',
        mitigante: 'Sin mitigantes.',
        resultado: elementos.value?.geografia ?? null,
    },
    {
        clave: 'canales',
        titulo: 'Canales de Envío y Distribución',
        icono: Radio,
        descripcion:
            'Mide el riesgo de vender o distribuir mediante canales internos, agentes o terceros.',
        regla: `4 indicadores ponderados. Se resta un offset de ${offsets.value.canales.toFixed(2)} y el resultado se acota entre 0 y 5.`,
        mitigante: 'Sin mitigantes.',
        resultado: elementos.value?.canales ?? null,
    },
    {
        clave: 'transacciones',
        titulo: 'Transacciones',
        icono: ArrowLeftRight,
        descripcion:
            'Analiza las alertas procedentes y su peso sobre operaciones, contratos, clientes y montos.',
        regla: 'Cada patrón tiene un puntaje. El impacto es el máximo entre contratos, clientes, operaciones y monto, dividido entre 10% y acotado a 100%. Los scores se suman y se acotan a 5.',
        mitigante: 'Sin mitigantes.',
        resultado: elementos.value?.transacciones ?? null,
    },
]);

const detalleIndicadores = computed(() => {
    const m = props.metodologia ?? {};
    const grupos = [
        {
            elemento: 'Productos y Servicios',
            indicadores: m.productos?.indicadores ?? [],
        },
        {
            elemento: 'Clientes · Personas Físicas',
            indicadores: m.clientes?.pf?.indicadores ?? [],
        },
        {
            elemento: 'Clientes · Personas Morales',
            indicadores: m.clientes?.pm?.indicadores ?? [],
        },
        {
            elemento: 'Países y Áreas Geográficas',
            indicadores: [
                {
                    codigo: '3.1',
                    nombre:
                        m.geografia?.pais?.indicador ??
                        'Jerarquización Basilea para LD/FT',
                    peso: m.geografia?.pais?.peso ?? 1,
                },
                ...(m.geografia?.estados?.indicadores ?? []),
            ],
        },
        {
            elemento: 'Canales de Envío y Distribución',
            indicadores: m.canales?.indicadores ?? [],
        },
        {
            elemento: 'Transacciones',
            indicadores: m.transacciones?.indicadores ?? [],
        },
    ];

    return grupos.filter((grupo) => grupo.indicadores.length > 0);
});

const periodoCompleto = computed(() => {
    const inicio = String(props.ejercicio.fecha_inicio ?? '');
    const fin = String(props.ejercicio.fecha_fin ?? '');

    return (
        inicio.endsWith('-01-01') &&
        fin.endsWith('-12-31') &&
        inicio.slice(0, 4) === fin.slice(0, 4)
    );
});

const calculado = computed(() => Boolean(props.ejercicio.calculado_at));
const cerrado = computed(() => props.ejercicio.estado === 'cerrado');
const advertenciasCount = computed(
    () => (props.ejercicio.advertencias ?? []).length,
);
const insumosCargados = computed(
    () => Number(props.ejercicio.polizas_count ?? 0) > 0,
);

const checklist = computed(() => [
    {
        etiqueta: 'Periodo de 12 meses',
        detalle: `${props.ejercicio.fecha_inicio} al ${props.ejercicio.fecha_fin}`,
        estado: periodoCompleto.value ? 'ok' : 'alerta',
        nota: periodoCompleto.value
            ? 'Cumple con el periodo anual.'
            : 'El periodo no cubre el año completo.',
    },
    {
        etiqueta: 'Insumos cargados',
        detalle: `${props.ejercicio.polizas_count} pólizas · ${props.ejercicio.siniestros_count} siniestros · ${props.ejercicio.alertas_count} alertas`,
        estado: insumosCargados.value ? 'ok' : 'alerta',
        nota: insumosCargados.value
            ? 'Base de pólizas importada.'
            : 'No hay pólizas cargadas.',
    },
    {
        etiqueta: 'Cálculo ejecutado',
        detalle: props.ejercicio.calculado_at ?? 'Pendiente',
        estado: calculado.value ? 'ok' : 'info',
        nota: calculado.value
            ? `Calculado por ${props.ejercicio.calculado_por_nombre ?? 'usuario no registrado'}.`
            : 'Aún no se ha calculado el ejercicio.',
    },
    {
        etiqueta: 'Advertencias',
        detalle: `${advertenciasCount.value} advertencia(s)`,
        estado: advertenciasCount.value === 0 ? 'ok' : 'alerta',
        nota:
            advertenciasCount.value === 0
                ? 'Sin observaciones del cálculo.'
                : 'Revisa la pestaña Advertencias.',
    },
    {
        etiqueta: 'Mitigante de entidad',
        detalle: pct(totalEntidad.value),
        estado: entidadDesviada.value ? 'alerta' : 'ok',
        nota: entidadDesviada.value
            ? 'La referencia del informe 2025 es 35%.'
            : 'Coincide con la referencia de 35%.',
    },
    {
        etiqueta: 'Ejercicio cerrado',
        detalle: props.ejercicio.cerrado_at ?? 'Abierto',
        estado: cerrado.value ? 'ok' : 'info',
        nota: cerrado.value
            ? 'El ejercicio está cerrado y no puede modificarse.'
            : 'El ejercicio aún puede editarse o recalcularse.',
    },
]);

const pasos = [
    {
        numero: 1,
        titulo: 'Crear ejercicio',
        detalle:
            'Se define el año, el periodo de doce meses y la versión de la metodología.',
    },
    {
        numero: 2,
        titulo: 'Importar insumos',
        detalle:
            'Pólizas, siniestros y alertas se validan y se asocian al periodo.',
    },
    {
        numero: 3,
        titulo: 'Riesgo inherente',
        detalle: 'Se calcula la exposición de los cinco elementos.',
    },
    {
        numero: 4,
        titulo: 'Mitigantes',
        detalle:
            'Se aplican los mitigantes fijos de la metodología 2025: 20%, 10% y 35%.',
    },
    {
        numero: 5,
        titulo: 'Residual final',
        detalle: 'Se obtiene el riesgo institucional y se documenta el cierre.',
    },
];

const glosario = [
    {
        termino: 'Riesgo inherente',
        definicion:
            'Exposición antes de considerar la efectividad de los controles.',
    },
    {
        termino: 'Riesgo residual',
        definicion: 'Exposición después de aplicar los mitigantes.',
    },
    {
        termino: 'Mitigante',
        definicion:
            'Control que reduce la probabilidad o el impacto del riesgo.',
    },
    {
        termino: 'Offset',
        definicion: 'Ajuste de calibración que se resta al score del elemento.',
    },
    {
        termino: 'Métrica dominante',
        definicion:
            'Distribución (clientes, operaciones o montos) que produce el mayor score.',
    },
    {
        termino: 'Alerta procedente',
        definicion: 'Alerta cuyo análisis concluyó en reporte o dictamen.',
    },
    {
        termino: 'Alerta relevante',
        definicion:
            'Alerta procedente del patrón de operaciones en efectivo mayores o iguales a 7,500 USD.',
    },
    {
        termino: 'Nivel de riesgo',
        definicion: 'Clasificación final: Bajo, Medio, Alto o Muy Alto.',
    },
];
</script>

<template>
    <div class="space-y-6">
        <div
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <div
                        class="flex items-center gap-2 text-sm font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        <BookOpen class="h-4 w-4" /> Guía de cálculo
                    </div>
                    <h2
                        class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        Cómo se calcula el EBR
                    </h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Esta pestaña explica el proceso completo para auditoría.
                        Es informativa y no modifica los insumos ni los
                        resultados del ejercicio.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700 dark:bg-neutral-900 dark:text-slate-300"
                        >Periodo: {{ ejercicio.fecha_inicio }} al
                        {{ ejercicio.fecha_fin }}</span
                    >
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700 capitalize dark:bg-neutral-900 dark:text-slate-300"
                        >{{ ejercicio.estado }}</span
                    >
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700 dark:bg-neutral-900 dark:text-slate-300"
                        >Metodología {{ ejercicio.version_metodologia }}</span
                    >
                    <span
                        v-if="ejercicio.calculado_at"
                        class="rounded-full bg-emerald-50 px-3 py-1 font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                        >Calculado: {{ ejercicio.calculado_at }}</span
                    >
                </div>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div
                v-for="paso in pasos"
                :key="paso.numero"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-semibold text-white"
                        >{{ paso.numero }}</span
                    >
                    <span
                        class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                        >{{ paso.titulo }}</span
                    >
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ paso.detalle }}
                </p>
            </div>
        </div>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
        >
            <div class="flex items-center gap-2">
                <Database class="h-5 w-5 text-emerald-600" />
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                    Paso 1 · Insumos
                </h3>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <p
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        Pólizas y clientes
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Operaciones de entrada y primas. Se usa la fecha de
                        emisión dentro del periodo.
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <p
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        Siniestros
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Operaciones de salida y montos pagados. Deben estar
                        vinculados a una póliza del periodo.
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <p
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        Alertas
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Solo se consideran las procedentes. Las relevantes son
                        las del patrón de operaciones en efectivo.
                    </p>
                </div>
            </div>
            <p
                class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
            >
                <Info class="mr-1 inline h-4 w-4" />
                El periodo debe cubrir doce meses. Los siniestros sin póliza en
                el periodo y las alertas no vinculadas se excluyen y quedan
                documentados en la pestaña Advertencias.
            </p>
        </section>

        <section class="space-y-4">
            <div
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
            >
                <div class="flex items-center gap-2">
                    <Sigma class="h-5 w-5 text-emerald-600" />
                    <h3
                        class="font-semibold text-slate-900 dark:text-slate-100"
                    >
                        Paso 2 · Riesgo inherente por elemento
                    </h3>
                </div>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Cada indicador se valora de 0 a 5. Se pondera por su peso,
                    se elige la métrica dominante y se resta un offset de
                    calibración. El resultado se acota entre 0 y 5.
                </p>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div
                        class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                        >
                            Fórmula base
                        </p>
                        <p
                            class="mt-1 font-mono text-xs text-slate-700 dark:text-slate-300"
                        >
                            Score = Σ (peso × puntaje) − offset
                        </p>
                    </div>
                    <div
                        class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                        >
                            Rango
                        </p>
                        <p
                            class="mt-1 text-xs text-slate-700 dark:text-slate-300"
                        >
                            0 a 5. Los valores negativos se llevan a 0 y los
                            mayores a 5 se limitan.
                        </p>
                    </div>
                    <div
                        class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                        >
                            Niveles
                        </p>
                        <p
                            class="mt-1 text-xs text-slate-700 dark:text-slate-300"
                        >
                            ≤ 2.00 Bajo · &gt; 2.00–3.00 Medio · &gt; 3.00–4.00
                            Alto · &gt; 4.00 Muy alto.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div
                    v-for="elemento in elementosCalculo"
                    :key="elemento.clave"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <component
                                :is="elemento.icono"
                                class="h-5 w-5 text-emerald-600"
                            />
                            <h4
                                class="font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ elemento.titulo }}
                            </h4>
                        </div>
                        <span
                            v-if="elemento.resultado"
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                riesgoClase(elemento.resultado.nivel_residual)
                            "
                            >{{ elemento.resultado.nivel_residual }}</span
                        >
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ elemento.descripcion }}
                    </p>
                    <div
                        class="mt-3 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-neutral-900/70 dark:text-slate-300"
                    >
                        <p>
                            <span class="font-semibold">Regla:</span>
                            {{ elemento.regla }}
                        </p>
                        <p class="mt-1">
                            <span class="font-semibold">Mitigante:</span>
                            {{ elemento.mitigante }}
                        </p>
                    </div>
                    <div
                        v-if="elemento.resultado"
                        class="mt-3 flex flex-wrap gap-3 text-xs text-slate-600 dark:text-slate-300"
                    >
                        <span
                            >Inherente:
                            <strong>{{
                                num(elemento.resultado.inherente)
                            }}</strong></span
                        >
                        <span
                            >Residual:
                            <strong>{{
                                num(elemento.resultado.residual)
                            }}</strong></span
                        >
                    </div>
                    <p v-else class="mt-3 text-xs text-slate-400">
                        Sin resultados calculados para este ejercicio.
                    </p>
                </div>
            </div>

            <details
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
            >
                <summary
                    class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    Ver indicadores y pesos del detalle técnico
                </summary>
                <div class="mt-4 space-y-4">
                    <div
                        v-for="grupo in detalleIndicadores"
                        :key="grupo.elemento"
                    >
                        <h5
                            class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                        >
                            {{ grupo.elemento }}
                        </h5>
                        <div class="mt-2 overflow-x-auto">
                            <table
                                class="min-w-full divide-y divide-slate-200 text-xs dark:divide-neutral-800"
                            >
                                <thead
                                    class="bg-slate-50 text-left text-slate-500 uppercase dark:bg-neutral-900/70 dark:text-slate-400"
                                >
                                    <tr>
                                        <th class="px-3 py-2">Indicador</th>
                                        <th class="px-3 py-2">
                                            Peso / puntaje
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-neutral-900"
                                >
                                    <tr
                                        v-for="i in grupo.indicadores"
                                        :key="i.codigo ?? i.nombre"
                                    >
                                        <td
                                            class="px-3 py-2 text-slate-600 dark:text-slate-300"
                                        >
                                            {{ i.nombre }}
                                        </td>
                                        <td
                                            class="px-3 py-2 font-medium text-slate-700 dark:text-slate-200"
                                        >
                                            {{ valorIndicador(i) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </details>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
        >
            <div class="flex items-center gap-2">
                <ShieldCheck class="h-5 w-5 text-emerald-600" />
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                    Paso 3 · Mitigantes
                </h3>
            </div>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                El efecto de cada control es el máximo porcentaje de reducción
                multiplicado por su nivel de efectividad fijo de la metodología
                2025.
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="(valor, nivel) in nivelesEfectividad"
                    :key="nivel"
                    class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70"
                >
                    <p
                        class="text-xs font-semibold text-slate-700 dark:text-slate-200"
                    >
                        {{ nivel }}
                    </p>
                    <p
                        class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ pct(valor) }}
                    </p>
                </div>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-slate-200 text-sm dark:divide-neutral-800"
                >
                    <thead
                        class="bg-slate-50 text-left text-xs text-slate-500 uppercase dark:bg-neutral-900/70 dark:text-slate-400"
                    >
                        <tr>
                            <th class="px-3 py-2">Mitigante</th>
                            <th class="px-3 py-2">Alcance</th>
                            <th class="px-3 py-2">Máx.</th>
                            <th class="px-3 py-2">Nivel</th>
                            <th class="px-3 py-2">Efecto</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-neutral-900"
                    >
                        <tr v-for="m in mitigantesActuales" :key="m.id">
                            <td
                                class="px-3 py-2 text-slate-700 dark:text-slate-300"
                            >
                                {{ m.nombre }}
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500">
                                {{ scopeLabel(m.scope) }}
                            </td>
                            <td class="px-3 py-2">{{ pct(m.max) }}</td>
                            <td class="px-3 py-2">{{ m.nivel }}</td>
                            <td class="px-3 py-2 font-semibold">
                                {{ pct(m.efecto) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div
                    class="rounded-xl border border-slate-200 p-3 text-xs text-slate-600 dark:border-neutral-800 dark:text-slate-300"
                >
                    <p class="font-semibold text-slate-800 dark:text-slate-200">
                        Mitigantes por indicador o elemento
                    </p>
                    <p class="mt-1">
                        Productos: 20% en el indicador 1.2. Clientes: 20% en
                        Actividad Económica y 10% en el elemento por Debida
                        Diligencia del Cliente.
                    </p>
                </div>
                <div
                    class="rounded-xl border border-slate-200 p-3 text-xs text-slate-600 dark:border-neutral-800 dark:text-slate-300"
                >
                    <p class="font-semibold text-slate-800 dark:text-slate-200">
                        Sin mitigantes y mitigante de entidad
                    </p>
                    <p class="mt-1">
                        Geografía, Canales y Transacciones no tienen mitigantes.
                        Los seis controles de entidad se aplican al final, sobre
                        el residual institucional.
                    </p>
                </div>
            </div>
            <div
                class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 p-3 text-sm dark:bg-neutral-900/70"
            >
                <span class="text-slate-600 dark:text-slate-300"
                    >Total mitigantes de entidad</span
                >
                <span
                    class="font-semibold text-slate-900 dark:text-slate-100"
                    >{{ pct(totalEntidad) }}</span
                >
            </div>
            <p v-if="entidadDesviada" class="mt-2 text-xs text-amber-600">
                La referencia del informe 2025 es 35% con todos los controles en
                nivel Optimizado.
            </p>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
        >
            <div class="flex items-center gap-2">
                <Calculator class="h-5 w-5 text-emerald-600" />
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                    Paso 4 · Riesgo residual institucional
                </h3>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <p
                        class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Residual institución
                    </p>
                    <p
                        class="mt-1 font-mono text-xs text-slate-700 dark:text-slate-300"
                    >
                        Σ (20% × residual de cada elemento)
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <p
                        class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Mitigante de entidad
                    </p>
                    <p
                        class="mt-1 font-mono text-xs text-slate-700 dark:text-slate-300"
                    >
                        35% (seis controles)
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <p
                        class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Residual final
                    </p>
                    <p
                        class="mt-1 font-mono text-xs text-slate-700 dark:text-slate-300"
                    >
                        residual × (1 − 35%)
                    </p>
                </div>
            </div>
            <div
                v-if="institucion"
                class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
            >
                <div
                    class="rounded-xl border border-slate-200 p-3 dark:border-neutral-800"
                >
                    <p class="text-xs text-slate-500">Inherente</p>
                    <p class="text-lg font-semibold">
                        {{ num(institucion.inherente) }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-slate-200 p-3 dark:border-neutral-800"
                >
                    <p class="text-xs text-slate-500">Residual</p>
                    <p class="text-lg font-semibold">
                        {{ num(institucion.residual) }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-slate-200 p-3 dark:border-neutral-800"
                >
                    <p class="text-xs text-slate-500">Mitigante entidad</p>
                    <p class="text-lg font-semibold">
                        {{ pct(institucion.mitigante_entidad) }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-slate-200 p-3 dark:border-neutral-800"
                >
                    <p class="text-xs text-slate-500">Residual final</p>
                    <p class="text-lg font-semibold">
                        {{ num(institucion.residual_final) }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-slate-200 p-3 dark:border-neutral-800"
                >
                    <p class="text-xs text-slate-500">Nivel final</p>
                    <span
                        class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="riesgoClase(institucion.nivel)"
                        >{{ institucion.nivel }}</span
                    >
                </div>
            </div>
            <div
                v-else
                class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 dark:border-neutral-800 dark:bg-neutral-900/70 dark:text-slate-300"
            >
                Aún no hay resultados calculados. La explicación aplica igual;
                calcula el EBR para ver los valores de este ejercicio.
            </div>
            <div
                class="mt-4 grid gap-2 text-xs text-slate-600 sm:grid-cols-4 dark:text-slate-300"
            >
                <div
                    class="rounded-lg bg-emerald-50 p-2 dark:bg-emerald-500/10"
                >
                    ≤ 2.00 · Bajo
                </div>
                <div class="rounded-lg bg-amber-50 p-2 dark:bg-amber-500/10">
                    &gt; 2.00 a 3.00 · Medio
                </div>
                <div class="rounded-lg bg-orange-50 p-2 dark:bg-orange-500/10">
                    &gt; 3.00 a 4.00 · Alto
                </div>
                <div class="rounded-lg bg-red-50 p-2 dark:bg-red-500/10">
                    &gt; 4.00 · Muy alto
                </div>
            </div>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
        >
            <div class="flex items-center gap-2">
                <ClipboardCheck class="h-5 w-5 text-emerald-600" />
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                    Paso 5 · Cierre y evidencia
                </h3>
            </div>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Antes de cerrar se revisan las advertencias, se registran las
                conclusiones y se exporta la evidencia. Un ejercicio cerrado no
                puede modificarse hasta que se reabra.
            </p>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <dt class="text-xs text-slate-500">Versión</dt>
                    <dd
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ ejercicio.version_metodologia }}
                    </dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <dt class="text-xs text-slate-500">Creado</dt>
                    <dd
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ fmt(ejercicio.created_at) }}
                    </dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <dt class="text-xs text-slate-500">Importado por</dt>
                    <dd
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ fmt(ejercicio.importado_por_nombre) }}
                    </dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <dt class="text-xs text-slate-500">Calculado por</dt>
                    <dd
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ fmt(ejercicio.calculado_por_nombre) }}
                    </dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <dt class="text-xs text-slate-500">Calculado el</dt>
                    <dd
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ fmt(ejercicio.calculado_at) }}
                    </dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <dt class="text-xs text-slate-500">Cerrado el</dt>
                    <dd
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ fmt(ejercicio.cerrado_at) }}
                    </dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70">
                    <dt class="text-xs text-slate-500">Estado</dt>
                    <dd
                        class="text-sm font-semibold text-slate-800 capitalize dark:text-slate-200"
                    >
                        {{ ejercicio.estado }}
                    </dd>
                </div>
            </dl>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <a
                    v-if="resultados"
                    :href="`/ebr/${ejercicio.id}/pdf`"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-neutral-700 dark:text-slate-200 dark:hover:bg-neutral-800"
                >
                    <FileText class="h-4 w-4" /> Descargar informe PDF
                </a>
                <a
                    v-if="resultados"
                    :href="`/ebr/${ejercicio.id}/excel`"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-neutral-700 dark:text-slate-200 dark:hover:bg-neutral-800"
                >
                    <FileSpreadsheet class="h-4 w-4" /> Descargar Excel
                </a>
                <span
                    v-if="!resultados"
                    class="text-xs text-slate-500 dark:text-slate-400"
                    >Las descargas estarán disponibles cuando el ejercicio tenga
                    resultados calculados.</span
                >
            </div>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
        >
            <div class="flex items-center gap-2">
                <CheckCircle2 class="h-5 w-5 text-emerald-600" />
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                    Semáforo de auditoría
                </h3>
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Resumen rápido del estado del ejercicio. No sustituye la
                revisión de los resultados ni de las advertencias.
            </p>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="item in checklist"
                    :key="item.etiqueta"
                    class="rounded-xl border p-3"
                    :class="
                        item.estado === 'ok'
                            ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                            : item.estado === 'alerta'
                              ? 'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10'
                              : 'border-slate-200 bg-slate-50 dark:border-neutral-800 dark:bg-neutral-900/70'
                    "
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle2
                            v-if="item.estado === 'ok'"
                            class="h-4 w-4 text-emerald-600"
                        />
                        <AlertTriangle
                            v-else-if="item.estado === 'alerta'"
                            class="h-4 w-4 text-amber-600"
                        />
                        <Info v-else class="h-4 w-4 text-slate-500" />
                        <span
                            class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                            >{{ item.etiqueta }}</span
                        >
                    </div>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                        {{ item.detalle }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ item.nota }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
        >
            <div class="flex items-center gap-2">
                <Layers class="h-5 w-5 text-emerald-600" />
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                    Glosario
                </h3>
            </div>
            <dl class="mt-4 grid gap-3 md:grid-cols-2">
                <div
                    v-for="item in glosario"
                    :key="item.termino"
                    class="rounded-xl bg-slate-50 p-3 dark:bg-neutral-900/70"
                >
                    <dt
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ item.termino }}
                    </dt>
                    <dd class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ item.definicion }}
                    </dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                Referencia: Informe anual de exposición a riesgos de LD/FT 2025,
                secciones 2.1, 2.2 y 3.1–3.3; documentación del módulo EBR.
            </p>
        </section>
    </div>
</template>
