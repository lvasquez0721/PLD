<script setup lang="ts">
import MetodologiaCalculo from '@/components/pages/ebr/MetodologiaCalculo.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { riesgoClase, scopeLabel } from '@/lib/ebr';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Calculator,
    CheckCircle2,
    Download,
    Lock,
    RefreshCw,
    ShieldCheck,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Ejercicio {
    id: number;
    anio: number;
    nombre: string;
    fecha_inicio: string;
    fecha_fin: string;
    estado: 'borrador' | 'calculado' | 'cerrado';
    version_metodologia: string;
    mitigantes: any[];
    resultados: any | null;
    totales: any | null;
    comparativo: any | null;
    advertencias: string[];
    observaciones: string | null;
    created_at: string | null;
    calculado_at: string | null;
    cerrado_at: string | null;
    importado_por_nombre: string | null;
    calculado_por_nombre: string | null;
    polizas_count: number;
    operaciones_count: number;
    siniestros_count: number;
    alertas_count: number;
}

const props = defineProps<{
    ejercicio: Ejercicio;
    metodologia: any;
    importaciones: any[];
    plantillas: Record<string, any>;
    mitigantesDefault: any[];
    nivelesEfectividad: Record<string, number>;
    puedeEditar: boolean;
    importacionReciente: any | null;
    erroresImportacion: any[];
    toast?: { type: 'success' | 'error' | 'warning'; message: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'EBR / Evaluación de Riesgos', href: '/ebr' },
    {
        title: `EBR ${props.ejercicio.anio}`,
        href: `/ebr/${props.ejercicio.id}`,
    },
];

const tab = ref<
    | 'resumen'
    | 'insumos'
    | 'resultados'
    | 'mitigantes'
    | 'metodologia'
    | 'advertencias'
>('resumen');
const tabs = [
    { key: 'resumen', label: 'Resumen' },
    { key: 'insumos', label: 'Insumos' },
    { key: 'resultados', label: 'Resultados' },
    { key: 'mitigantes', label: 'Mitigantes' },
    { key: 'metodologia', label: 'Metodología' },
    { key: 'advertencias', label: 'Advertencias' },
] as const;

const r = computed(() => props.ejercicio.resultados);
const elementos = computed(() => r.value?.elementos ?? null);
const institucion = computed(() => r.value?.institucion ?? null);

const importForms = {
    clientes: useForm<{ archivo: File | null }>({ archivo: null }),
    siniestros: useForm<{ archivo: File | null }>({ archivo: null }),
    alertas: useForm<{ archivo: File | null }>({ archivo: null }),
};

const observacionesForm = useForm({
    observaciones: props.ejercicio.observaciones ?? '',
});
const mitigantesCatalogo = new Map(
    (props.mitigantesDefault ?? []).map((m: any) => [m.id, m]),
);

const mitigantesIniciales = (props.mitigantesDefault ?? []).map((m: any) => {
    const base = mitigantesCatalogo.get(m.id) ?? m;

    return {
        ...base,
        nivel: base.nivel,
    };
});

const mitigantesForm = useForm({
    mitigantes: JSON.parse(JSON.stringify(mitigantesIniciales)),
});

function subir(tipo: 'clientes' | 'siniestros' | 'alertas', event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    importForms[tipo].archivo = file;
}

function prevalidar(tipo: 'clientes' | 'siniestros' | 'alertas') {
    importForms[tipo].post(`/ebr/${props.ejercicio.id}/importar/${tipo}`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => importForms[tipo].reset(),
    });
}

function confirmar(importacion: any) {
    router.post(
        `/ebr/${props.ejercicio.id}/importaciones/${importacion.id}/confirmar`,
        {},
        { preserveScroll: true },
    );
}

function calcular() {
    if (!confirm('¿Calcular el EBR con los insumos importados?')) return;
    router.post(
        `/ebr/${props.ejercicio.id}/calcular`,
        {},
        { preserveScroll: true },
    );
}

function cerrar() {
    if (
        !confirm(
            'Al cerrar el ejercicio no podrás modificarlo hasta reabrirlo. ¿Continuar?',
        )
    )
        return;
    router.post(
        `/ebr/${props.ejercicio.id}/cerrar`,
        {},
        { preserveScroll: true },
    );
}

function reabrir() {
    if (!confirm('¿Reabrir el ejercicio para corregir insumos o mitigantes?'))
        return;
    router.post(
        `/ebr/${props.ejercicio.id}/reabrir`,
        {},
        { preserveScroll: true },
    );
}

function guardarObservaciones() {
    observacionesForm.put(`/ebr/${props.ejercicio.id}/observaciones`, {
        preserveScroll: true,
    });
}

function pct(valor: number | undefined) {
    return `${(Number(valor ?? 0) * 100).toFixed(1)}%`;
}

function num(valor: number | undefined) {
    return Number(valor ?? 0).toFixed(2);
}

const mitigantesEntidad = computed(() =>
    (mitigantesForm.mitigantes as any[])
        .filter((m) => m.scope === 'entidad')
        .reduce(
            (total, m) =>
                total +
                (Number(m.max) || 0) * (props.nivelesEfectividad[m.nivel] ?? 1),
            0,
        ),
);

const entidadDesviada = computed(
    () => Math.abs(mitigantesEntidad.value - 0.35) > 0.0001,
);

function estadoImportacion(estado: string) {
    if (estado === 'importado')
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
    if (estado === 'error')
        return 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300';
    return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
}
</script>

<template>
    <Head :title="`EBR ${ejercicio.anio}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <div
                class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-sm font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        <ShieldCheck class="h-4 w-4" /> Ejercicio anual
                    </div>
                    <h1
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        EBR {{ ejercicio.anio }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Periodo {{ ejercicio.fecha_inicio }} al
                        {{ ejercicio.fecha_fin }} · {{ ejercicio.estado }}
                    </p>
                </div>
                <div v-if="puedeEditar" class="flex flex-wrap gap-2">
                    <button
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="ejercicio.estado === 'cerrado'"
                        @click="calcular"
                    >
                        <Calculator class="h-4 w-4" /> Calcular EBR
                    </button>
                    <button
                        v-if="ejercicio.estado === 'calculado'"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-neutral-700 dark:text-slate-200 dark:hover:bg-neutral-800"
                        @click="cerrar"
                    >
                        <Lock class="h-4 w-4" /> Cerrar
                    </button>
                    <button
                        v-if="ejercicio.estado === 'cerrado'"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-neutral-700 dark:text-slate-200 dark:hover:bg-neutral-800"
                        @click="reabrir"
                    >
                        <RefreshCw class="h-4 w-4" /> Reabrir
                    </button>
                </div>
            </div>

            <div
                v-if="toast"
                class="rounded-xl border px-4 py-3 text-sm"
                :class="
                    toast.type === 'error'
                        ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300'
                        : toast.type === 'warning'
                          ? 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'
                          : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                "
            >
                {{ toast.message }}
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <div
                        class="text-xs tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Inherente institución
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{
                            ejercicio.totales
                                ? num(ejercicio.totales.inherente)
                                : '—'
                        }}
                    </div>
                    <div
                        v-if="ejercicio.totales"
                        class="mt-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="riesgoClase(institucion?.nivel_inherente)"
                    >
                        {{ institucion?.nivel_inherente }}
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <div
                        class="text-xs tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Residual s/ mitigantes elemento
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{
                            ejercicio.totales
                                ? num(ejercicio.totales.residual)
                                : '—'
                        }}
                    </div>
                    <div
                        v-if="ejercicio.totales"
                        class="mt-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="riesgoClase(institucion?.nivel_residual)"
                    >
                        {{ institucion?.nivel_residual }}
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <div
                        class="text-xs tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Residual final
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{
                            ejercicio.totales
                                ? num(ejercicio.totales.residual_final)
                                : '—'
                        }}
                    </div>
                    <div
                        v-if="ejercicio.totales"
                        class="mt-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="riesgoClase(ejercicio.totales.nivel)"
                    >
                        {{ ejercicio.totales.nivel }}
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <div
                        class="text-xs tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Insumos
                    </div>
                    <div
                        class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ ejercicio.polizas_count }} pólizas
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ ejercicio.siniestros_count }} siniestros ·
                        {{ ejercicio.alertas_count }} alertas
                    </div>
                    <div
                        v-if="ejercicio.calculado_at"
                        class="mt-1 text-xs text-slate-400"
                    >
                        Calculado: {{ ejercicio.calculado_at }}
                    </div>
                </div>
            </div>

            <div
                class="flex flex-wrap gap-2 border-b border-slate-200 pb-2 dark:border-neutral-800"
            >
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        tab === t.key
                            ? 'bg-slate-900 text-white dark:bg-emerald-600'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-neutral-800'
                    "
                    @click="tab = t.key"
                >
                    {{ t.label }}
                </button>
            </div>

            <div v-if="tab === 'resumen'" class="grid gap-5 xl:grid-cols-3">
                <div
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2 dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <div
                        class="border-b border-slate-200 px-5 py-4 dark:border-neutral-800"
                    >
                        <h2
                            class="font-semibold text-slate-900 dark:text-slate-100"
                        >
                            Riesgo por elemento
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-slate-200 text-sm dark:divide-neutral-800"
                        >
                            <thead
                                class="bg-slate-50 text-left text-xs tracking-wide text-slate-500 uppercase dark:bg-neutral-900/70 dark:text-slate-400"
                            >
                                <tr>
                                    <th class="px-4 py-3">Elemento</th>
                                    <th class="px-4 py-3">Peso</th>
                                    <th class="px-4 py-3">Inherente</th>
                                    <th class="px-4 py-3">Residual</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-neutral-900"
                            >
                                <tr
                                    v-for="e in institucion?.detalle ?? []"
                                    :key="e.clave"
                                >
                                    <td
                                        class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200"
                                    >
                                        {{ e.elemento }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        {{ pct(e.peso) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold">{{
                                            num(e.inherente)
                                        }}</span>
                                        <span
                                            class="ml-2 inline-flex rounded-full px-2 py-0.5 text-xs"
                                            :class="riesgoClase(e.nivel)"
                                            >{{ e.nivel }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold">{{
                                            num(e.residual)
                                        }}</span>
                                        <span
                                            class="ml-2 inline-flex rounded-full px-2 py-0.5 text-xs"
                                            :class="
                                                riesgoClase(e.nivel_residual)
                                            "
                                            >{{ e.nivel_residual }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!institucion" class="text-slate-500">
                                    <td
                                        colspan="4"
                                        class="px-4 py-6 text-center"
                                    >
                                        Aún no hay resultados. Importa los
                                        insumos y calcula el EBR.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <h2
                        class="font-semibold text-slate-900 dark:text-slate-100"
                    >
                        Resultado institucional
                    </h2>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Mitigante entidad</span
                            ><span class="font-semibold">{{
                                pct(institucion?.mitigante_entidad)
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Residual final</span
                            ><span class="font-semibold">{{
                                num(institucion?.residual_final)
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Nivel final</span
                            ><span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="riesgoClase(ejercicio.totales?.nivel)"
                                >{{
                                    ejercicio.totales?.nivel ?? 'Sin calcular'
                                }}</span
                            >
                        </div>
                    </div>
                    <div
                        v-if="r?.comparativo?.institucion"
                        class="mt-5 rounded-xl bg-slate-50 p-3 text-xs dark:bg-neutral-900"
                    >
                        <div
                            class="font-semibold text-slate-700 dark:text-slate-300"
                        >
                            Vs. {{ r.comparativo.anio_anterior }}
                        </div>
                        <div
                            class="mt-2 flex items-center gap-1"
                            :class="
                                r.comparativo.institucion
                                    .variacion_residual_final <= 0
                                    ? 'text-emerald-600'
                                    : 'text-red-600'
                            "
                        >
                            <ArrowDownRight
                                v-if="
                                    r.comparativo.institucion
                                        .variacion_residual_final <= 0
                                "
                                class="h-4 w-4"
                            />
                            <ArrowUpRight v-else class="h-4 w-4" />
                            {{
                                num(
                                    r.comparativo.institucion
                                        .variacion_residual_final,
                                )
                            }}
                            en residual final
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="tab === 'insumos'" class="space-y-5">
                <div
                    v-if="importacionReciente"
                    class="rounded-2xl border p-4 text-sm"
                    :class="
                        importacionReciente.estado === 'error'
                            ? 'border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10'
                            : 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                    "
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <div class="font-semibold">
                                {{ importacionReciente.nombre_archivo }} ·
                                {{ importacionReciente.tipo }}
                            </div>
                            <div class="text-xs">
                                {{ importacionReciente.filas_validas }} fila(s)
                                válida(s),
                                {{ importacionReciente.filas_error }} error(es).
                                Estado: {{ importacionReciente.estado }}
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                v-if="
                                    importacionReciente.estado === 'prevalidado'
                                "
                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white"
                                @click="confirmar(importacionReciente)"
                            >
                                Confirmar importación
                            </button>
                            <a
                                v-if="importacionReciente.filas_error > 0"
                                :href="`/ebr/${ejercicio.id}/importaciones/${importacionReciente.id}/errores`"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-neutral-700"
                                >Descargar errores</a
                            >
                        </div>
                    </div>
                    <div
                        v-if="erroresImportacion.length"
                        class="mt-3 max-h-48 overflow-auto rounded-lg bg-white/70 p-3 text-xs dark:bg-neutral-950/60"
                    >
                        <div
                            v-for="(err, i) in erroresImportacion.slice(0, 100)"
                            :key="i"
                            class="border-b border-slate-100 py-1 last:border-0 dark:border-neutral-800"
                        >
                            Línea {{ err.linea }} ·
                            {{ err.campo || 'general' }}: {{ err.mensaje }}
                            <span v-if="err.valor">({{ err.valor }})</span>
                        </div>
                    </div>
                    <div
                        v-if="importacionReciente.resumen?.advertencias?.length"
                        class="mt-3 max-h-40 overflow-auto rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200"
                    >
                        <div class="mb-1 font-semibold">Advertencias</div>
                        <div
                            v-for="(
                                warn, i
                            ) in importacionReciente.resumen.advertencias.slice(
                                0,
                                50,
                            )"
                            :key="`w-${i}`"
                            class="border-b border-amber-100 py-1 last:border-0 dark:border-amber-500/20"
                        >
                            Línea {{ warn.linea }} ·
                            {{ warn.campo || 'general' }}: {{ warn.mensaje }}
                            <span v-if="warn.valor">({{ warn.valor }})</span>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-3">
                    <div
                        v-for="tipo in [
                            'clientes',
                            'siniestros',
                            'alertas',
                        ] as const"
                        :key="tipo"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h2
                                    class="font-semibold text-slate-900 capitalize dark:text-slate-100"
                                >
                                    CSV de {{ tipo }}
                                </h2>
                                <p
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Descarga la plantilla, captura o exporta la
                                    información y cárgala en formato CSV.
                                </p>
                                <p
                                    v-if="tipo === 'clientes'"
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Cada fila es una emisión o endoso. Usa
                                    id_operacion para distinguir operaciones de
                                    una misma póliza.
                                </p>
                                <p
                                    v-else-if="tipo === 'alertas'"
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    El vínculo puede ser por id_operacion,
                                    póliza o cliente. La fecha de detección no
                                    filtra el EBR.
                                </p>
                            </div>
                            <a
                                :href="`/ebr/plantillas/${tipo}`"
                                class="rounded-lg border border-slate-300 p-2 text-slate-600 hover:bg-slate-100 dark:border-neutral-700 dark:text-slate-300 dark:hover:bg-neutral-800"
                                title="Descargar plantilla"
                                ><Download class="h-4 w-4"
                            /></a>
                        </div>
                        <div class="mt-4 space-y-3">
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                class="block w-full rounded-lg border border-slate-300 bg-white p-2 text-xs dark:border-neutral-700 dark:bg-neutral-900"
                                @change="subir(tipo, $event)"
                            />
                            <div
                                v-if="importForms[tipo].errors.archivo"
                                class="text-xs text-red-600"
                            >
                                {{ importForms[tipo].errors.archivo }}
                            </div>
                            <button
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                :disabled="
                                    !importForms[tipo].archivo ||
                                    importForms[tipo].processing ||
                                    !puedeEditar ||
                                    ejercicio.estado === 'cerrado'
                                "
                                @click="prevalidar(tipo)"
                            >
                                <Upload class="h-4 w-4" />
                                {{
                                    importForms[tipo].processing
                                        ? 'Validando…'
                                        : 'Prevalidar archivo'
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <div
                        class="border-b border-slate-200 px-5 py-4 dark:border-neutral-800"
                    >
                        <h2
                            class="font-semibold text-slate-900 dark:text-slate-100"
                        >
                            Historial de importaciones
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-slate-200 text-sm dark:divide-neutral-800"
                        >
                            <thead
                                class="bg-slate-50 text-left text-xs tracking-wide text-slate-500 uppercase dark:bg-neutral-900/70 dark:text-slate-400"
                            >
                                <tr>
                                    <th class="px-4 py-3">Fecha</th>
                                    <th class="px-4 py-3">Tipo</th>
                                    <th class="px-4 py-3">Archivo</th>
                                    <th class="px-4 py-3">Filas</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-neutral-900"
                            >
                                <tr v-for="i in importaciones" :key="i.id">
                                    <td
                                        class="px-4 py-3 text-xs text-slate-500"
                                    >
                                        {{ i.created_at }}
                                    </td>
                                    <td class="px-4 py-3 capitalize">
                                        {{ i.tipo }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        {{ i.nombre_archivo }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        {{ i.filas_validas }} /
                                        {{ i.total_filas }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="estadoImportacion(i.estado)"
                                            >{{ i.estado }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            v-if="
                                                i.estado === 'prevalidado' &&
                                                puedeEditar &&
                                                ejercicio.estado !== 'cerrado'
                                            "
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white"
                                            @click="confirmar(i)"
                                        >
                                            Confirmar
                                        </button>
                                        <a
                                            v-if="i.filas_error > 0"
                                            :href="`/ebr/${ejercicio.id}/importaciones/${i.id}/errores`"
                                            class="ml-2 text-xs font-semibold text-red-600"
                                            >Errores</a
                                        >
                                    </td>
                                </tr>
                                <tr v-if="importaciones.length === 0">
                                    <td
                                        colspan="6"
                                        class="px-4 py-6 text-center text-slate-500"
                                    >
                                        Sin importaciones.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="tab === 'resultados'" class="space-y-6">
                <div
                    v-if="!r"
                    class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500 dark:border-neutral-700"
                >
                    Aún no hay resultados calculados.
                </div>
                <template v-else>
                    <div
                        class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                    >
                        <div
                            class="border-b border-slate-200 px-5 py-4 dark:border-neutral-800"
                        >
                            <h2
                                class="font-semibold text-slate-900 dark:text-slate-100"
                            >
                                Productos y Servicios
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table
                                class="min-w-full divide-y divide-slate-200 text-sm dark:divide-neutral-800"
                            >
                                <thead
                                    class="bg-slate-50 text-left text-xs text-slate-500 uppercase dark:bg-neutral-900/70"
                                >
                                    <tr>
                                        <th class="px-4 py-3">Producto</th>
                                        <th class="px-4 py-3">Inherente</th>
                                        <th class="px-4 py-3">Residual</th>
                                        <th class="px-4 py-3">Ops. entrada</th>
                                        <th class="px-4 py-3">Monto entrada</th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-neutral-900"
                                >
                                    <tr
                                        v-for="p in elementos?.productos
                                            ?.detalle ?? []"
                                        :key="p.grupo"
                                    >
                                        <td class="px-4 py-3 font-medium">
                                            {{ p.grupo }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ num(p.inherente) }}
                                            <span
                                                class="ml-1 rounded-full px-2 py-0.5 text-xs"
                                                :class="riesgoClase(p.nivel)"
                                                >{{ p.nivel }}</span
                                            >
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ num(p.residual) }}
                                            <span
                                                class="ml-1 rounded-full px-2 py-0.5 text-xs"
                                                :class="
                                                    riesgoClase(
                                                        p.nivel_residual,
                                                    )
                                                "
                                                >{{ p.nivel_residual }}</span
                                            >
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ pct(p.distribuciones?.ops_in) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{
                                                pct(p.distribuciones?.monto_in)
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid gap-5 xl:grid-cols-2">
                        <div
                            v-for="tipo in ['pf', 'pm'] as const"
                            :key="tipo"
                            class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-neutral-800"
                            >
                                <h2
                                    class="font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    {{
                                        tipo === 'pf'
                                            ? 'Personas Físicas'
                                            : 'Personas Morales'
                                    }}
                                </h2>
                                <span class="text-sm font-semibold"
                                    >{{
                                        num(
                                            elementos?.clientes?.[tipo]
                                                ?.inherente,
                                        )
                                    }}
                                    /
                                    {{
                                        num(
                                            elementos?.clientes?.[tipo]
                                                ?.residual,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="overflow-x-auto">
                                <table
                                    class="min-w-full divide-y divide-slate-200 text-xs dark:divide-neutral-800"
                                >
                                    <thead
                                        class="bg-slate-50 text-left text-slate-500 uppercase dark:bg-neutral-900/70"
                                    >
                                        <tr>
                                            <th class="px-3 py-2">Indicador</th>
                                            <th class="px-3 py-2">Peso</th>
                                            <th class="px-3 py-2">Inherente</th>
                                            <th class="px-3 py-2">Residual</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-100 dark:divide-neutral-900"
                                    >
                                        <tr
                                            v-for="i in elementos?.clientes?.[
                                                tipo
                                            ]?.indicadores ?? []"
                                            :key="i.clave"
                                        >
                                            <td class="px-3 py-2">
                                                {{ i.nombre }}
                                            </td>
                                            <td class="px-3 py-2">
                                                {{ pct(i.peso) }}
                                            </td>
                                            <td class="px-3 py-2">
                                                {{ num(i.inherente) }}
                                            </td>
                                            <td class="px-3 py-2">
                                                {{ num(i.residual) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-neutral-800"
                        >
                            <h2
                                class="font-semibold text-slate-900 dark:text-slate-100"
                            >
                                Países y Áreas Geográficas
                            </h2>
                            <span class="text-sm text-slate-500"
                                >{{
                                    elementos?.geografia?.pais?.pais ?? 'México'
                                }}
                                {{ num(elementos?.geografia?.pais?.inherente) }}
                                · Estados
                                {{ num(elementos?.geografia?.inherente) }}</span
                            >
                        </div>
                        <div class="max-h-[420px] overflow-auto">
                            <table
                                class="min-w-full divide-y divide-slate-200 text-sm dark:divide-neutral-800"
                            >
                                <thead
                                    class="sticky top-0 bg-slate-50 text-left text-xs text-slate-500 uppercase dark:bg-neutral-900"
                                >
                                    <tr>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3">Inherente</th>
                                        <th class="px-4 py-3">Residual</th>
                                        <th class="px-4 py-3">Ops. salida</th>
                                        <th class="px-4 py-3">Monto salida</th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-neutral-900"
                                >
                                    <tr
                                        v-for="e in elementos?.geografia
                                            ?.estados ?? []"
                                        :key="e.estado"
                                    >
                                        <td class="px-4 py-2">
                                            {{ e.estado }}
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ num(e.inherente) }}
                                            <span
                                                class="ml-1 rounded-full px-2 py-0.5 text-xs"
                                                :class="riesgoClase(e.nivel)"
                                                >{{ e.nivel }}</span
                                            >
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ num(e.residual) }}
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ pct(e.distribuciones?.ops_out) }}
                                        </td>
                                        <td class="px-4 py-2">
                                            {{
                                                pct(e.distribuciones?.monto_out)
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid gap-5 xl:grid-cols-2">
                        <div
                            class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                        >
                            <div
                                class="border-b border-slate-200 px-5 py-4 dark:border-neutral-800"
                            >
                                <h2 class="font-semibold">Canales</h2>
                            </div>
                            <table class="min-w-full text-sm">
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-neutral-900"
                                >
                                    <tr
                                        v-for="c in elementos?.canales
                                            ?.detalle ?? []"
                                        :key="c.grupo"
                                    >
                                        <td class="px-4 py-3">{{ c.grupo }}</td>
                                        <td class="px-4 py-3">
                                            {{ num(c.inherente) }}
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs"
                                                :class="riesgoClase(c.nivel)"
                                                >{{ c.nivel }}</span
                                            >
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ num(c.residual) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                        >
                            <div
                                class="border-b border-slate-200 px-5 py-4 dark:border-neutral-800"
                            >
                                <h2 class="font-semibold">Transacciones</h2>
                                <p class="text-xs text-slate-500">
                                    {{ r?.resumen_datos?.alertas ?? 0 }} alertas
                                    generadas ·
                                    {{
                                        r?.resumen_datos?.alertas_procedentes ??
                                        0
                                    }}
                                    procedentes ·
                                    {{
                                        r?.resumen_datos?.alertas_relevantes ??
                                        0
                                    }}
                                    relevantes
                                </p>
                            </div>
                            <table class="min-w-full text-sm">
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-neutral-900"
                                >
                                    <tr
                                        v-for="t in elementos?.transacciones
                                            ?.indicadores ?? []"
                                        :key="t.codigo"
                                    >
                                        <td class="px-4 py-3">
                                            {{ t.nombre }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ t.alertas }} alertas
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ num(t.inherente) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>

            <div v-if="tab === 'mitigantes'" class="grid gap-5 xl:grid-cols-3">
                <div
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2 dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <div
                        class="border-b border-slate-200 px-5 py-4 dark:border-neutral-800"
                    >
                        <h2 class="font-semibold">Mitigantes del ejercicio</h2>
                        <p class="text-xs text-slate-500">
                            Los mitigantes de la metodología 2025 son fijos: 20%
                            en el indicador de producto, 10% en el elemento de
                            clientes y 35% a nivel entidad.
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            El efecto se calcula como porcentaje máximo × nivel
                            de efectividad definido en la metodología.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-slate-200 text-sm dark:divide-neutral-800"
                        >
                            <thead
                                class="bg-slate-50 text-left text-xs text-slate-500 uppercase dark:bg-neutral-900/70"
                            >
                                <tr>
                                    <th class="px-4 py-3">Mitigante</th>
                                    <th class="px-4 py-3">Alcance</th>
                                    <th class="px-4 py-3">Máx.</th>
                                    <th class="px-4 py-3">Nivel</th>
                                    <th class="px-4 py-3">Efecto</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-neutral-900"
                            >
                                <tr
                                    v-for="m in mitigantesForm.mitigantes"
                                    :key="m.id"
                                >
                                    <td class="px-4 py-3">{{ m.nombre }}</td>
                                    <td
                                        class="px-4 py-3 text-xs text-slate-500"
                                    >
                                        {{ scopeLabel(m.scope) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-medium">{{
                                            pct(m.max)
                                        }}</span>
                                        <span
                                            class="ml-1 text-xs text-slate-400"
                                            >fijo</span
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-xs text-slate-500"
                                            >{{ m.nivel }} (fijo)</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 font-semibold">
                                        {{
                                            pct(
                                                (Number(m.max) || 0) *
                                                    (nivelesEfectividad[
                                                        m.nivel
                                                    ] ?? 1),
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div
                            class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm dark:border-neutral-800"
                        >
                            <span class="text-slate-500"
                                >Total mitigantes de entidad</span
                            >
                            <span class="font-semibold">{{
                                pct(mitigantesEntidad)
                            }}</span>
                        </div>
                        <p
                            v-if="entidadDesviada"
                            class="px-4 pb-3 text-xs text-amber-600"
                        >
                            El informe 2025 de referencia usa 35% con todos los
                            controles en nivel Optimizado.
                        </p>
                    </div>
                    <div
                        class="border-t border-slate-200 px-4 py-3 text-xs text-slate-500 dark:border-neutral-800"
                    >
                        Los porcentajes y niveles de efectividad no son
                        editables en la metodología 2025.
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <h2 class="font-semibold">Observaciones y conclusiones</h2>
                    <textarea
                        v-model="observacionesForm.observaciones"
                        rows="10"
                        class="mt-3 w-full rounded-lg border border-slate-300 p-3 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                        :disabled="
                            !puedeEditar || ejercicio.estado === 'cerrado'
                        "
                        placeholder="Conclusiones del ejercicio, comentarios del Comité, etc."
                    ></textarea>
                    <div class="mt-3 flex justify-end">
                        <button
                            v-if="puedeEditar && ejercicio.estado !== 'cerrado'"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-neutral-700"
                            @click="guardarObservaciones"
                        >
                            Guardar observaciones
                        </button>
                    </div>
                </div>
            </div>

            <MetodologiaCalculo
                v-if="tab === 'metodologia'"
                :ejercicio="ejercicio"
                :metodologia="metodologia"
                :mitigantes-default="mitigantesDefault"
                :niveles-efectividad="nivelesEfectividad"
                :resultados="r"
            />

            <div
                v-if="tab === 'advertencias'"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
            >
                <h2 class="font-semibold">Advertencias del cálculo</h2>
                <div
                    v-if="(ejercicio.advertencias ?? []).length === 0"
                    class="mt-3 flex items-center gap-2 text-sm text-emerald-600"
                >
                    <CheckCircle2 class="h-4 w-4" /> Sin advertencias.
                </div>
                <ul
                    v-else
                    class="mt-3 space-y-2 text-sm text-amber-700 dark:text-amber-300"
                >
                    <li
                        v-for="(a, i) in ejercicio.advertencias"
                        :key="i"
                        class="flex gap-2"
                    >
                        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                        {{ a }}
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
