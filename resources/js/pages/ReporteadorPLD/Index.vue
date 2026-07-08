<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import DateInput from '@/components/forms/DateInput.vue';
import Select from '@/components/forms/Select.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';

interface Props {
    montoAcumulado: number;
}
const props = defineProps<Props>();

// ── Filtros ──────────────────────────────────────────────────────────────────
const fechaInicio   = ref('');
const fechaFin      = ref('');
const tipoPersona   = ref('');       // '' | '1' | '2'
const monedaMXN     = ref(false);
const monedaUSD     = ref(false);
const modoAgrupado  = ref(false);

const filtroUSD500  = ref(false);
const filtroUSD10K  = ref(false);
const filtroUSD50K  = ref(false);
const filtroUSDacum = ref(false);
const filtroMXN300K = ref(false);
const filtroMXN500K = ref(false);
const filtroMXNacum = ref(false);

const opcionesTipoPersona = [
    { value: '',  label: 'Todos' },
    { value: '1', label: 'Persona Física' },
    { value: '2', label: 'Persona Moral' },
];

// Habilitar umbrales según moneda seleccionada
const usdThresholdsEnabled = computed(() => monedaUSD.value);
const mxnThresholdsEnabled = computed(() => monedaMXN.value);

// Limpiar umbrales deshabilitados al cambiar moneda
watch(monedaUSD, (val) => {
    if (!val) {
        filtroUSD500.value  = false;
        filtroUSD10K.value  = false;
        filtroUSD50K.value  = false;
        filtroUSDacum.value = false;
    }
});
watch(monedaMXN, (val) => {
    if (!val) {
        filtroMXN300K.value = false;
        filtroMXN500K.value = false;
        filtroMXNacum.value = false;
    }
});

// Etiqueta de moneda para encabezados (USD solo cuando únicamente USD está marcado)
const monedaLabel = computed(() => (monedaUSD.value && !monedaMXN.value) ? 'USD' : 'MXD');

// ── Estado de resultados ─────────────────────────────────────────────────────
interface Resultado {
    Ncliente:            number;
    Nombre:              string;
    NoPoliza?:           string | null;
    TipoPago?:           string | null;
    FormaPago?:          string | null;
    FechaPago?:          string | null;
    MontoPagadoMXD:      number | null;
    EquivalentePagadoUSD: number | null;
}

const resultados    = ref<Resultado[]>([]);
const isLoading     = ref(false);
const hasBuscado    = ref(false);
const search        = ref('');
const searchInput   = ref('');
const perPage       = ref(10);
const currentPage   = ref(1);
let searchTimer: number | null = null;

// ── Estado de detalle ────────────────────────────────────────────────────────
const detalleCliente    = ref<{ id: number; nombre: string } | null>(null);
const detalleResultados = ref<Resultado[]>([]);
const isLoadingDetalle  = ref(false);
const detalleSearch     = ref('');
const detalleSearchInput = ref('');
const detallePerPage    = ref(10);
const detalleCurrentPage = ref(1);
let detalleSearchTimer: number | null = null;

// ── Búsqueda y paginación ────────────────────────────────────────────────────
function normalize(text: string): string {
    return (text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

function matchesSearch(item: Resultado, q: string): boolean {
    if (!q.trim()) return true;
    const haystack = Object.values(item ?? {}).map((v) => normalize(String(v ?? ''))).join(' ');
    return normalize(q).trim().split(/\s+/).every((t) => haystack.includes(t));
}

const filteredResultados = computed(() => resultados.value.filter((r) => matchesSearch(r, search.value)));
const totalPages = computed(() => perPage.value === -1 ? 1 : Math.ceil(filteredResultados.value.length / perPage.value || 1));
const paginatedResultados = computed(() => {
    if (perPage.value === -1) return filteredResultados.value;
    const start = (currentPage.value - 1) * perPage.value;
    return filteredResultados.value.slice(start, start + perPage.value);
});

const filteredDetalle = computed(() => detalleResultados.value.filter((r) => matchesSearch(r, detalleSearch.value)));
const detalleTotalPages = computed(() => detallePerPage.value === -1 ? 1 : Math.ceil(filteredDetalle.value.length / detallePerPage.value || 1));
const paginatedDetalle = computed(() => {
    if (detallePerPage.value === -1) return filteredDetalle.value;
    const start = (detalleCurrentPage.value - 1) * detallePerPage.value;
    return filteredDetalle.value.slice(start, start + detallePerPage.value);
});

watch(searchInput, (v) => {
    if (searchTimer) window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => { search.value = v; }, 250);
});
watch(detalleSearchInput, (v) => {
    if (detalleSearchTimer) window.clearTimeout(detalleSearchTimer);
    detalleSearchTimer = window.setTimeout(() => { detalleSearch.value = v; }, 250);
});
watch([search, perPage], () => (currentPage.value = 1));
watch([detalleSearch, detallePerPage], () => (detalleCurrentPage.value = 1));

const showingMessage = computed(() => {
    if (isLoading.value || !filteredResultados.value.length) return '';
    const total = filteredResultados.value.length;
    if (perPage.value === -1) return `Mostrando todos los ${total.toLocaleString()} registros.`;
    const start = (currentPage.value - 1) * perPage.value + 1;
    const end   = Math.min(start + perPage.value - 1, total);
    return `Mostrando ${start.toLocaleString()} a ${end.toLocaleString()} de ${total.toLocaleString()} registros.`;
});

const detalleShowingMessage = computed(() => {
    if (isLoadingDetalle.value || !filteredDetalle.value.length) return '';
    const total = filteredDetalle.value.length;
    if (detallePerPage.value === -1) return `Mostrando todos los ${total.toLocaleString()} registros.`;
    const start = (detalleCurrentPage.value - 1) * detallePerPage.value + 1;
    const end   = Math.min(start + detallePerPage.value - 1, total);
    return `Mostrando ${start.toLocaleString()} a ${end.toLocaleString()} de ${total.toLocaleString()} registros.`;
});

// ── Formato de montos ────────────────────────────────────────────────────────
function formatMonto(value: number | null | undefined, currency: 'MXD' | 'USD'): string {
    if (value == null) return 'N/A';
    return new Intl.NumberFormat('es-MX', {
        style:                 'currency',
        currency:              currency === 'USD' ? 'USD' : 'MXN',
        minimumFractionDigits: 2,
    }).format(value);
}

function montoDelResultado(row: Resultado): number | null {
    return monedaLabel.value === 'USD' ? row.EquivalentePagadoUSD : row.MontoPagadoMXD;
}

// ── Parámetros de consulta ───────────────────────────────────────────────────
function buildParams(agrupado = modoAgrupado.value): URLSearchParams {
    return new URLSearchParams({
        agrupado:    agrupado ? '1' : '0',
        tipoPersona: tipoPersona.value,
        monedaMXN:   monedaMXN.value  ? '1' : '0',
        monedaUSD:   monedaUSD.value  ? '1' : '0',
        fechaInicio: fechaInicio.value,
        fechaFin:    fechaFin.value,
        usd500:      filtroUSD500.value  ? '1' : '0',
        usd10k:      filtroUSD10K.value  ? '1' : '0',
        usd50k:      filtroUSD50K.value  ? '1' : '0',
        usdAcum:     filtroUSDacum.value ? '1' : '0',
        mxn300k:     filtroMXN300K.value ? '1' : '0',
        mxn500k:     filtroMXN500K.value ? '1' : '0',
        mxnAcum:     filtroMXNacum.value ? '1' : '0',
    });
}

// ── Acciones ─────────────────────────────────────────────────────────────────
const buscar = async () => {
    detalleCliente.value = null;
    isLoading.value = true;
    hasBuscado.value = true;
    currentPage.value = 1;
    try {
        const res  = await fetch(`/reporteador-pld/buscar?${buildParams()}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        resultados.value = (data?.resultados ?? []) as Resultado[];
    } catch {
        resultados.value = [];
    } finally {
        isLoading.value = false;
    }
};

const verDetalle = async (row: Resultado) => {
    detalleCliente.value     = { id: row.Ncliente, nombre: row.Nombre };
    detalleResultados.value  = [];
    isLoadingDetalle.value   = true;
    detalleCurrentPage.value = 1;

    const params = new URLSearchParams({
        noCliente: String(row.Ncliente),
        fecha1:    fechaInicio.value,
        fecha2:    fechaFin.value,
        moneda:    monedaMXN.value && !monedaUSD.value ? 'MXN' : monedaUSD.value && !monedaMXN.value ? 'USD' : '',
    });

    try {
        const res  = await fetch(`/reporteador-pld/detalle?${params}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        detalleResultados.value = (data?.resultados ?? []) as Resultado[];
    } catch {
        detalleResultados.value = [];
    } finally {
        isLoadingDetalle.value = false;
    }
};

const cerrarDetalle = () => {
    detalleCliente.value = null;
    detalleResultados.value = [];
};

const descargarCSV = () => {
    window.location.href = `/reporteador-pld/exportar?${buildParams()}`;
};

const breadcrumbs = [{ title: 'Reporteador PLD', href: '' }];

// Etiqueta dinámica del umbral acumulado USD (viene de config)
const labelUSDacum = computed(() => {
    const n = props.montoAcumulado;
    return `≥ ${new Intl.NumberFormat('es-MX').format(n)} USD acumulado`;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
            <div class="relative space-y-6 p-1">

                <!-- ── Filtros ─────────────────────────────────────────────── -->
                <div class="rounded-xl border border-slate-100 bg-gradient-to-r from-white/90 via-slate-50/70 to-white/90 p-5 shadow-sm backdrop-blur-sm dark:border-neutral-800/80 dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-neutral-400">Filtros</h2>

                    <form @submit.prevent="buscar" class="space-y-5">

                        <!-- Fechas + Tipo Solicitante -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label class="mb-2 block text-[15px] font-semibold tracking-tight text-neutral-900 dark:text-neutral-50">Fecha inicio</label>
                                <DateInput id="fecha-inicio" v-model="fechaInicio" />
                            </div>
                            <div>
                                <label class="mb-2 block text-[15px] font-semibold tracking-tight text-neutral-900 dark:text-neutral-50">Fecha fin</label>
                                <DateInput id="fecha-fin" v-model="fechaFin" />
                            </div>
                            <div>
                                <Select id="tipo-persona" label="Tipo solicitante:" :options="opcionesTipoPersona"
                                    v-model="tipoPersona" placeholder="Todos" />
                            </div>
                        </div>

                        <!-- Moneda -->
                        <div>
                            <p class="mb-2 text-[13px] font-semibold uppercase tracking-wide text-slate-500 dark:text-neutral-400">Moneda</p>
                            <div class="flex flex-wrap gap-4">
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-neutral-300">
                                    <input type="checkbox" v-model="monedaMXN" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                    MXN
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-neutral-300">
                                    <input type="checkbox" v-model="monedaUSD" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                    USD
                                </label>
                            </div>
                        </div>

                        <!-- Umbrales de Monto -->
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- USD -->
                            <div>
                                <p class="mb-2 text-[13px] font-semibold uppercase tracking-wide text-slate-500 dark:text-neutral-400">Umbrales USD</p>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-sm"
                                        :class="usdThresholdsEnabled ? 'cursor-pointer text-slate-700 dark:text-neutral-300' : 'cursor-not-allowed opacity-40 text-slate-400'">
                                        <input type="checkbox" v-model="filtroUSD500"  :disabled="!usdThresholdsEnabled" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                        ≥ 500 USD
                                    </label>
                                    <label class="flex items-center gap-2 text-sm"
                                        :class="usdThresholdsEnabled ? 'cursor-pointer text-slate-700 dark:text-neutral-300' : 'cursor-not-allowed opacity-40 text-slate-400'">
                                        <input type="checkbox" v-model="filtroUSD10K"  :disabled="!usdThresholdsEnabled" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                        ≥ 10,000 USD
                                    </label>
                                    <label class="flex items-center gap-2 text-sm"
                                        :class="usdThresholdsEnabled ? 'cursor-pointer text-slate-700 dark:text-neutral-300' : 'cursor-not-allowed opacity-40 text-slate-400'">
                                        <input type="checkbox" v-model="filtroUSD50K"  :disabled="!usdThresholdsEnabled" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                        ≥ 50,000 USD
                                    </label>
                                    <label class="flex items-center gap-2 text-sm"
                                        :class="usdThresholdsEnabled ? 'cursor-pointer text-slate-700 dark:text-neutral-300' : 'cursor-not-allowed opacity-40 text-slate-400'">
                                        <input type="checkbox" v-model="filtroUSDacum" :disabled="!usdThresholdsEnabled" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                        {{ labelUSDacum }}
                                    </label>
                                </div>
                            </div>

                            <!-- MXN -->
                            <div>
                                <p class="mb-2 text-[13px] font-semibold uppercase tracking-wide text-slate-500 dark:text-neutral-400">Umbrales MXN</p>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-sm"
                                        :class="mxnThresholdsEnabled ? 'cursor-pointer text-slate-700 dark:text-neutral-300' : 'cursor-not-allowed opacity-40 text-slate-400'">
                                        <input type="checkbox" v-model="filtroMXN300K" :disabled="!mxnThresholdsEnabled" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                        ≥ 300,000 MXN
                                    </label>
                                    <label class="flex items-center gap-2 text-sm"
                                        :class="mxnThresholdsEnabled ? 'cursor-pointer text-slate-700 dark:text-neutral-300' : 'cursor-not-allowed opacity-40 text-slate-400'">
                                        <input type="checkbox" v-model="filtroMXN500K" :disabled="!mxnThresholdsEnabled" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                        ≥ 500,000 MXN
                                    </label>
                                    <label class="flex items-center gap-2 text-sm"
                                        :class="mxnThresholdsEnabled ? 'cursor-pointer text-slate-700 dark:text-neutral-300' : 'cursor-not-allowed opacity-40 text-slate-400'">
                                        <input type="checkbox" v-model="filtroMXNacum" :disabled="!mxnThresholdsEnabled" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                        ≥ 1,000,000 MXN acumulado
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Modo agrupado -->
                        <div>
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-neutral-300">
                                <input type="checkbox" v-model="modoAgrupado" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                Vista agrupada por cliente
                            </label>
                        </div>

                        <!-- Acciones -->
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="descargarCSV" :disabled="!hasBuscado || isLoading"
                                class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition-all duration-150 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:hover:bg-neutral-800">
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Descargar CSV
                            </button>
                            <button type="submit" :disabled="isLoading"
                                class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-70">
                                <svg v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                                </svg>
                                <svg v-else class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                {{ isLoading ? 'Buscando…' : 'Buscar' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── Detalle del cliente ──────────────────────────────────── -->
                <div v-if="detalleCliente"
                    class="rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50/80 via-white to-blue-50/80 p-5 shadow-sm dark:border-blue-900/60 dark:from-blue-950/30 dark:via-neutral-950/90 dark:to-blue-950/30">

                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">Detalle de movimientos</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-white">
                                Cliente #{{ detalleCliente.id }} — {{ detalleCliente.nombre }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-neutral-400">
                                Montos expresados en {{ monedaLabel }}
                            </p>
                        </div>
                        <button @click="cerrarDetalle"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300">
                            ✕ Cerrar detalle
                        </button>
                    </div>

                    <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div class="flex flex-col gap-1 w-48">
                            <label class="text-xs text-slate-600 dark:text-neutral-300">Registros por página</label>
                            <select v-model.number="detallePerPage"
                                class="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-xs text-slate-900 outline-none dark:border-neutral-700 dark:bg-neutral-900 dark:text-white">
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                                <option :value="-1">Todos</option>
                            </select>
                        </div>
                        <input v-model="detalleSearchInput" type="search" placeholder="Buscar en resultados…"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 placeholder-slate-400 outline-none focus:border-blue-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white md:w-64" />
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 shadow-sm dark:border-neutral-800">
                        <div class="max-h-72 overflow-y-auto overflow-x-auto">
                            <table class="min-w-full border-collapse text-sm whitespace-nowrap text-slate-900 dark:text-white">
                                <thead>
                                    <tr class="sticky top-0 z-10 bg-blue-50 text-xs font-semibold uppercase tracking-wide text-slate-700 dark:bg-blue-950/60 dark:text-neutral-200">
                                        <th class="border-b border-slate-200 px-3 py-2 text-left dark:border-neutral-800">Póliza</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left dark:border-neutral-800">Tipo de Pago</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left dark:border-neutral-800">Forma de Pago</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left dark:border-neutral-800">Fecha Pago</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-right dark:border-neutral-800">Monto {{ monedaLabel }}</th>
                                    </tr>
                                </thead>
                                <tbody v-if="isLoadingDetalle">
                                    <tr>
                                        <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-400 dark:text-neutral-500">Cargando…</td>
                                    </tr>
                                </tbody>
                                <tbody v-else-if="paginatedDetalle.length">
                                    <tr v-for="(row, idx) in paginatedDetalle" :key="idx"
                                        class="border-b border-slate-100 bg-white even:bg-slate-50/50 dark:border-neutral-800/60 dark:bg-neutral-950/40 dark:even:bg-neutral-900/30">
                                        <td class="px-3 py-2">{{ row.NoPoliza ?? 'N/A' }}</td>
                                        <td class="px-3 py-2">{{ row.TipoPago ?? 'N/A' }}</td>
                                        <td class="px-3 py-2">{{ row.FormaPago ?? 'N/A' }}</td>
                                        <td class="px-3 py-2">{{ row.FechaPago ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 text-right font-mono">
                                            {{ formatMonto(montoDelResultado(row), monedaLabel) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody v-else>
                                    <tr>
                                        <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-400 dark:text-neutral-500">Sin movimientos</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Paginación detalle -->
                    <div class="mt-3 flex items-center justify-between">
                        <p class="text-xs text-slate-500 dark:text-neutral-400">{{ detalleShowingMessage }}</p>
                        <div class="flex items-center gap-2">
                            <button @click="detalleCurrentPage--" :disabled="detalleCurrentPage === 1"
                                class="rounded border border-slate-300 bg-white px-3 py-1 text-xs disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white">
                                Anterior
                            </button>
                            <span class="text-xs text-slate-600 dark:text-neutral-300">{{ detalleCurrentPage }} / {{ detalleTotalPages }}</span>
                            <button @click="detalleCurrentPage++" :disabled="detalleCurrentPage === detalleTotalPages"
                                class="rounded border border-slate-300 bg-white px-3 py-1 text-xs disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white">
                                Siguiente
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Tabla de resultados ──────────────────────────────────── -->
                <div v-if="hasBuscado"
                    class="rounded-xl border border-slate-100 bg-gradient-to-r from-white/90 via-slate-50/70 to-white/90 p-4 shadow-sm backdrop-blur-sm dark:border-neutral-800/80 dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90">

                    <!-- Encabezado de moneda -->
                    <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-neutral-400">
                            Montos expresados en <span class="text-blue-600 dark:text-blue-400">{{ monedaLabel }}</span>
                            <span v-if="modoAgrupado" class="ml-2 text-slate-400">(Vista agrupada por cliente)</span>
                        </p>
                        <div class="flex flex-col gap-2 md:flex-row md:items-end">
                            <div class="flex flex-col gap-1 w-40">
                                <label class="text-xs text-slate-600 dark:text-neutral-300">Registros por página</label>
                                <select v-model.number="perPage"
                                    class="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-xs text-slate-900 outline-none dark:border-neutral-700 dark:bg-neutral-900 dark:text-white">
                                    <option :value="10">10</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                    <option :value="100">100</option>
                                    <option :value="-1">Todos</option>
                                </select>
                            </div>
                            <input v-model="searchInput" type="search" placeholder="Buscar en resultados…"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs placeholder-slate-400 outline-none focus:border-blue-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white md:w-64" />
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md dark:border-neutral-800 dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95">
                        <div class="max-h-[28rem] overflow-y-auto overflow-x-auto">
                            <table class="min-w-full border-collapse text-sm whitespace-nowrap text-slate-900 dark:text-white">
                                <thead>
                                    <tr class="sticky top-0 z-10 bg-gradient-to-r from-slate-50 via-slate-50/95 to-blue-50/60 text-xs font-semibold uppercase tracking-wide text-slate-700 dark:from-neutral-900/95 dark:via-neutral-900/95 dark:to-slate-900/95 dark:text-neutral-200">
                                        <th class="border-b border-slate-200 px-3 py-2 text-left dark:border-neutral-800">No. Cliente</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left dark:border-neutral-800">Nombre</th>
                                        <template v-if="!modoAgrupado">
                                            <th class="border-b border-slate-200 px-3 py-2 text-left dark:border-neutral-800">Póliza</th>
                                            <th class="border-b border-slate-200 px-3 py-2 text-left dark:border-neutral-800">Tipo de Pago</th>
                                            <th class="border-b border-slate-200 px-3 py-2 text-left dark:border-neutral-800">Forma de Pago</th>
                                            <th class="border-b border-slate-200 px-3 py-2 text-left dark:border-neutral-800">Fecha Pago</th>
                                        </template>
                                        <th class="border-b border-slate-200 px-3 py-2 text-right dark:border-neutral-800">Monto {{ monedaLabel }}</th>
                                        <th v-if="modoAgrupado" class="sticky right-0 z-20 border-b border-l border-slate-200 bg-slate-50 px-3 py-2 text-left dark:border-neutral-800 dark:bg-neutral-900">Acciones</th>
                                    </tr>
                                </thead>

                                <tbody v-if="isLoading">
                                    <tr>
                                        <td :colspan="modoAgrupado ? 4 : 7" class="px-3 py-8 text-center text-sm text-slate-400 dark:text-neutral-500">Cargando…</td>
                                    </tr>
                                </tbody>

                                <tbody v-else-if="paginatedResultados.length">
                                    <tr v-for="(row, idx) in paginatedResultados" :key="idx"
                                        class="group border-b border-l-2 border-slate-100 border-l-transparent bg-white transition-all duration-200 hover:border-l-blue-400 hover:bg-gradient-to-r hover:from-white hover:via-slate-50/80 hover:to-blue-50/40 hover:shadow-[0_4px_12px_rgba(15,23,42,0.06)] dark:border-neutral-800/60 dark:bg-neutral-950/40 dark:hover:border-l-blue-500 dark:hover:from-neutral-950/90 dark:hover:via-neutral-900/90">
                                        <td class="px-3 py-2 align-middle font-mono text-xs text-slate-600 dark:text-neutral-400">{{ row.Ncliente }}</td>
                                        <td class="px-3 py-2 align-middle max-w-[220px] truncate" :title="row.Nombre">{{ row.Nombre }}</td>
                                        <template v-if="!modoAgrupado">
                                            <td class="px-3 py-2 align-middle">{{ row.NoPoliza ?? 'N/A' }}</td>
                                            <td class="px-3 py-2 align-middle">{{ row.TipoPago ?? 'N/A' }}</td>
                                            <td class="px-3 py-2 align-middle">{{ row.FormaPago ?? 'N/A' }}</td>
                                            <td class="px-3 py-2 align-middle">{{ row.FechaPago ?? 'N/A' }}</td>
                                        </template>
                                        <td class="px-3 py-2 align-middle text-right font-mono font-medium">
                                            {{ formatMonto(montoDelResultado(row), monedaLabel) }}
                                        </td>
                                        <td v-if="modoAgrupado"
                                            class="sticky right-0 z-10 border-l border-slate-100 bg-white px-3 py-2 align-middle group-hover:bg-blue-50 dark:border-neutral-800/60 dark:bg-neutral-950 dark:group-hover:bg-neutral-900">
                                            <button @click="verDetalle(row)"
                                                class="inline-flex items-center justify-center rounded-md border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 transition-all hover:bg-blue-100 dark:border-blue-600 dark:bg-blue-900/30 dark:text-blue-200 dark:hover:bg-blue-900/50">
                                                Ver detalle
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>

                                <tbody v-else>
                                    <tr>
                                        <td :colspan="modoAgrupado ? 4 : 7" class="px-3 py-8 text-center text-sm text-slate-400 dark:text-neutral-500">
                                            No hay información con esos criterios
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Paginación principal -->
                    <div class="mt-4 flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-100 bg-gradient-to-r from-white via-slate-50/70 to-white p-3 text-slate-900 shadow-sm sm:flex-row sm:items-center dark:border-neutral-800 dark:from-neutral-950/95 dark:via-neutral-900/90 dark:to-neutral-950/95 dark:text-white">
                        <p class="text-xs text-slate-500 dark:text-neutral-400">{{ showingMessage }}</p>
                        <div class="flex items-center space-x-2">
                            <button @click="currentPage--" :disabled="currentPage === 1"
                                class="rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-slate-700 shadow-sm transition-all hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white">
                                Anterior
                            </button>
                            <span class="text-xs text-slate-600 dark:text-neutral-300">Página</span>
                            <input type="number" v-model.number="currentPage" min="1" :max="totalPages"
                                class="w-16 rounded-lg border border-slate-300 bg-white px-3 py-2 text-center text-xs text-slate-900 outline-none focus:border-blue-500 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white" />
                            <span class="text-xs text-slate-600 dark:text-neutral-300">de {{ totalPages }}</span>
                            <button @click="currentPage++" :disabled="currentPage === totalPages"
                                class="rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-slate-700 shadow-sm transition-all hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white">
                                Siguiente
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </FadeIn>
    </AppLayout>
</template>
