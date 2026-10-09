<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Select from '@/components/forms/Select.vue';
import DateInput from '@/components/forms/DateInput.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const tipoReporteFiltro = ref('Todos');
const estatusFiltro     = ref('');
// Usar strings ISO directamente para evitar bugs al teclear año en el input nativo.
// Antes se usaba Date + computed, lo cual interfería con la edición manual del año.
const fechaInicialStr   = ref('');
const fechaFinalStr     = ref('');

const opcionesTipoReporte = [
    { value: 'Todos',         label: 'Todos' },
    { value: 'Monto',         label: 'Monto' },
    { value: 'Monto Inusual', label: 'Monto Inusual' },
    { value: 'Nuevo',         label: 'Nuevo' },
];

const opcionesEstatus = [
    { value: 'Todos',         label: 'Todos' },
    { value: 'Enviado',       label: 'Enviado' },
    { value: 'Por reportar',  label: 'Por reportar' },
];

interface Alerta {
    IDRegistroAlerta:    number;
    Folio:               string | null;
    Patron:              string | null;
    IDCliente:           number | null;
    Cliente:             string | null;
    Poliza:              string | null;
    FechaDeteccion:      string | null;
    IDOperacion:         number | null;
    HoraDeteccion:       string | null;
    FechaOperacion:      string | null;
    HoraOperacion:       string | null;
    MontoOperacion:      number | null;
    InstrumentoMonetario: string | null;
    RFCAgente:           string | null;
    Agente:              string | null;
    Estatus:             string | null;
    Descripcion:         string | null;
    Razones:             string | null;
    Evidencias:          string | null;
    IDReporteOP:         number | null;
    IDMoneda:            string | null;
    // --- Operación base (tbOperaciones) ---
    OperacionFolioEndoso?:          string | null;
    OperacionPrimaTotal?:           number | null;
    OperacionGastosEmision?:        number | null;
    OperacionIDMoneda?:             string | null;
    OperacionIDFormaPago?:          string | number | null;
    OperacionFechaEmision?:         string | null;
    OperacionFechaInicioVigencia?:  string | null;
    OperacionFechaFinVigencia?:     string | null;
    OperacionTipoDocumento?:        string | null;
    OperacionEsquemaDePago?:        string | null;
    OperacionPagaTercero?:          number | boolean | null;
    OperacionCancelada?:            number | boolean | null;
    OperacionEsEndosoCancelacion?:  number | boolean | null;
    OperacionCancelaPoliza?:        number | boolean | null;
    OperacionNombreAgente?:         string | null;
    OperacionAPaternoAgente?:       string | null;
    OperacionAMaternoAgente?:       string | null;
    OperacionRazonSocialAgente?:    string | null;
    OperacionRFCAgente?:            string | null;
    OperacionFolioPoliza?:          string | null;
}

const resultados  = ref<Alerta[]>([]);
const isLoading   = ref(false);
const hasBuscado  = ref(false);
const search      = ref('');
const searchInput = ref('');
let searchTimer: number | null = null;
const perPage     = ref(10);
const currentPage = ref(1);

function normalize(text: string): string {
    return (text || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function parseTokens(q: string): string[] {
    const tokens: string[] = [];
    const normalized = normalize(q).trim();
    if (!normalized) return tokens;
    const re = /"([^"]+)"|(\\S+)/g;
    let m: RegExpExecArray | null;
    while ((m = re.exec(normalized)) !== null) {
        const token = (m[1] || m[2] || '').trim();
        if (token) tokens.push(token);
    }
    return tokens;
}

const filteredResultados = computed(() => {
    const tokens = parseTokens(search.value);
    if (tokens.length === 0) return resultados.value;
    return resultados.value.filter((item) => {
        const values = Object.values(item ?? {}).map((v) => normalize(String(v ?? '')));
        return tokens.every((t) => values.some((val) => val.includes(t)));
    });
});

const paginatedResultados = computed(() => {
    if (perPage.value === -1) return filteredResultados.value;
    const start = (currentPage.value - 1) * perPage.value;
    return filteredResultados.value.slice(start, start + perPage.value);
});

const totalPages = computed(() => {
    if (perPage.value === -1) return 1;
    return Math.ceil(filteredResultados.value.length / perPage.value || 1);
});

// --- Selección de elementos por reportar ---
const seleccionados = ref<number[]>([]);

const seleccionables = computed(() =>
    filteredResultados.value.filter((item) => item.Estatus === 'Por reportar')
);

const todosSeleccionados = computed<boolean>({
    get() {
        const total = seleccionables.value.length;
        return total > 0 && seleccionados.value.length === total;
    },
    set(value: boolean) {
        seleccionados.value = value ? seleccionables.value.map((i) => i.IDRegistroAlerta) : [];
    },
});

const seleccionParcial = computed(() =>
    seleccionados.value.length > 0 && seleccionados.value.length < seleccionables.value.length
);

function toggleSeleccion(id: number) {
    if (seleccionados.value.includes(id)) {
        seleccionados.value = seleccionados.value.filter((item) => item !== id);
    } else {
        seleccionados.value.push(id);
    }
}

function formatFechaCorta(fecha: string | null | undefined): string {
    if (!fecha) return '—';
    try {
        const d = new Date(fecha);
        if (isNaN(d.getTime())) return String(fecha).slice(0,10);
        return d.toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' });
    } catch { return String(fecha).slice(0,10); }
}
function formatMonto(n: number | null | undefined): string {
    if (n == null || n === '') return '—';
    const num = Number(n);
    if (isNaN(num)) return '—';
    return num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formaPagoLabel(id: any): string {
    const map: Record<string,string> = { '1':'Efectivo','2':'Cheque','3':'Transferencia','4':'Tarjeta','5':'Monedero','6':'Dinero electrónico' };
    if (id == null || id === '') return '—';
    return map[String(id)] || `ID ${id}`;
}
function agenteOperacionNombre(a: Alerta): string {
    if (a.OperacionRazonSocialAgente) return a.OperacionRazonSocialAgente;
    const partes = [a.OperacionNombreAgente, a.OperacionAPaternoAgente, a.OperacionAMaternoAgente].filter(Boolean);
    if (partes.length) return partes.join(' ');
    if (a.OperacionRFCAgente) return a.OperacionRFCAgente;
    return a.Agente || '—';
}
function esCancelada(a: Alerta): boolean {
    return !!(a.OperacionCancelada == 1 || (a.OperacionCancelada as any) === true || a.OperacionEsEndosoCancelacion == 1 || (a.OperacionEsEndosoCancelacion as any) === true || a.OperacionCancelaPoliza == 1 || (a.OperacionCancelaPoliza as any) === true);
}

function nextPage() { if (currentPage.value < totalPages.value) currentPage.value++; }
function prevPage() { if (currentPage.value > 1) currentPage.value--; }
watch([search, perPage], () => {
    currentPage.value = 1;
    seleccionados.value = [];
});
watch(searchInput, (v) => {
    if (searchTimer) window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => { search.value = v; }, 250);
});

const showingMessage = computed(() => {
    if (isLoading.value || !filteredResultados.value.length) return '';
    const total = filteredResultados.value.length;
    if (perPage.value === -1) return `Mostrando todos los ${total.toLocaleString()} registros.`;
    const start = (currentPage.value - 1) * perPage.value + 1;
    const end   = Math.min(start + perPage.value - 1, total);
    return `Mostrando ${start.toLocaleString()} a ${end.toLocaleString()} de un total de ${total.toLocaleString()} registros.`;
});

const breadcrumbs = [{ title: 'Reporte de operaciones', href: '' }];

function verDetalleAlerta(alerta: Alerta) {
    router.visit(`/alertas/${alerta.IDRegistroAlerta}/detalles`);
}

const buscar = async () => {
    const params = new URLSearchParams({
        tipo_reporte: tipoReporteFiltro.value || '',
        estatus:      estatusFiltro.value     || '',
        fecha_ini:    fechaInicialStr.value   || '',
        fecha_fin:    fechaFinalStr.value     || '',
    });
    isLoading.value = true;
    hasBuscado.value = true;
    try {
        const res  = await fetch(`/reporte-operaciones/obtener?${params.toString()}`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        resultados.value = (data?.alertas ?? []) as Alerta[];
        seleccionados.value = [];
        currentPage.value = 1;
    } catch {
        resultados.value = [];
        seleccionados.value = [];
    } finally {
        isLoading.value = false;
    }
};

const descargandoCSV = ref(false);
const showExportModal = ref(false);
const reportando = ref(false);

function abrirModalExportar() {
    showExportModal.value = true;
}

const descargarCSV = async (conHeaders = false) => {
    if (descargandoCSV.value) return;
    showExportModal.value = false;
    descargandoCSV.value = true;

    const payload: any = {
        ids:          seleccionados.value,
        tipo_reporte: tipoReporteFiltro.value || '',
        estatus:      estatusFiltro.value     || '',
        fecha_ini:    fechaInicialStr.value   || '',
        fecha_fin:    fechaFinalStr.value     || '',
        con_headers:  conHeaders,
    };

    try {
        const res = await axios.post('/reporte-operaciones/exportar', payload, {
            responseType: 'blob',
        });

        const blob = res.data as Blob;
        const url  = window.URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url;
        const disposition = (res.headers?.['content-disposition'] ?? '') as string;
        const nombreArchivo = /filename="?([^"]+)"?/.exec(disposition)?.[1]
            ?? `reporte_operaciones_${new Date().toISOString().slice(0, 10)}.csv`;
        a.download = nombreArchivo;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);

        // Ya no se refresca ni cambia estatus: solo descarga
    } catch (error: any) {
        let mensaje = 'No hay datos para exportar.';
        try {
            const data = error?.response?.data;
            if (data instanceof Blob) {
                mensaje = JSON.parse(await data.text())?.message ?? mensaje;
            } else if (data?.message) {
                mensaje = data.message;
            }
        } catch { /* sin cuerpo */ }
        window.alert(mensaje);
    } finally {
        descargandoCSV.value = false;
    }
};

const reportar = async () => {
    if (reportando.value) return;
    if (seleccionados.value.length === 0) {
        window.alert('Seleccione al menos un registro con estatus "Por reportar" para reportar.');
        return;
    }
    reportando.value = true;
    try {
        const payload: any = {
            ids:          seleccionados.value,
            tipo_reporte: tipoReporteFiltro.value || '',
            estatus:      estatusFiltro.value     || '',
            fecha_ini:    fechaInicialStr.value   || '',
            fecha_fin:    fechaFinalStr.value     || '',
        };
        const res = await axios.post('/reporte-operaciones/reportar', payload);
        const mensaje = (res.data as any)?.message ?? `Se reportaron ${seleccionados.value.length} registro(s).`;
        window.alert(mensaje);
        await buscar();
    } catch (error: any) {
        let mensaje = 'No se pudo reportar. Verifique los registros seleccionados.';
        try {
            const data = error?.response?.data;
            if (data instanceof Blob) {
                mensaje = JSON.parse(await data.text())?.message ?? mensaje;
            } else if (data?.message) {
                mensaje = data.message;
            } else if (data?.errors) {
                const first = Object.values(data.errors as Record<string,string[]>).flat()[0];
                if (first) mensaje = first as string;
            }
        } catch { /* sin cuerpo */ }
        window.alert(mensaje);
    } finally {
        reportando.value = false;
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
            <div class="relative">
                <div
                    class="mt-6 flex flex-col gap-4 rounded-xl border border-slate-100 bg-gradient-to-r from-white/90 via-slate-50/70 to-white/90 p-4 shadow-sm backdrop-blur-sm transition-colors duration-200 ease-out focus-within:border-blue-400/80 focus-within:shadow-[0_0_0_1px_rgba(59,130,246,0.3)] dark:border-neutral-800/80 dark:bg-gradient-to-r dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90">
                    <form @submit.prevent="buscar" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Tipo de reporte -->
                            <div>
                                <Select id="tipo-reporte" label="Tipo de reporte:" :options="opcionesTipoReporte"
                                    v-model="tipoReporteFiltro" placeholder="Seleccione tipo de reporte" />
                            </div>

                            <!-- Estatus -->
                            <div>
                                <Select id="estatus-operacion" label="Estatus:" :options="opcionesEstatus"
                                    v-model="estatusFiltro" placeholder="Seleccione estatus" />
                            </div>

                            <!-- Fecha inicial -->
                            <div>
                                <label for="fecha-inicial"
                                    class="block mb-2 text-[15px] font-semibold tracking-tight select-none transition-colors duration-200 text-neutral-900 dark:text-neutral-50">
                                    Fecha inicial:
                                </label>
                                <DateInput id="fecha-inicial" v-model="fechaInicialStr" />
                            </div>

                            <!-- Fecha final -->
                            <div>
                                <label for="fecha-final"
                                    class="block mb-2 text-[15px] font-semibold tracking-tight select-none transition-colors duration-200 text-neutral-900 dark:text-neutral-50">
                                    Fecha final:
                                </label>
                                <DateInput id="fecha-final" v-model="fechaFinalStr" />
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="abrirModalExportar" :disabled="descargandoCSV"
                                class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition-all duration-150 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:hover:bg-neutral-800">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                {{ descargandoCSV ? 'Exportando...' : 'Descargar CSV' }}
                            </button>
                            <button type="button" @click="reportar" :disabled="reportando || seleccionados.length === 0"
                                class="inline-flex items-center justify-center rounded-md border border-transparent bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ reportando ? 'Reportando...' : 'Reportar' }}
                            </button>
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                Buscar
                            </button>
                        </div>
                    </form>
                </div>

                <div
                    class="mt-6 flex flex-col gap-4 rounded-xl border border-slate-100 bg-gradient-to-r from-white/90 via-slate-50/70 to-white/90 p-4 shadow-sm backdrop-blur-sm transition-colors duration-200 ease-out dark:border-neutral-800/80 dark:bg-gradient-to-r dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div class="flex flex-col gap-1 w-48">
                            <label class="text-xs text-slate-600 dark:text-neutral-300">Número de elementos</label>
                            <select v-model.number="perPage" :disabled="isLoading"
                                class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-xs text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900">
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                                <option :value="100">100</option>
                                <option :value="-1">Todos</option>
                            </select>
                        </div>
                    </div>

                    <div
                        class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)]">
                        <div class="p-4 flex items-center justify-between">
                            <div v-if="showingMessage" class="text-xs text-slate-500 dark:text-neutral-400">{{
                                showingMessage }}</div>
                        </div>
                        <div class="max-h-[28rem] overflow-y-auto overflow-x-auto">
                            <table
                                class="min-w-full border-collapse text-sm text-slate-900 dark:text-white whitespace-nowrap">
                                <thead>
                                    <tr
                                        class="sticky top-0 z-10 bg-gradient-to-r from-slate-50 via-slate-50/95 to-blue-50/60 text-xs font-semibold uppercase tracking-wide text-slate-700 backdrop-blur-sm dark:bg-gradient-to-r dark:from-neutral-900/95 dark:via-neutral-900/95 dark:to-slate-900/95 dark:text-neutral-200">
                                        <th class="sticky left-0 z-20 border-b border-r border-slate-200 bg-gradient-to-r from-slate-50 via-slate-50/95 to-blue-50/60 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800 dark:from-neutral-900/95 dark:via-neutral-900/95 dark:to-slate-900/95">
                                            <input type="checkbox" v-model="todosSeleccionados"
                                                :indeterminate.prop="seleccionParcial"
                                                :disabled="seleccionables.length === 0"
                                                title="Seleccionar todos los filtrados"
                                                class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-40" />
                                        </th>
                                        <th class="sticky left-[46px] z-20 border-b border-r border-slate-200 bg-gradient-to-r from-slate-50 via-slate-50/95 to-blue-50/60 px-3 py-2 text-left align-middle text-[11px] font-bold tracking-wider dark:border-neutral-800 dark:from-neutral-900/95 dark:via-neutral-900/95 dark:to-slate-900/95">Estatus</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Folio</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Tipo de reporte</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Cliente</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Póliza</th>
                                        <!-- Operación base -->
                                        <th class="border-b border-slate-200 bg-amber-50/70 px-3 py-2 text-left align-middle text-[11px] font-semibold text-amber-900 dark:border-neutral-800 dark:bg-amber-900/20 dark:text-amber-100" title="Folio del endoso (vacío = emisión)">Folio endoso</th>
                                        <th class="border-b border-slate-200 bg-amber-50/70 px-3 py-2 text-left align-middle text-[11px] font-semibold text-amber-900 dark:border-neutral-800 dark:bg-amber-900/20 dark:text-amber-100">Prima total</th>
                                        <th class="border-b border-slate-200 bg-amber-50/70 px-3 py-2 text-left align-middle text-[11px] font-semibold text-amber-900 dark:border-neutral-800 dark:bg-amber-900/20 dark:text-amber-100">Moneda</th>
                                        <th class="border-b border-slate-200 bg-amber-50/70 px-3 py-2 text-left align-middle text-[11px] font-semibold text-amber-900 dark:border-neutral-800 dark:bg-amber-900/20 dark:text-amber-100">F. emisión</th>
                                        <th class="border-b border-slate-200 bg-amber-50/70 px-3 py-2 text-left align-middle text-[11px] font-semibold text-amber-900 dark:border-neutral-800 dark:bg-amber-900/20 dark:text-amber-100">Vigencia</th>
                                        <th class="border-b border-slate-200 bg-amber-50/70 px-3 py-2 text-left align-middle text-[11px] font-semibold text-amber-900 dark:border-neutral-800 dark:bg-amber-900/20 dark:text-amber-100">Forma pago</th>
                                        <th class="border-b border-slate-200 bg-amber-50/70 px-3 py-2 text-left align-middle text-[11px] font-semibold text-amber-900 dark:border-neutral-800 dark:bg-amber-900/20 dark:text-amber-100">Agente (op.)</th>
                                        <th class="border-b border-slate-200 bg-amber-50/70 px-3 py-2 text-left align-middle text-[11px] font-semibold text-amber-900 dark:border-neutral-800 dark:bg-amber-900/20 dark:text-amber-100">Flags</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Fecha detección</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Monto alerta</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Instrumento monetario</th>
                                        <th class="sticky right-0 z-20 border-b border-l border-slate-200 bg-slate-50 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800 dark:bg-neutral-900">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody v-if="paginatedResultados.length">
                                    <tr v-for="item in paginatedResultados" :key="item.IDRegistroAlerta"
                                        :class="item.Estatus === 'Por reportar' ? 'border-l-amber-400 dark:border-l-amber-500' : item.Estatus === 'Enviado' ? 'border-l-emerald-400 dark:border-l-emerald-500' : 'border-l-transparent'"
                                        class="group cursor-pointer border-b border-l-2 bg-white transition-all duration-200 ease-out hover:-translate-y-[1px] hover:bg-gradient-to-r hover:from-white hover:via-slate-50/80 hover:to-blue-50/40 hover:shadow-[0_10px_30px_rgba(15,23,42,0.08)] dark:bg-neutral-950/40 dark:hover:bg-gradient-to-r dark:hover:from-neutral-950/90 dark:hover:via-neutral-900/90 dark:hover:to-slate-800/90 dark:hover:shadow-[0_18px_40px_rgba(0,0,0,0.75)] border-slate-100 dark:border-neutral-800/60">
                                        <td class="sticky left-0 z-10 border-r border-slate-100 bg-white px-3 py-2 align-middle group-hover:bg-blue-50 dark:border-neutral-800/60 dark:bg-neutral-950 dark:group-hover:bg-neutral-900">
                                            <input v-if="item.Estatus === 'Por reportar'" type="checkbox"
                                                :checked="seleccionados.includes(item.IDRegistroAlerta)"
                                                @change="toggleSeleccion(item.IDRegistroAlerta)"
                                                class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                        </td>
                                        <td class="sticky left-[46px] z-10 border-r border-slate-100 bg-white px-2 py-2 align-middle group-hover:bg-blue-50 dark:border-neutral-800/60 dark:bg-neutral-950 dark:group-hover:bg-neutral-900">
                                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold leading-none shadow-sm"
                                                :class="item.Estatus === 'Por reportar' ? 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-900/40 dark:text-amber-200 dark:border-amber-700' : item.Estatus === 'Enviado' ? 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-700' : 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-neutral-800 dark:text-neutral-200 dark:border-neutral-700'">
                                                <span class="h-1.5 w-1.5 rounded-full"
                                                    :class="item.Estatus === 'Por reportar' ? 'bg-amber-500' : item.Estatus === 'Enviado' ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                                {{ item.Estatus ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 align-middle">{{ item.Folio ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 align-middle">{{ item.Patron ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 align-middle max-w-[14rem] truncate" :title="item.Cliente ?? ''">{{ item.Cliente ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 align-middle font-mono text-xs">{{ item.Poliza ?? 'N/A' }}</td>
                                        <!-- Operación base -->
                                        <td class="px-3 py-2 align-middle">
                                            <span v-if="item.OperacionFolioEndoso && String(item.OperacionFolioEndoso).trim() !== ''" class="font-mono text-xs">{{ item.OperacionFolioEndoso }}</span>
                                            <span v-else class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-neutral-800 dark:text-neutral-400">Emisión</span>
                                        </td>
                                        <td class="px-3 py-2 align-middle text-right font-mono text-xs">
                                            <span :class="(Number(item.OperacionPrimaTotal)||0) < 0 ? 'text-red-600 dark:text-red-300 font-semibold' : ''">{{ item.OperacionPrimaTotal != null ? formatMonto(item.OperacionPrimaTotal) : '—' }}</span>
                                        </td>
                                        <td class="px-3 py-2 align-middle text-center">
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">{{ item.OperacionIDMoneda || item.IDMoneda || '—' }}</span>
                                        </td>
                                        <td class="px-3 py-2 align-middle whitespace-nowrap text-xs">{{ formatFechaCorta(item.OperacionFechaEmision) }}</td>
                                        <td class="px-3 py-2 align-middle whitespace-nowrap text-xs">
                                            <span v-if="item.OperacionFechaInicioVigencia || item.OperacionFechaFinVigencia" :title="`${formatFechaCorta(item.OperacionFechaInicioVigencia)} → ${formatFechaCorta(item.OperacionFechaFinVigencia)}`">{{ formatFechaCorta(item.OperacionFechaInicioVigencia) }} <span class="text-slate-400">→</span> {{ formatFechaCorta(item.OperacionFechaFinVigencia) }}</span>
                                            <span v-else class="text-slate-400">—</span>
                                        </td>
                                        <td class="px-3 py-2 align-middle text-xs">
                                            <span :title="item.OperacionEsquemaDePago ? `Esquema: ${item.OperacionEsquemaDePago}` : ''">{{ formaPagoLabel(item.OperacionIDFormaPago) }}</span>
                                            <span v-if="item.OperacionEsquemaDePago" class="ml-1 text-[10px] text-slate-400">({{ item.OperacionEsquemaDePago }})</span>
                                        </td>
                                        <td class="px-3 py-2 align-middle max-w-[12rem] truncate text-xs" :title="agenteOperacionNombre(item)">{{ agenteOperacionNombre(item) }}</td>
                                        <td class="px-3 py-2 align-middle">
                                            <div class="flex flex-wrap gap-1">
                                                <span v-if="esCancelada(item)" class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-900/30 dark:text-red-200">Cancelada</span>
                                                <span v-if="item.OperacionPagaTercero == 1 || (item.OperacionPagaTercero as any) === true" class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold text-orange-700 dark:bg-orange-900/30 dark:text-orange-200">Paga 3º</span>
                                                <span v-if="!esCancelada(item) && !(item.OperacionPagaTercero == 1 || (item.OperacionPagaTercero as any) === true)" class="text-slate-300 text-[10px]">—</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 align-middle whitespace-nowrap text-xs">{{ item.FechaDeteccion ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 align-middle text-right font-mono text-xs">{{ item.MontoOperacion != null ? formatMonto(item.MontoOperacion) : 'N/A' }}</td>
                                        <td class="px-3 py-2 align-middle">{{ item.InstrumentoMonetario ?? 'N/A' }}</td>
                                        <td
                                            class="sticky right-0 z-10 border-l border-slate-100 bg-white px-3 py-2 align-middle group-hover:bg-blue-50 dark:border-neutral-800/60 dark:bg-neutral-950 dark:group-hover:bg-neutral-900">
                                            <button type="button"
                                                class="inline-flex items-center justify-center rounded-md border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 transition-all duration-150 hover:bg-blue-100 dark:border-blue-600 dark:bg-blue-900/30 dark:text-blue-200 dark:hover:bg-blue-900/50"
                                                @click="verDetalleAlerta(item)">
                                                Ver detalles
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody v-else>
                                    <tr>
                                        <td colspan="18"
                                            class="px-3 py-4 text-center text-sm text-slate-500 dark:text-neutral-400">
                                            <span v-if="isLoading">Cargando...</span>
                                            <span v-else-if="!hasBuscado">Seleccione filtros y presione <strong>Buscar</strong> para mostrar registros.</span>
                                            <span v-else>Sin resultados para los filtros seleccionados.</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-100 bg-gradient-to-r from-white via-slate-50/70 to-white p-3 text-slate-900 shadow-sm backdrop-blur-sm sm:flex-row sm:items-center sm:gap-4 dark:border-neutral-800 dark:bg-gradient-to-r dark:from-neutral-950/95 dark:via-neutral-900/90 dark:to-neutral-950/95 dark:text-white">
                        <p class="text-xs text-slate-500 dark:text-neutral-400">{{ showingMessage }}</p>
                        <div class="flex items-center space-x-2">
                            <button @click="prevPage" :disabled="currentPage === 1"
                                class="rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-slate-700 shadow-sm transition-all duration-150 ease-out hover:-translate-y-[1px] hover:bg-slate-50 hover:shadow-md disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:bg-neutral-800/90">
                                Anterior
                            </button>
                            <span class="text-xs text-slate-600 dark:text-neutral-300">Página</span>
                            <input type="number" v-model.number="currentPage" min="1" :max="totalPages"
                                class="w-16 rounded-lg border border-slate-300 bg-white px-3 py-2 text-center text-xs text-slate-900 outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900" />
                            <span class="text-xs text-slate-600 dark:text-neutral-300">de {{ totalPages }}</span>
                            <button @click="nextPage" :disabled="currentPage === totalPages"
                                class="rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-slate-700 shadow-sm transition-all duration-150 ease-out hover:-translate-y-[1px] hover:bg-slate-50 hover:shadow-md disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:bg-neutral-800/90">
                                Siguiente
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </FadeIn>

        <!-- Modal exportar CSV: con/sin headers -->
        <Dialog :open="showExportModal" @update:open="(v: boolean) => showExportModal = v">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Descargar CSV</DialogTitle>
                    <DialogDescription>
                        Elige el formato de descarga. Por defecto el archivo regulatorio se envía sin títulos (solo data).
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-3 py-2">
                    <button type="button" @click="descargarCSV(false)" :disabled="descargandoCSV"
                        class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-60">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Sin headers (solo data) — Recomendado
                    </button>
                    <button type="button" @click="descargarCSV(true)" :disabled="descargandoCSV"
                        class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:hover:bg-neutral-800 disabled:opacity-60">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4 4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Con headers (con títulos)
                    </button>
                    <button type="button" @click="showExportModal = false"
                        class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300">
                        Cancelar
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
