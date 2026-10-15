<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/ui/tables/Datatable.vue';
import Titulo from '@/components/ui/Titulo.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
import ModalForm from '@/components/ui/modals/modalForm.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { CalendarDays, ChevronLeft, ChevronRight, Copy, Download, Mail, RotateCcw, Search, X } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Correos', href: '' }];

interface Correo {
  id: number;
  destinatario: string | null;
  nombre: string | null;
  asunto: string | null;
  tipo: string;
  estado: string;
  id_cliente: number | null;
  id_operacion: number | null;
  alertas_count: number;
  fecha: string | null;
  tiene_cuerpo: boolean;
  truncado: boolean;
}

interface Paginated {
  data: Correo[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

interface FilterOptions {
  tipos: string[];
  estados: string[];
  destinatarios: { correo: string; total: number }[];
}

const page = usePage<{ correos: Paginated; filters: Record<string, any>; filterOptions: FilterOptions }>();
const paginated = computed(() => page.props.correos);
const filterOptions = computed<FilterOptions>(() => ({
  tipos: page.props.filterOptions?.tipos ?? [],
  estados: page.props.filterOptions?.estados ?? [],
  destinatarios: page.props.filterOptions?.destinatarios ?? [],
}));
const initial = page.props.filters ?? {};
const filters = reactive({
  search: (initial.search as string) ?? '',
  tipo: (initial.tipo as string) ?? '',
  estado: (initial.estado as string) ?? '',
  fecha_desde: (initial.fecha_desde as string) ?? '',
  fecha_hasta: (initial.fecha_hasta as string) ?? '',
  q_cuerpo: (initial.q_cuerpo as string) ?? '',
  per_page: Number(initial.per_page ?? paginated.value?.per_page ?? 10),
  sort: (initial.sort as string) ?? 'id',
  direction: (initial.direction as string) ?? 'desc',
});
const navegando = ref(false);
let debounceTimer: number | null = null;

function paramsLimpios() {
  const out: Record<string, any> = {};
  if (filters.search.trim() !== '') out.search = filters.search.trim();
  if (filters.tipo !== '') out.tipo = filters.tipo;
  if (filters.estado !== '') out.estado = filters.estado;
  if (filters.fecha_desde !== '') out.fecha_desde = filters.fecha_desde;
  if (filters.fecha_hasta !== '') out.fecha_hasta = filters.fecha_hasta;
  if (filters.q_cuerpo.trim() !== '') out.q_cuerpo = filters.q_cuerpo.trim();
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
    router.get('/correos', params, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (navegando.value = false) });
  };
  if (!debounced) { if (debounceTimer) window.clearTimeout(debounceTimer); lanzar(); return; }
  if (debounceTimer) window.clearTimeout(debounceTimer);
  debounceTimer = window.setTimeout(lanzar, 450);
}
watch(() => [filters.search, filters.q_cuerpo], () => aplicar(true));
function onSelectChange() { aplicar(false); }
function onSort(key: string, direction: 'asc' | 'desc') { filters.sort = key; filters.direction = direction; aplicar(false, false); }
function irAPagina(p: number) {
  if (p < 1 || p > (paginated.value?.last_page ?? 1) || navegando.value) return;
  navegando.value = true;
  router.get('/correos', { ...paramsLimpios(), page: p }, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (navegando.value = false) });
}
function limpiarFiltros() {
  filters.search = ''; filters.tipo = ''; filters.estado = '';
  filters.fecha_desde = ''; filters.fecha_hasta = ''; filters.q_cuerpo = '';
  filters.sort = 'id'; filters.direction = 'desc'; aplicar(false);
}
function presetFechas(dias: number) {
  const hoy = new Date(); const desde = new Date(hoy);
  desde.setDate(hoy.getDate() - (dias - 1));
  const fmt = (d: Date) => d.toISOString().slice(0, 10);
  filters.fecha_desde = fmt(desde); filters.fecha_hasta = fmt(hoy); aplicar(false);
}
const hayFiltros = computed(() => filters.search !== '' || filters.tipo !== '' || filters.estado !== '' || filters.fecha_desde !== '' || filters.fecha_hasta !== '' || filters.q_cuerpo !== '');
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

const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'fecha', label: 'Fecha', sortable: true },
  { key: 'destinatario', label: 'Destinatario', sortable: true },
  { key: 'asunto', label: 'Asunto', sortable: true },
  { key: 'tipo', label: 'Tipo', sortable: true },
  { key: 'estado', label: 'Estado', sortable: true },
  { key: 'alertas_count', label: 'Alertas', sortable: false },
];
const rowActions = [{ id: 'ver', label: 'Ver', icon: 'view' as const, variant: 'secondary' as const, disabled: (row: Record<string, any>) => !row.tiene_cuerpo }];
const rows = computed(() => (paginated.value?.data ?? []).map((c) => ({
  id: c.id, fecha: c.fecha, destinatario: c.destinatario, asunto: c.asunto, tipo: c.tipo,
  estado: c.estado, alertas_count: c.alertas_count, tiene_cuerpo: c.tiene_cuerpo,
})));

function tipoBadge(t: string): string {
  const base = 'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold tracking-tight';
  if (t === 'AlertaConsolidada') return `${base} bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300`;
  if (t === 'RiesgoAlto') return `${base} bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300`;
  return `${base} bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300`;
}
function estadoBadge(e: string): string {
  const base = 'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold tracking-tight';
  if (e === 'Enviado') return `${base} bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300`;
  if (e === 'OmitidoSinConfig') return `${base} bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300`;
  return `${base} bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300`;
}

const showModal = ref(false);
const modalTitle = ref('');
const modalHtml = ref('');
const modalFuente = ref('');
const modalLoading = ref(false);
const modalMeta = ref<any>(null);
const verFuente = ref(false);
const copiado = ref(false);

async function verDetalle(rowData: Record<string, any>) {
  modalLoading.value = true; modalHtml.value = ''; modalFuente.value = ''; modalMeta.value = null;
  verFuente.value = false; copiado.value = false; showModal.value = true;
  try {
    const res = await fetch(`/correos/${rowData.id}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (!res.ok) { modalFuente.value = data.message || 'No se pudo cargar el correo.'; }
    else {
      modalMeta.value = data.meta ?? null;
      modalTitle.value = data.meta?.asunto || `Correo #${data.meta?.id}`;
      modalHtml.value = data.cuerpo || '';
      modalFuente.value = data.cuerpo || 'Sin contenido registrado.';
    }
  } catch { modalFuente.value = 'Error al obtener el detalle del correo.'; }
  finally { modalLoading.value = false; }
}
function handleRowAction(_a: string, row: Record<string, any>) { verDetalle(row); }
async function copiarContenido() {
  try { await navigator.clipboard.writeText(modalFuente.value); copiado.value = true; window.setTimeout(() => (copiado.value = false), 1600); } catch { /* noop */ }
}
function descargarHtml() {
  const blob = new Blob([modalFuente.value], { type: 'text/html;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = `correo-${modalMeta.value?.id ?? 'detalle'}.html`;
  document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
}
</script>

<template>
  <Head title="Correos" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <FadeIn>
      <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <Titulo :icon="Mail" title="Correos emitidos por el sistema" size="md" weight="bold" class="mb-2" />
          <div v-if="navegando" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></div>
            Actualizando…
          </div>
        </div>

        <div class="rounded-2xl border border-gray-200/80 bg-white px-5 py-4 shadow-sm dark:border-sidebar-border/50 dark:bg-sidebar">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Tipo
              <select v-model="filters.tipo" class="rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 text-sm font-medium outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white" @change="onSelectChange">
                <option value="">Todos</option>
                <option v-for="t in filterOptions.tipos" :key="t" :value="t">{{ t }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Estado
              <select v-model="filters.estado" class="rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 text-sm font-medium outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white" @change="onSelectChange">
                <option value="">Todos</option>
                <option v-for="e in filterOptions.estados" :key="e" :value="e">{{ e }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Buscar <span class="font-normal text-gray-400">(destinatario, asunto)</span>
              <span class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="filters.search" type="text" placeholder="Buscar correos…" autocomplete="off" spellcheck="false" class="w-full rounded-xl border border-gray-200/80 bg-white py-2.5 pl-9 pr-8 text-sm font-medium outline-none placeholder:text-gray-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white" />
                <button v-if="filters.search" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-gray-400 hover:bg-gray-100" @click="filters.search = ''"><X class="h-3.5 w-3.5" /></button>
              </span>
            </label>
            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Contiene en cuerpo (HTML)
              <span class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="filters.q_cuerpo" type="text" placeholder="Ej. folio, alerta…" autocomplete="off" spellcheck="false" class="w-full rounded-xl border border-gray-200/80 bg-white py-2.5 pl-9 pr-8 text-sm font-medium outline-none placeholder:text-gray-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white" />
                <button v-if="filters.q_cuerpo" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-gray-400 hover:bg-gray-100" @click="filters.q_cuerpo = ''"><X class="h-3.5 w-3.5" /></button>
              </span>
            </label>
            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Fecha desde
              <input v-model="filters.fecha_desde" type="date" class="rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 text-sm font-medium outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white" @change="onSelectChange" />
            </label>
            <label class="flex flex-col gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
              Fecha hasta
              <input v-model="filters.fecha_hasta" type="date" class="rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 text-sm font-medium outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/40 dark:border-sidebar-border/60 dark:bg-sidebar dark:text-white" @change="onSelectChange" />
            </label>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400"><CalendarDays class="h-3.5 w-3.5" /> Rango rápido:</span>
            <button type="button" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold hover:bg-gray-200 dark:bg-white/10 dark:hover:bg-white/20" @click="presetFechas(1)">Hoy</button>
            <button type="button" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold hover:bg-gray-200 dark:bg-white/10 dark:hover:bg-white/20" @click="presetFechas(7)">7 días</button>
            <button type="button" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold hover:bg-gray-200 dark:bg-white/10 dark:hover:bg-white/20" @click="presetFechas(30)">30 días</button>
            <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500">Por página:
              <select v-model.number="filters.per_page" class="rounded-lg border border-gray-200/80 px-2 py-1.5 text-xs font-semibold dark:bg-sidebar" @change="onSelectChange">
                <option :value="5">5</option><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option>
              </select>
            </label>
            <button v-if="hayFiltros" type="button" class="ml-auto inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100" @click="limpiarFiltros"><RotateCcw class="h-3.5 w-3.5" /> Limpiar filtros</button>
          </div>
        </div>

        <div class="text-sm text-gray-600 dark:text-gray-400">
          Mostrando <span class="font-bold text-blue-600">{{ paginated?.from ?? 0 }}–{{ paginated?.to ?? 0 }}</span>
          de <span class="font-bold">{{ paginated?.total ?? 0 }}</span> correos
        </div>

        <Datatable :columns="columns" :rows="rows" :row-actions="rowActions" :server-side="true" empty-message="No se encontraron correos con los filtros aplicados" @row-action="handleRowAction" @sort="onSort">
          <template #cell-tipo="{ value }"><span :class="tipoBadge(String(value))">{{ value }}</span></template>
          <template #cell-estado="{ value }"><span :class="estadoBadge(String(value))">{{ value }}</span></template>
          <template #cell-asunto="{ value }"><span class="max-w-md truncate block" :title="String(value ?? '')">{{ value ?? '—' }}</span></template>
        </Datatable>

        <div v-if="(paginated?.last_page ?? 1) > 1" class="flex flex-col items-center justify-between gap-3 rounded-2xl border border-gray-200/80 bg-white px-5 py-4 shadow-sm sm:flex-row dark:border-sidebar-border/50 dark:bg-sidebar">
          <div class="text-xs font-medium text-gray-500">Página <span class="font-bold">{{ paginated?.current_page }}</span> de <span class="font-bold">{{ paginated?.last_page }}</span></div>
          <nav class="flex items-center gap-1.5">
            <button type="button" :disabled="(paginated?.current_page ?? 1) <= 1 || navegando" class="inline-flex items-center gap-1 rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold hover:bg-gray-50 disabled:opacity-40" @click="irAPagina((paginated?.current_page ?? 1) - 1)"><ChevronLeft class="h-3.5 w-3.5" /> Anterior</button>
            <button v-for="p in paginasVisibles" :key="p" type="button" :class="['rounded-xl border px-3 py-2 text-xs font-bold', p === paginated?.current_page ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-200 hover:bg-gray-50']" @click="irAPagina(p)">{{ p }}</button>
            <button type="button" :disabled="(paginated?.current_page ?? 1) >= (paginated?.last_page ?? 1) || navegando" class="inline-flex items-center gap-1 rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold hover:bg-gray-50 disabled:opacity-40" @click="irAPagina((paginated?.current_page ?? 1) + 1)">Siguiente <ChevronRight class="h-3.5 w-3.5" /></button>
          </nav>
        </div>
      </div>
    </FadeIn>

    <ModalForm v-model="showModal" :title="modalTitle" width-class="max-w-4xl" @close="showModal = false">
      <div v-if="modalMeta" class="mb-3 flex flex-wrap items-center gap-1.5">
        <span :class="tipoBadge(modalMeta.tipo)">{{ modalMeta.tipo }}</span>
        <span :class="estadoBadge(modalMeta.estado)">{{ modalMeta.estado }}</span>
        <span v-if="modalMeta.fecha" class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold">{{ modalMeta.fecha }}</span>
        <span v-if="modalMeta.destinatario" class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold">{{ modalMeta.destinatario }}</span>
        <span v-if="modalMeta.alertas_count" class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold">{{ modalMeta.alertas_count }} alerta(s)</span>
      </div>
      <div v-if="modalMeta?.truncado" class="mb-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">Contenido truncado a 20 000 caracteres al registrarse.</div>
      <div v-if="modalMeta?.error" class="mb-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">Error: {{ modalMeta.error }}</div>
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-xl border border-gray-200 p-1 text-xs font-bold">
          <button type="button" :class="['rounded-lg px-3 py-1.5', !verFuente ? 'bg-blue-600 text-white shadow' : 'text-gray-500']" @click="verFuente = false">Vista</button>
          <button type="button" :class="['rounded-lg px-3 py-1.5', verFuente ? 'bg-blue-600 text-white shadow' : 'text-gray-500']" @click="verFuente = true">Fuente HTML</button>
        </div>
        <div class="ml-auto flex gap-2">
          <button type="button" :disabled="modalLoading || !modalFuente" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-bold hover:bg-gray-50 disabled:opacity-40" @click="copiarContenido"><Copy class="h-3.5 w-3.5" /> {{ copiado ? '¡Copiado!' : 'Copiar' }}</button>
          <button type="button" :disabled="modalLoading || !modalFuente" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-bold hover:bg-gray-50 disabled:opacity-40" @click="descargarHtml"><Download class="h-3.5 w-3.5" /> HTML</button>
        </div>
      </div>
      <div class="relative">
        <div v-if="modalLoading" class="flex items-center gap-2 text-sm text-gray-600"><div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></div>Cargando…</div>
        <div v-else-if="!verFuente" class="max-h-[55vh] overflow-auto rounded-xl border border-gray-200 bg-white p-4" v-html="modalHtml"></div>
        <pre v-else class="max-h-[50vh] overflow-auto whitespace-pre-wrap break-words rounded-xl bg-gray-100 p-4 text-xs leading-relaxed">{{ modalFuente }}</pre>
      </div>
    </ModalForm>
  </AppLayout>
</template>
