<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Select from '@/components/forms/Select.vue';
import DateInput from '@/components/forms/DateInput.vue';

const tipoOperacion = ref('');
const estatusOperacion = ref('');
const fechaInicial = ref<Date | null>(null);
const fechaFinal = ref<Date | null>(null);

const page = usePage();
const opcionesTipoOperacion = computed(() => {
  const tipos = (page.props as any)?.tiposOperacion ?? [];
  const opts = Array.isArray(tipos) ? tipos.map((t: string) => ({ value: t, label: t })) : [];
  return [{ value: 'Todos', label: 'Todos' }, ...opts];
});

const opcionesEstatus = [
  { value: 'Todos', label: 'Todos' },
  { value: 'Generado', label: 'Generado' },
  { value: 'Analizado', label: 'Analizado' },
  { value: 'Cerrado', label: 'Cerrado' },
  { value: 'Reportado', label: 'Reportado' },
  { value: 'Enviado', label: 'Enviado' },
];

function toISODate(d: Date) {
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

const fechaInicialStr = computed<string>({
  get() {
    return fechaInicial.value ? toISODate(fechaInicial.value) : '';
  },
  set(v: string) {
    fechaInicial.value = v ? new Date(v) : null;
  },
});

const fechaFinalStr = computed<string>({
  get() {
    return fechaFinal.value ? toISODate(fechaFinal.value) : '';
  },
  set(v: string) {
    fechaFinal.value = v ? new Date(v) : null;
  },
});

interface Reporte {
  IDReporte: number;
  IDRegistroAlerta: number | null;
  TipoReporte: string | null;
  PeriodoReporte: string | null;
  Folio: string | null;
  OrganoSupervisor: string | null;
  CveSujetoObligado: string | null;
  Localidad: string | null;
  Sucursal: string | null;
  TipoOperacion: string | null;
  InstrumentoMonetario: string | null;
  NoPoliza: string | null;
  Monto: number | null;
  IDMoneda: number | null;
  FechaOperacion: string | null;
  FechaDeteccion: string | null;
  Nacionalidad: string | null;
  TipoPersona: string | null;
  RazonSocial: string | null;
  Nombre: string | null;
  APaterno: string | null;
  AMaterno: string | null;
  RFC: string | null;
  CURP: string | null;
  FechaNacimiento: string | null;
  Domicilio: string | null;
  Colonia: string | null;
  Ciudad: string | null;
  Telefono: string | null;
  Ocupacion: string | null;
  NombreAgente: string | null;
  APaternoAgente: string | null;
  AMaternoAgente: string | null;
  RFCAgente: string | null;
  CURPAgente: string | null;
  Cuenta: string | null;
  NoPolizaCuenta: string | null;
  CveSujetoObl: string | null;
  NombreTitular: string | null;
  APaternoTitular: string | null;
  AMaternoTitular: string | null;
  Descripcion: string | null;
  Razon: string | null;
  Estatus: string | null;
  IDTipoReporte: number | null;
  IDTipoOperacion: number | null;
}

const resultados = ref<Reporte[]>(((page.props as any)?.reportes ?? []) as Reporte[]);
const isLoading = ref(false);
const search = ref('');
const searchInput = ref('');
let searchTimer: number | null = null;
const perPage = ref(10);
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
    const phrase = m[1];
    const word = m[2];
    const token = (phrase || word || '').trim();
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

function nextPage() { if (currentPage.value < totalPages.value) currentPage.value++; }
function prevPage() { if (currentPage.value > 1) currentPage.value--; }
watch([search, perPage], () => (currentPage.value = 1));
watch(searchInput, (v) => {
  if (searchTimer) window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => {
    search.value = v;
  }, 250);
});

const domicilioExpandidos = ref<Set<number>>(new Set());
function isDomicilioExpandido(id: number) { return domicilioExpandidos.value.has(id); }
function toggleDomicilio(id: number) {
  if (domicilioExpandidos.value.has(id)) domicilioExpandidos.value.delete(id);
  else domicilioExpandidos.value.add(id);
  domicilioExpandidos.value = new Set(domicilioExpandidos.value);
}
function truncarTexto(texto: string, largo: number) {
  if (!texto) return '';
  return texto.length > largo ? `${texto.slice(0, largo)}…` : texto;
}

const showingMessage = computed(() => {
  if (isLoading.value || !filteredResultados.value.length) return '';
  const total = filteredResultados.value.length;
  if (perPage.value === -1) return `Mostrando todos los ${total.toLocaleString()} registros.`;
  const start = (currentPage.value - 1) * perPage.value + 1;
  const end = Math.min(start + perPage.value - 1, total);
  return `Mostrando ${start.toLocaleString()} a ${end.toLocaleString()} de un total de ${total.toLocaleString()} registros.`;
});

const breadcrumbs = [{ title: 'Reporte de operaciones', href: '' }];

const buscar = async () => {
  const params = new URLSearchParams({
    tipo: tipoOperacion.value || '',
    estatus: estatusOperacion.value || '',
    fecha_ini: fechaInicialStr.value || '',
    fecha_fin: fechaFinalStr.value || '',
  });
  isLoading.value = true;
  try {
    const res = await fetch(`/reporte-operaciones/obtener?${params.toString()}`, { method: 'GET', headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    resultados.value = (data?.reportes ?? []) as Reporte[];
    currentPage.value = 1;
  } catch {
    resultados.value = [];
  } finally {
    isLoading.value = false;
  }
};

const descargarCSV = () => {
  const params = new URLSearchParams({
    tipo: tipoOperacion.value || '',
    estatus: estatusOperacion.value || '',
    fecha_ini: fechaInicialStr.value || '',
    fecha_fin: fechaFinalStr.value || '',
  });
  window.location.href = `/reporte-operaciones/exportar?${params.toString()}`;
};

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
    </div>


    <div class="mt-6 flex flex-col gap-4 rounded-xl border border-slate-100 bg-gradient-to-r from-white/90 via-slate-50/70 to-white/90 p-4 shadow-sm backdrop-blur-sm transition-colors duration-200 ease-out focus-within:border-blue-400/80 focus-within:shadow-[0_0_0_1px_rgba(59,130,246,0.3)] dark:border-neutral-800/80 dark:bg-gradient-to-r dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90">
      <form @submit.prevent="buscar" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Tipo de operación -->
          <div>
            <Select
              id="tipo-operacion"
              label="Tipo de operación:"
              :options="opcionesTipoOperacion"
              v-model="tipoOperacion"
              placeholder="Seleccione tipo de operación"
            />
          </div>

          <!-- Estatus de operación -->
          <div>
            <Select
              id="estatus-operacion"
              label="Estatus Operación:"
              :options="opcionesEstatus"
              v-model="estatusOperacion"
              placeholder="Seleccione estatus"
            />
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

        <!-- Botones a la derecha -->
        <div class="mt-4 flex justify-end gap-2">
          <button
            type="button"
            @click="descargarCSV"
            class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition-all duration-150 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:hover:bg-neutral-800"
          >
            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Descargar CSV
          </button>
          <button type="submit" class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            Buscar
          </button>
        </div>
      </form>
    </div>

    <div class="mt-6 flex flex-col gap-4 rounded-xl border border-slate-100 bg-gradient-to-r from-white/90 via-slate-50/70 to-white/90 p-4 shadow-sm backdrop-blur-sm transition-colors duration-200 ease-out dark:border-neutral-800/80 dark:bg-gradient-to-r dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90">
      <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="flex flex-col gap-1 w-48">
          <label class="text-xs text-slate-600 dark:text-neutral-300">Número de elementos</label>
          <select v-model.number="perPage" :disabled="isLoading" class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-xs text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
            <option :value="-1">Todos</option>
          </select>
        </div>
      </div>

      <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)]">
        <div class="p-4 flex items-center justify-between">
          <div v-if="showingMessage" class="text-xs text-slate-500 dark:text-neutral-400">{{ showingMessage }}</div>
        </div>
        <div class="max-h-[28rem] overflow-y-auto overflow-x-auto">
          <table class="min-w-full border-collapse text-sm text-slate-900 dark:text-white whitespace-nowrap">
            <thead>
              <tr class="sticky top-0 z-10 bg-gradient-to-r from-slate-50 via-slate-50/95 to-blue-50/60 text-xs font-semibold uppercase tracking-wide text-slate-700 backdrop-blur-sm dark:bg-gradient-to-r dark:from-neutral-900/95 dark:via-neutral-900/95 dark:to-slate-900/95 dark:text-neutral-200">
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">ID</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">IDRegistroAlerta</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">TipoReporte</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">PeriodoReporte</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Folio</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">OrganoSupervisor</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">CveSujetoObligado</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Localidad</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Sucursal</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">TipoOperacion</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">InstrumentoMonetario</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">NoPoliza</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Monto</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">IDMoneda</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">FechaOperacion</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">FechaDeteccion</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Nacionalidad</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">TipoPersona</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">RazonSocial</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Nombre</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">APaterno</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">AMaterno</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">RFC</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">CURP</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">FechaNacimiento</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Domicilio</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Colonia</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Ciudad</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Telefono</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Ocupacion</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">NombreAgente</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">APaternoAgente</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">AMaternoAgente</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">RFCAgente</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">CURPAgente</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Cuenta</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">NoPolizaCuenta</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">CveSujetoObl</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">NombreTitular</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">APaternoTitular</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">AMaternoTitular</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Descripcion</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Razon</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Estatus</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">IDTipoReporte</th>
                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">IDTipoOperacion</th>
              </tr>
            </thead>
            <tbody v-if="paginatedResultados.length">
              <tr v-for="item in paginatedResultados" :key="item.IDReporte" class="group cursor-pointer border-b border-l-2 border-slate-100 border-l-transparent bg-white transition-all duration-200 ease-out hover:-translate-y-[1px] hover:border-l-blue-400 hover:bg-gradient-to-r hover:from-white hover:via-slate-50/80 hover:to-blue-50/40 hover:shadow-[0_10px_30px_rgba(15,23,42,0.08)] dark:border-neutral-800/60 dark:border-l-transparent dark:bg-neutral-950/40 dark:hover:border-l-blue-500 dark:hover:bg-gradient-to-r dark:hover:from-neutral-950/90 dark:hover:via-neutral-900/90 dark:hover:to-slate-800/90 dark:hover:shadow-[0_18px_40px_rgba(0,0,0,0.75)]">
                <td class="px-3 py-2 align-middle">{{ item.IDReporte }}</td>
                <td class="px-3 py-2 align-middle">{{ item.IDRegistroAlerta }}</td>
                <td class="px-3 py-2 align-middle">{{ item.TipoReporte }}</td>
                <td class="px-3 py-2 align-middle">{{ item.PeriodoReporte }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Folio }}</td>
                <td class="px-3 py-2 align-middle">{{ item.OrganoSupervisor }}</td>
                <td class="px-3 py-2 align-middle">{{ item.CveSujetoObligado }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Localidad }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Sucursal }}</td>
                <td class="px-3 py-2 align-middle">{{ item.TipoOperacion }}</td>
                <td class="px-3 py-2 align-middle">{{ item.InstrumentoMonetario }}</td>
                <td class="px-3 py-2 align-middle">{{ item.NoPoliza }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Monto }}</td>
                <td class="px-3 py-2 align-middle">{{ item.IDMoneda }}</td>
                <td class="px-3 py-2 align-middle">{{ item.FechaOperacion }}</td>
                <td class="px-3 py-2 align-middle">{{ item.FechaDeteccion }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Nacionalidad }}</td>
                <td class="px-3 py-2 align-middle">{{ item.TipoPersona }}</td>
                <td class="px-3 py-2 align-middle">{{ item.RazonSocial }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Nombre }}</td>
                <td class="px-3 py-2 align-middle">{{ item.APaterno }}</td>
                <td class="px-3 py-2 align-middle">{{ item.AMaterno }}</td>
                <td class="px-3 py-2 align-middle">{{ item.RFC }}</td>
                <td class="px-3 py-2 align-middle">{{ item.CURP }}</td>
                <td class="px-3 py-2 align-middle">{{ item.FechaNacimiento }}</td>
                <td class="px-3 py-2 align-middle">
                  <div class="flex items-start gap-1">
                    <button
                      type="button"
                      class="mt-0.5 inline-flex h-5 w-5 items-center justify-center text-blue-600 transition-colors hover:text-blue-700 focus:outline-none"
                      @click.stop="toggleDomicilio(item.IDReporte)"
                      :aria-expanded="isDomicilioExpandido(item.IDReporte)"
                      :title="isDomicilioExpandido(item.IDReporte) ? 'Contraer domicilio' : 'Desplegar domicilio'"
                    >
                      <svg
                        class="h-4 w-4 transition-transform duration-200"
                        :class="isDomicilioExpandido(item.IDReporte) ? 'rotate-90' : ''"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                      >
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                      </svg>
                    </button>
                    <div class="min-w-0">
                      <span v-if="isDomicilioExpandido(item.IDReporte)" class="whitespace-nowrap">
                        {{ item.Domicilio || '' }}
                      </span>
                      <span v-else>
                        {{ truncarTexto(item.Domicilio ?? '', 10) }}
                      </span>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2 align-middle">{{ item.Colonia }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Ciudad }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Telefono }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Ocupacion }}</td>
                <td class="px-3 py-2 align-middle">{{ item.NombreAgente }}</td>
                <td class="px-3 py-2 align-middle">{{ item.APaternoAgente }}</td>
                <td class="px-3 py-2 align-middle">{{ item.AMaternoAgente }}</td>
                <td class="px-3 py-2 align-middle">{{ item.RFCAgente }}</td>
                <td class="px-3 py-2 align-middle">{{ item.CURPAgente }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Cuenta }}</td>
                <td class="px-3 py-2 align-middle">{{ item.NoPolizaCuenta }}</td>
                <td class="px-3 py-2 align-middle">{{ item.CveSujetoObl }}</td>
                <td class="px-3 py-2 align-middle">{{ item.NombreTitular }}</td>
                <td class="px-3 py-2 align-middle">{{ item.APaternoTitular }}</td>
                <td class="px-3 py-2 align-middle">{{ item.AMaternoTitular }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Descripcion }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Razon }}</td>
                <td class="px-3 py-2 align-middle">{{ item.Estatus }}</td>
                <td class="px-3 py-2 align-middle">{{ item.IDTipoReporte }}</td>
                <td class="px-3 py-2 align-middle">{{ item.IDTipoOperacion }}</td>
              </tr>
            </tbody>
            <tbody v-else>
              <tr>
                <td colspan="48" class="px-3 py-4 text-center text-sm text-slate-500 dark:text-neutral-400">Sin resultados</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-4 flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-100 bg-gradient-to-r from-white via-slate-50/70 to-white p-3 text-slate-900 shadow-sm backdrop-blur-sm sm:flex-row sm:items-center sm:gap-4 dark:border-neutral-800 dark:bg-gradient-to-r dark:from-neutral-950/95 dark:via-neutral-900/90 dark:to-neutral-950/95 dark:text-white">
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
  </AppLayout>
</template>
