<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/ui/tables/Datatable.vue';
import Titulo from '@/components/ui/Titulo.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
import ModalForm from '@/components/ui/modals/modalForm.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  Copy,
  Download,
  FileJson,
  RotateCcw,
  ScrollText,
  Search,
  X,
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Logs', href: '' }];

interface Log {
  id: number;
  usuario: string | null;
  metodo: string;
  ruta: string | null;
  ip: string | null;
  estatus: number | null;
  duracion_ms: number | null;
  fecha: string | null;
  tiene_body: boolean;
  tiene_response: boolean;
}

interface PaginatedLogs {
  data: Log[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

interface RutaOpcion {
  patron: string;
  total: number;
}

interface FilterOptions {
  metodos: string[];
  estatuses: number[];
  rutas: RutaOpcion[];
}

interface LogMeta {
  id: number;
  usuario: string | null;
  metodo: string;
  ruta: string | null;
  url: string | null;
  ip: string | null;
  estatus: number | null;
  duracion_ms: number | null;
  fecha: string | null;
  truncado_body: boolean;
  truncado_response: boolean;
}

const page = usePage<{
  logs: PaginatedLogs;
  filters: Record<string, any>;
  filterOptions: FilterOptions;
}>();

const paginated = computed(() => page.props.logs);
const filterOptions = computed<FilterOptions>(() => ({
  metodos: page.props.filterOptions?.metodos ?? [],
  estatuses: page.props.filterOptions?.estatuses ?? [],
  rutas: page.props.filterOptions?.rutas ?? [],
}));

const initial = page.props.filters ?? {};

const filters = reactive({
  search: (initial.search as string) ?? '',
  metodo: (initial.metodo as string) ?? '',
  ruta: (initial.ruta as string) ?? '',
  estatus: initial.estatus !== undefined && initial.estatus !== null && initial.estatus !== '' ? String(initial.estatus) : '',
  fecha_desde: (initial.fecha_desde as string) ?? '',
  fecha_hasta: (initial.fecha_hasta as string) ?? '',
  q_payload: (initial.q_payload as string) ?? '',
  q_response: (initial.q_response as string) ?? '',
  per_page: Number(initial.per_page ?? paginated.value?.per_page ?? 10),
  sort: (initial.sort as string) ?? 'id',
  direction: (initial.direction as string) ?? 'desc',
});

const navegando = ref(false);
let debounceTimer: number | null = null;

function paramsLimpios() {
  const out: Record<string, any> = {};
  if (filters.search.trim() !== '') out.search = filters.search.trim();
  if (filters.metodo !== '') out.metodo = filters.metodo;
  if (filters.ruta !== '') out.ruta = filters.ruta;
  if (filters.estatus !== '') out.estatus = filters.estatus;
  if (filters.fecha_desde !== '') out.fecha_desde = filters.fecha_desde;
  if (filters.fecha_hasta !== '') out.fecha_hasta = filters.fecha_hasta;
  if (filters.q_payload.trim() !== '') out.q_payload = filters.q_payload.trim();
  if (filters.q_response.trim() !== '') out.q_response = filters.q_response.trim();
  out.sort = filters.sort;
  out.direction = filters.direction;
  out.per_page = filters.per_page;
  return out;
}

function aplicar(debounced = false, resetPage = true) {
  const lanzar = () => {
    navegando.value = true;
    const params = paramsLimpios();
    if (resetPage) params.page = 1;
    router.get('/logs', params, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onFinish: () => {
        navegando.value = false;
      },
    });
  };
  if (!debounced) {
    if (debounceTimer) window.clearTimeout(debounceTimer);
    lanzar();
    return;
  }
  if (debounceTimer) window.clearTimeout(debounceTimer);
  debounceTimer = window.setTimeout(lanzar, 450);
}

watch(
  () => [filters.search, filters.q_payload, filters.q_response],
  () => aplicar(true),
);

function onSelectChange() {
  aplicar(false);
}

function onSort(key: string, direction: 'asc' | 'desc') {
  filters.sort = key;
  filters.direction = direction;
  aplicar(false, false);
}

function irAPagina(p: number) {
  if (p < 1 || p > (paginated.value?.last_page ?? 1) || navegando.value) return;
  navegando.value = true;
  router.get('/logs', { ...paramsLimpios(), page: p }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onFinish: () => {
      navegando.value = false;
    },
  });
}

function limpiarFiltros() {
  filters.search = '';
  filters.metodo = '';
  filters.ruta = '';
  filters.estatus = '';
  filters.fecha_desde = '';
  filters.fecha_hasta = '';
  filters.q_payload = '';
  filters.q_response = '';
  filters.sort = 'id';
  filters.direction = 'desc';
  aplicar(false);
}

function presetFechas(dias: number) {
  const hoy = new Date();
  const desde = new Date(hoy);
  desde.setDate(hoy.getDate() - (dias - 1));
  const fmt = (d: Date) => d.toISOString().slice(0, 10);
  filters.fecha_desde = fmt(desde);
  filters.fecha_hasta = fmt(hoy);
  aplicar(false);
}

function quitarFiltro(key: 'search' | 'metodo' | 'ruta' | 'estatus' | 'fecha_desde' | 'fecha_hasta' | 'q_payload' | 'q_response') {
  filters[key] = '';
  aplicar(false);
}

const hayFiltros = computed(
  () =>
    filters.search !== '' ||
    filters.metodo !== '' ||
    filters.ruta !== '' ||
    filters.estatus !== '' ||
    filters.fecha_desde !== '' ||
    filters.fecha_hasta !== '' ||
    filters.q_payload !== '' ||
    filters.q_response !== '',
);

const chips = computed(() => {
  const list: { key: 'search' | 'metodo' | 'ruta' | 'estatus' | 'fecha_desde' | 'fecha_hasta' | 'q_payload' | 'q_response'; label: string }[] = [];
  if (filters.search) list.push({ key: 'search', label: `Texto: ${filters.search}` });
  if (filters.metodo) list.push({ key: 'metodo', label: `Método: ${filters.metodo}` });
  if (filters.ruta) list.push({ key: 'ruta', label: `Ruta: ${filters.ruta}` });
  if (filters.estatus) list.push({ key: 'estatus', label: `Estatus: ${filters.estatus} ${estatusTexto(Number(filters.estatus))}` });
  if (filters.fecha_desde) list.push({ key: 'fecha_desde', label: `Desde: ${filters.fecha_desde}` });
  if (filters.fecha_hasta) list.push({ key: 'fecha_hasta', label: `Hasta: ${filters.fecha_hasta}` });
  if (filters.q_payload) list.push({ key: 'q_payload', label: `En Body: ${filters.q_payload}` });
  if (filters.q_response) list.push({ key: 'q_response', label: `En Response: ${filters.q_response}` });
  return list;
});

const paginasVisibles = computed(() => {
  const total = paginated.value?.last_page ?? 1;
  const actual = paginated.value?.current_page ?? 1;
  const inicio = Math.max(1, actual - 2);
  const fin = Math.min(total, inicio + 4);
  const real = Math.max(1, fin - 4);
  const arr: number[] = [];
  for (let i = real; i <= fin; i++) arr.push(i);
  return arr;
});

// ---- Tabla (modo serverSide: sin filtros internos) ----
const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'fecha', label: 'Fecha', sortable: true },
  { key: 'usuario', label: 'Usuario', sortable: true },
  { key: 'metodo', label: 'Método', sortable: true },
  { key: 'ruta', label: 'Ruta', sortable: true },
  { key: 'ip', label: 'IP', sortable: true },
  { key: 'estatus', label: 'Estatus', sortable: true },
  { key: 'duracion_ms', label: 'Duración (ms)', sortable: true },
];

const rowActions = [
  { id: 'body', label: 'Body', icon: 'view', variant: 'secondary' as const, disabled: (row: Record<string, any>) => !row.tiene_body },
  { id: 'response', label: 'Response', icon: 'view', variant: 'secondary' as const, disabled: (row: Record<string, any>) => !row.tiene_response },
];

const rows = computed(() =>
  (paginated.value?.data ?? []).map((log) => ({
    id: log.id,
    fecha: log.fecha,
    usuario: log.usuario || 'Anónimo',
    metodo: log.metodo,
    ruta: log.ruta,
    ip: log.ip,
    estatus: log.estatus,
    duracion_ms: log.duracion_ms,
    tiene_body: log.tiene_body,
    tiene_response: log.tiene_response,
  })),
);

function metodoBadge(metodo: string): string {
  const base = 'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold tracking-tight';
  switch ((metodo || '').toUpperCase()) {
    case 'GET':
      return `${base} bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300`;
    case 'POST':
      return `${base} bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300`;
    case 'PUT':
    case 'PATCH':
      return `${base} bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300`;
    case 'DELETE':
      return `${base} bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300`;
    default:
      return `${base} bg-gray-100 text-gray-700 dark:bg-gray-700/50 dark:text-gray-300`;
  }
}

function estatusBadge(estatus: number | null): string {
  const base = 'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold tracking-tight tabular-nums';
  if (estatus === null || estatus === undefined)
    return `${base} bg-gray-100 text-gray-500 dark:bg-gray-700/50 dark:text-gray-400`;
  if (estatus >= 200 && estatus < 300) return `${base} bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300`;
  if (estatus >= 300 && estatus < 400) return `${base} bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300`;
  if (estatus >= 400 && estatus < 500) return `${base} bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300`;
  return `${base} bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300`;
}

function estatusTexto(code: number): string {
  const mapa: Record<number, string> = {
    200: 'OK', 201: 'Created', 204: 'No Content',
    301: 'Moved', 302: 'Found', 304: 'Not Modified',
    400: 'Bad Request', 401: 'Unauthorized', 403: 'Forbidden',
    404: 'Not Found', 419: 'Expired', 422: 'Unprocessable',
    429: 'Too Many Requests', 500: 'Server Error', 502: 'Bad Gateway', 503: 'Unavailable',
  };
  return mapa[code] ?? '';
}

function duracionClase(ms: number | null): string {
  if (ms === null || ms === undefined) return 'text-gray-400';
  if (ms >= 2000) return 'text-red-600 dark:text-red-400 font-bold';
  if (ms >= 1000) return 'text-amber-600 dark:text-amber-400 font-semibold';
  return 'text-gray-700 dark:text-gray-200 tabular-nums';
}

// ---- Modal detalle ----
const showModal = ref(false);
const modalTab = ref<'body' | 'response'>('body');
const modalTitle = ref('');
const modalContent = ref('');
const modalLoading = ref(false);
const modalMeta = ref<LogMeta | null>(null);
const copiado = ref(false);
let abortador: AbortController | null = null;

function formatearJson(content: string | null): string {
  if (!content) return '';
  try {
    return JSON.stringify(JSON.parse(content), null, 2);
  } catch {
    return content;
  }
}

async function verDetalle(actionId: string, rowData: Record<string, any>) {
  if (abortador) abortador.abort();
  abortador = new AbortController();
  const id = rowData.id;
  modalTab.value = actionId === 'response' ? 'response' : 'body';
  modalTitle.value = modalTab.value === 'body' ? 'Body (solicitud)' : 'Response (respuesta)';
  modalLoading.value = true;
  modalContent.value = '';
  modalMeta.value = null;
  copiado.value = false;
  showModal.value = true;
  try {
    const res = await fetch(`/logs/${id}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal: abortador.signal,
    });
    const data = await res.json();
    if (!res.ok) {
      modalContent.value = data.message || 'No se pudo cargar el log.';
    } else {
      modalMeta.value = data.meta ?? null;
      const crudo = modalTab.value === 'body' ? data.body : data.response;
      modalContent.value = crudo ? formatearJson(crudo) : modalTab.value === 'body' ? 'Sin body registrado.' : 'Sin response registrado.';
    }
  } catch (e: any) {
    if (e?.name !== 'AbortError') modalContent.value = 'Error al obtener el detalle del log.';
  } finally {
    modalLoading.value = false;
  }
}

function handleRowAction(actionId: string, rowData: Record<string, any>) {
  verDetalle(actionId, rowData);
}

async function cambiarTab(tab: 'body' | 'response') {
  if (!modalMeta.value || modalLoading.value || modalTab.value === tab) return;
  modalTab.value = tab;
  modalTitle.value = tab === 'body' ? 'Body (solicitud)' : 'Response (respuesta)';
  await verDetalle(tab, { id: modalMeta.value.id });
}

async function copiarContenido() {
  try {
    await navigator.clipboard.writeText(modalContent.value);
    copiado.value = true;
    window.setTimeout(() => (copiado.value = false), 1600);
  } catch {
    const ta = document.createElement('textarea');
    ta.value = modalContent.value;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    copiado.value = true;
    window.setTimeout(() => (copiado.value = false), 1600);
  }
}

function descargarJson() {
  const blob = new Blob([modalContent.value], { type: 'application/json;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `log-${modalMeta.value?.id ?? 'detalle'}-${modalTab.value}.json`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
</script>

<template>
  <Head title="Logs" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <FadeIn>
      <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <Titulo :icon="ScrollText" title="Registro de Logs de Endpoints" size="md" weight="bold" class="mb-2" />
          <div v-if="navegando" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></div>
            Actualizando…
          </div>
        </div>

        <!-- Barra de filtros server-side -->
        <div class="rounded-2xl border border-gray-200/80 bg-white px-5 py-4 shadow-sm dark:border-sidebar-border/50 dark:bg-sidebar">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Método
              <select
                v-model="filters.metodo"
                class="rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 text-sm font-medium text-gray-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white"
                @change="onSelectChange"
              >
                <option value="">Todos</option>
                <option v-for="m in filterOptions.metodos" :key="m" :value="m">{{ m }}</option>
              </select>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Ruta <span class="font-normal text-gray-400">(agrupada por patrón)</span>
              <select
                v-model="filters.ruta"
                class="rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 text-sm font-medium text-gray-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white"
                @change="onSelectChange"
              >
                <option value="">Todas</option>
                <option v-for="r in filterOptions.rutas" :key="r.patron" :value="r.patron">
                  {{ r.patron }} ({{ r.total }})
                </option>
              </select>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Estatus
              <select
                v-model="filters.estatus"
                class="rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 text-sm font-medium text-gray-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white"
                @change="onSelectChange"
              >
                <option value="">Todos</option>
                <option v-for="e in filterOptions.estatuses" :key="e" :value="String(e)">
                  {{ e }}{{ estatusTexto(e) ? ` · ${estatusTexto(e)}` : '' }}
                </option>
              </select>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Buscar <span class="font-normal text-gray-400">(usuario, ruta, IP, URL)</span>
              <span class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  v-model="filters.search"
                  type="text"
                  placeholder="Buscar logs…"
                  autocomplete="off"
                  spellcheck="false"
                  class="w-full rounded-xl border border-gray-200/80 bg-white py-2.5 pl-9 pr-8 text-sm font-medium text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white"
                />
                <button
                  v-if="filters.search"
                  type="button"
                  aria-label="Limpiar búsqueda"
                  class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10"
                  @click="filters.search = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </span>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Contiene en Body <span class="font-normal text-gray-400">(payload)</span>
              <span class="relative">
                <FileJson class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  v-model="filters.q_payload"
                  type="text"
                  placeholder='Ej. "folio", RFC, email…'
                  autocomplete="off"
                  spellcheck="false"
                  class="w-full rounded-xl border border-gray-200/80 bg-white py-2.5 pl-9 pr-8 text-sm font-medium text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white"
                />
                <button
                  v-if="filters.q_payload"
                  type="button"
                  aria-label="Limpiar búsqueda en body"
                  class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10"
                  @click="filters.q_payload = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </span>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Contiene en Response
              <span class="relative">
                <FileJson class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  v-model="filters.q_response"
                  type="text"
                  placeholder='Ej. "success", "error", folio…'
                  autocomplete="off"
                  spellcheck="false"
                  class="w-full rounded-xl border border-gray-200/80 bg-white py-2.5 pl-9 pr-8 text-sm font-medium text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white"
                />
                <button
                  v-if="filters.q_response"
                  type="button"
                  aria-label="Limpiar búsqueda en response"
                  class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10"
                  @click="filters.q_response = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </span>
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Fecha desde
              <input
                v-model="filters.fecha_desde"
                type="date"
                class="rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 text-sm font-medium text-gray-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white"
                @change="onSelectChange"
              />
            </label>

            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Fecha hasta
              <input
                v-model="filters.fecha_hasta"
                type="date"
                class="rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 text-sm font-medium text-gray-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white"
                @change="onSelectChange"
              />
            </label>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
              <CalendarDays class="h-3.5 w-3.5" /> Rango rápido:
            </span>
            <button type="button" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20" @click="presetFechas(1)">Hoy</button>
            <button type="button" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20" @click="presetFechas(2)">Últimas 24 h</button>
            <button type="button" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20" @click="presetFechas(7)">7 días</button>
            <button type="button" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20" @click="presetFechas(30)">30 días</button>
            <span class="mx-1 hidden h-5 w-px bg-gray-200 dark:bg-white/10 sm:inline-block"></span>
            <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
              Por página:
              <select
                v-model.number="filters.per_page"
                class="rounded-lg border border-gray-200/80 bg-white px-2 py-1.5 text-xs font-semibold text-gray-800 outline-none dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white"
                @change="onSelectChange"
              >
                <option :value="5">5</option>
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
              </select>
            </label>
            <button
              v-if="hayFiltros"
              type="button"
              class="ml-auto inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-bold text-blue-700 transition hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50"
              @click="limpiarFiltros"
            >
              <RotateCcw class="h-3.5 w-3.5" /> Limpiar filtros
            </button>
          </div>

          <div v-if="chips.length" class="mt-3 flex flex-wrap gap-1.5">
            <button
              v-for="chip in chips"
              :key="chip.key + chip.label"
              type="button"
              class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:border-red-800 dark:hover:bg-red-900/30 dark:hover:text-red-300"
              :title="`Quitar filtro: ${chip.label}`"
              @click="quitarFiltro(chip.key)"
            >
              <span class="truncate">{{ chip.label }}</span>
              <X class="h-3 w-3 shrink-0" />
            </button>
          </div>
        </div>

        <div class="text-sm text-gray-600 dark:text-gray-400">
          Mostrando
          <span class="font-bold text-blue-600 dark:text-blue-400">{{ paginated?.from ?? 0 }}–{{ paginated?.to ?? 0 }}</span>
          de <span class="font-bold text-gray-900 dark:text-white">{{ paginated?.total ?? 0 }}</span> registros
        </div>

        <Datatable
          :columns="columns"
          :rows="rows"
          :row-actions="rowActions"
          :server-side="true"
          search-placeholder="Buscar logs..."
          empty-message="No se encontraron registros de logs con los filtros aplicados"
          @row-action="handleRowAction"
          @sort="onSort"
        >
          <template #cell-metodo="{ value }">
            <span :class="metodoBadge(String(value))">{{ value }}</span>
          </template>
          <template #cell-estatus="{ value }">
            <span :class="estatusBadge(value)" :title="value !== null ? `${value} ${estatusTexto(Number(value))}` : 'Sin estatus'">
              {{ value ?? '—' }}
            </span>
          </template>
          <template #cell-duracion_ms="{ value }">
            <span :class="duracionClase(value)">{{ value ?? '—' }}</span>
          </template>
          <template #cell-ruta="{ value }">
            <span class="font-mono text-xs" :title="String(value ?? '')">{{ value ?? '—' }}</span>
          </template>
        </Datatable>

        <!-- Paginación server-side -->
        <div
          v-if="(paginated?.last_page ?? 1) > 1"
          class="flex flex-col items-center justify-between gap-3 rounded-2xl border border-gray-200/80 bg-white px-5 py-4 shadow-sm sm:flex-row dark:border-sidebar-border/50 dark:bg-sidebar"
        >
          <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
            Página <span class="font-bold text-gray-900 dark:text-white">{{ paginated?.current_page }}</span>
            de <span class="font-bold text-gray-900 dark:text-white">{{ paginated?.last_page }}</span>
          </div>
          <nav class="flex items-center gap-1.5" aria-label="Paginación de logs">
            <button
              type="button"
              :disabled="(paginated?.current_page ?? 1) <= 1 || navegando"
              class="inline-flex items-center gap-1 rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10"
              @click="irAPagina((paginated?.current_page ?? 1) - 1)"
            >
              <ChevronLeft class="h-3.5 w-3.5" /> Anterior
            </button>
            <button
              v-for="p in paginasVisibles"
              :key="p"
              type="button"
              :aria-current="p === paginated?.current_page ? 'page' : undefined"
              :class="[
                'rounded-xl border px-3 py-2 text-xs font-bold transition',
                p === paginated?.current_page
                  ? 'border-blue-600 bg-blue-600 text-white shadow-md shadow-blue-500/30'
                  : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10',
              ]"
              @click="irAPagina(p)"
            >
              {{ p }}
            </button>
            <button
              type="button"
              :disabled="(paginated?.current_page ?? 1) >= (paginated?.last_page ?? 1) || navegando"
              class="inline-flex items-center gap-1 rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10"
              @click="irAPagina((paginated?.current_page ?? 1) + 1)"
            >
              Siguiente <ChevronRight class="h-3.5 w-3.5" />
            </button>
          </nav>
        </div>
      </div>
    </FadeIn>

    <ModalForm v-model="showModal" :title="modalTitle" width-class="max-w-3xl" @close="showModal = false">
      <div v-if="modalMeta" class="mb-3 flex flex-wrap items-center gap-1.5">
        <span :class="metodoBadge(modalMeta.metodo)">{{ modalMeta.metodo }}</span>
        <span :class="estatusBadge(modalMeta.estatus)">{{ modalMeta.estatus ?? '—' }}</span>
        <span v-if="modalMeta.fecha" class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ modalMeta.fecha }}</span>
        <span v-if="modalMeta.usuario" class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ modalMeta.usuario }}</span>
        <span v-if="modalMeta.ip" class="rounded-lg bg-gray-100 px-2.5 py-1 font-mono text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ modalMeta.ip }}</span>
        <span v-if="modalMeta.duracion_ms !== null" class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ modalMeta.duracion_ms }} ms</span>
      </div>
      <div v-if="modalMeta?.ruta" class="mb-3 truncate font-mono text-xs text-gray-500 dark:text-gray-400" :title="modalMeta.url ?? modalMeta.ruta ?? ''">
        {{ modalMeta.ruta }}
      </div>

      <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-xl border border-gray-200 p-1 text-xs font-bold dark:border-white/10">
          <button
            type="button"
            :class="['rounded-lg px-3 py-1.5 transition', modalTab === 'body' ? 'bg-blue-600 text-white shadow' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white']"
            @click="cambiarTab('body')"
          >
            Body
          </button>
          <button
            type="button"
            :class="['rounded-lg px-3 py-1.5 transition', modalTab === 'response' ? 'bg-blue-600 text-white shadow' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white']"
            @click="cambiarTab('response')"
          >
            Response
          </button>
        </div>
        <div class="ml-auto flex gap-2">
          <button
            type="button"
            :disabled="modalLoading || !modalContent"
            class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-bold text-gray-600 transition hover:bg-gray-50 disabled:opacity-40 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/10"
            @click="copiarContenido"
          >
            <Copy class="h-3.5 w-3.5" /> {{ copiado ? '¡Copiado!' : 'Copiar' }}
          </button>
          <button
            type="button"
            :disabled="modalLoading || !modalContent"
            class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-bold text-gray-600 transition hover:bg-gray-50 disabled:opacity-40 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/10"
            @click="descargarJson"
          >
            <Download class="h-3.5 w-3.5" /> JSON
          </button>
        </div>
      </div>

      <div v-if="modalTab === 'body' && modalMeta?.truncado_body" class="mb-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
        Contenido truncado a 20 000 caracteres al registrarse.
      </div>
      <div v-if="modalTab === 'response' && modalMeta?.truncado_response" class="mb-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
        Contenido truncado a 20 000 caracteres al registrarse.
      </div>

      <div class="relative">
        <div v-if="modalLoading" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
          <div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></div>
          Cargando…
        </div>
        <pre v-else class="max-h-[50vh] overflow-auto whitespace-pre-wrap break-words rounded-xl bg-gray-100 p-4 text-xs leading-relaxed text-gray-800 dark:bg-zinc-800 dark:text-gray-200">{{ modalContent }}</pre>
      </div>
    </ModalForm>
  </AppLayout>
</template>
