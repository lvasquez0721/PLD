<script setup lang="ts">
// Datatable avanzado con paginación y filtros avanzados mejorado
// Optimizado para UX/UI experto con microtipografía y detalles refinados
// Versión responsive mejorada - OPTIMIZADO PARA RENDIMIENTO

import { ref, computed, watch, nextTick, onMounted, onUnmounted, shallowRef, watchEffect } from 'vue';
import FadeIn from '../animation/fadeIn.vue';
import "primeicons/primeicons.css";

interface Column {
  key: string;
  label: string;
  filterType?: 'select' | 'text';
  options?: any[];
  sortable?: boolean;
  width?: string;
  align?: 'left' | 'center' | 'right';
  format?: 'text' | 'number' | 'date' | 'currency';
}

interface CustomButton {
  id: string;
  label: string;
  icon?: string;
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
  disabled?: boolean;
  loading?: boolean;
}

interface RowAction {
  id: string;
  label: string;
  icon?: string;
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
  disabled?: boolean | ((row: Record<string, any>) => boolean);
  hidden?: boolean | ((row: Record<string, any>) => boolean);
}

const props = defineProps<{
  columns: Column[];
  rows: Record<string, any>[];
  loading?: boolean;
  emptyMessage?: string;
  searchPlaceholder?: string;
  customButtons?: CustomButton[];
  rowActions?: RowAction[];
  virtualScrolling?: boolean;
  virtualScrollThreshold?: number;
}>();

// Emits para eventos personalizados
const emit = defineEmits<{
  sort: [key: string, direction: 'asc' | 'desc'];
  filter: [filters: Record<string, string>];
  search: [query: string];
  pageChange: [page: number];
  buttonClick: [buttonId: string, rowData?: Record<string, any>];
  rowAction: [actionId: string, rowData: Record<string, any>];
}>();

const sortKey = ref<string | null>(null);
const sortAsc = ref(true);

// Buscador global con debounce para mejor UX
const search = ref('');
const isSearchFocused = ref(false);
const searchTimeout = ref<number | null>(null);

// Filtros avanzados - usando shallowRef para mejor rendimiento
const advancedFilters = shallowRef<Record<string, string | null>>({});
const showAdvancedFilters = ref(false);

// Cache para filtros inicializados
const initializedFilters = new Set<string>();

// Inicializar advancedFilters de forma optimizada
const initializeFilters = () => {
  const newFilters: Record<string, string | null> = {};
  props.columns.forEach(col => {
    if (col.filterType && !initializedFilters.has(col.key)) {
      newFilters[col.key] = '';
      initializedFilters.add(col.key);
    }
  });
  if (Object.keys(newFilters).length > 0) {
    advancedFilters.value = { ...advancedFilters.value, ...newFilters };
  }
};

// Estados de interacción refinados - usando shallowRef donde sea posible
const hoveredRow = ref<number | null>(null);
const hoveredColumn = ref<string | null>(null);
const isAnimating = ref(false);

// Estados para animación de cambio de página
const isPageChanging = ref(false);
const showTableContent = ref(true);

// Estado para pantalla completa
const isFullscreen = ref(false);
const isAnimatingFullscreen = ref(false);

// Referencia para el contenedor de la tabla
const tableContainer = ref<HTMLElement>();

/**
 * Paginación mejorada
 */
const page = ref(1);
const perPageOptions = [5, 10, 25, 50, 100];
const perPage = ref(perPageOptions[1]);

// Reiniciar página si búsqueda o filtros cambian - optimizado
watchEffect(() => {
  // Solo resetear página si realmente cambió algo relevante
  if (search.value || Object.values(advancedFilters.value).some(v => v && v.trim() !== '')) {
    page.value = 1;
  }
});

// Cache para normalización de strings para mejor rendimiento
const normalizeCache = new Map<string, string>();
const CACHE_SIZE_LIMIT = 1000;

// Función optimizada para normalización de texto con cache
function normalizeString(val: any): string {
  if (val === undefined || val === null) return '';

  const str = String(val);

  // Verificar cache primero
  if (normalizeCache.has(str)) {
    return normalizeCache.get(str)!;
  }

  // Limpiar cache si está lleno
  if (normalizeCache.size >= CACHE_SIZE_LIMIT) {
    normalizeCache.clear();
  }

  const normalized = str
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();

  normalizeCache.set(str, normalized);
  return normalized;
}

// Función para formatear valores según el tipo de columna
function formatValue(value: any, format?: string): string {
  if (value === null || value === undefined) return '';

  switch (format) {
    case 'number':
      return new Intl.NumberFormat('es-ES').format(Number(value));
    case 'currency':
      return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR'
      }).format(Number(value));
    case 'date':
      return new Intl.DateTimeFormat('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
      }).format(new Date(value));
    default:
      return String(value);
  }
}

// Debounced search para mejor rendimiento
function handleSearch(value: string) {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }

  searchTimeout.value = setTimeout(() => {
    emit('search', value);
  }, 300);
}

// Cache para resultados de filtrado
const filterCache = new Map<string, Record<string, any>[]>();
const lastFilterKey = ref<string>('');

// Función optimizada para filtrado con cache
const filteredRows = computed(() => {
  const q = normalizeString(search.value).trim();
  const columns = props.columns;
  const filters = advancedFilters.value;

  // Crear clave de cache única
  const filterKey = `${q}-${JSON.stringify(filters)}-${props.rows.length}`;

  // Verificar cache
  if (filterCache.has(filterKey) && lastFilterKey.value === filterKey) {
    return filterCache.get(filterKey)!;
  }

  let baseRows = props.rows;

  // Optimización: solo filtrar si hay búsqueda
  if (q) {
    const searchColumns = columns.filter(col => col.key);
    baseRows = baseRows.filter(row => {
      for (const col of searchColumns) {
        if (normalizeString(row[col.key]).includes(q)) {
          return true;
        }
      }
      return false;
    });
  }

  // Optimización: solo aplicar filtros avanzados si hay filtros activos
  const activeFilters = Object.entries(filters).filter(([key, value]) =>
    value && value.trim() !== ''
  );

  if (activeFilters.length > 0) {
    baseRows = baseRows.filter(row => {
      for (const [key, filterVal] of activeFilters) {
        const col = columns.find(c => c.key === key);
        if (!col?.filterType) continue;

        const cellVal = row[key];
        if (col.filterType === 'select') {
          if (String(cellVal) !== String(filterVal)) return false;
        } else if (col.filterType === 'text') {
          if (!normalizeString(cellVal).includes(normalizeString(filterVal))) return false;
        }
      }
      return true;
    });
  }

  // Actualizar cache
  filterCache.set(filterKey, baseRows);
  lastFilterKey.value = filterKey;

  // Limpiar cache si está lleno
  if (filterCache.size > 10) {
    filterCache.clear();
  }

  return baseRows;
});

function setSort(key: string) {
  const column = props.columns.find(col => col.key === key);
  if (column?.sortable === false) return;

  isAnimating.value = true;

  if (sortKey.value === key) {
    sortAsc.value = !sortAsc.value;
  } else {
    sortKey.value = key;
    sortAsc.value = true;
  }

  emit('sort', key, sortAsc.value ? 'asc' : 'desc');

  nextTick(() => {
    setTimeout(() => {
      isAnimating.value = false;
    }, 150);
  });
}

// Cache para ordenamiento
const sortCache = new Map<string, Record<string, any>[]>();
const lastSortKey = ref<string>('');

const sortedRows = computed(() => {
  const rows = filteredRows.value;
  const currentSortKey = `${sortKey.value}-${sortAsc.value}`;

  // Verificar cache de ordenamiento
  if (sortCache.has(currentSortKey) && lastSortKey.value === currentSortKey) {
    return sortCache.get(currentSortKey)!;
  }

  if (!sortKey.value) {
    sortCache.set(currentSortKey, rows);
    lastSortKey.value = currentSortKey;
    return rows;
  }

  // Usar Array.from para evitar mutación del array original
  const sorted = Array.from(rows).sort((a, b) => {
    const va = a[sortKey.value!];
    const vb = b[sortKey.value!];

    // Optimización: manejo más eficiente de valores nulos
    if (va == null && vb == null) return 0;
    if (va == null) return sortAsc.value ? 1 : -1;
    if (vb == null) return sortAsc.value ? -1 : 1;

    // Comparación optimizada
    const comparison = va < vb ? -1 : va > vb ? 1 : 0;
    return sortAsc.value ? comparison : -comparison;
  });

  // Actualizar cache
  sortCache.set(currentSortKey, sorted);
  lastSortKey.value = currentSortKey;

  // Limpiar cache si está lleno
  if (sortCache.size > 5) {
    sortCache.clear();
  }

  return sorted;
});

const totalRows = computed(() => sortedRows.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalRows.value / perPage.value)));

watch([totalRows, perPage], () => {
  if (page.value > totalPages.value) {
    page.value = totalPages.value;
  }
});

// Virtualización para grandes datasets
const virtualScrollThreshold = computed(() => props.virtualScrollThreshold || 100);
const shouldUseVirtualScrolling = computed(() =>
  props.virtualScrolling && sortedRows.value.length > virtualScrollThreshold.value
);

// Estado para virtualización
const scrollTop = ref(0);
const containerHeight = ref(0);
const rowHeight = 60; // Altura estimada por fila
const bufferSize = 5; // Filas adicionales para renderizar

// Filas visibles con virtualización
const virtualRows = computed(() => {
  if (!shouldUseVirtualScrolling.value) {
    // Paginación normal
    const start = (page.value - 1) * perPage.value;
    const end = start + perPage.value;
    return sortedRows.value.slice(start, end);
  }

  // Virtualización
  const startIndex = Math.max(0, Math.floor(scrollTop.value / rowHeight) - bufferSize);
  const endIndex = Math.min(
    sortedRows.value.length,
    Math.ceil((scrollTop.value + containerHeight.value) / rowHeight) + bufferSize
  );

  return sortedRows.value.slice(startIndex, endIndex).map((row, index) => ({
    ...row,
    _virtualIndex: startIndex + index,
    _virtualOffset: (startIndex + index) * rowHeight
  }));
});

// Altura total del contenido virtualizado
const virtualContentHeight = computed(() =>
  shouldUseVirtualScrolling.value ? sortedRows.value.length * rowHeight : 0
);

// Función para manejar scroll en contenedor virtualizado
function handleVirtualScroll(event: Event) {
  const target = event.target as HTMLElement;
  scrollTop.value = target.scrollTop;
}

const pagedRows = computed(() => virtualRows.value);

// Función optimizada para cambio de página con animación elegante
async function gotoPage(newPage: number) {
  if (newPage < 1 || newPage > totalPages.value || isPageChanging.value) return;

  isPageChanging.value = true;

  // Usar requestAnimationFrame para mejor rendimiento
  await new Promise(resolve => requestAnimationFrame(resolve));

  // Fade out con slide
  showTableContent.value = false;

  // Esperar a que termine la animación de fade out
  await new Promise(resolve => setTimeout(resolve, 200));

  // Cambiar página
  page.value = newPage;
  emit('pageChange', newPage);

  // Esperar un momento para que se actualice el contenido
  await nextTick();

  // Fade in con slide
  showTableContent.value = true;

  // Esperar a que termine la animación de fade in
  await new Promise(resolve => setTimeout(resolve, 250));

  isPageChanging.value = false;
}

const hasActiveFilters = computed(() =>
  Object.values(advancedFilters.value).some(v => v && v !== '')
);

// Funciones de interacción optimizadas con throttling
let hoverTimeout: number | null = null;

function handleRowHover(index: number | null) {
  if (hoverTimeout) {
    clearTimeout(hoverTimeout);
  }

  hoverTimeout = setTimeout(() => {
    hoveredRow.value = index;
  }, 16); // ~60fps throttling
}

function handleColumnHover(key: string | null) {
  if (hoverTimeout) {
    clearTimeout(hoverTimeout);
  }

  hoverTimeout = setTimeout(() => {
    hoveredColumn.value = key;
  }, 16); // ~60fps throttling
}

function clearAllFilters() {
  // Optimización: crear nuevo objeto en lugar de mutar
  const clearedFilters: Record<string, string | null> = {};
  Object.keys(advancedFilters.value).forEach(k => {
    clearedFilters[k] = '';
  });
  advancedFilters.value = clearedFilters;
  search.value = '';

  // Limpiar caches
  filterCache.clear();
  sortCache.clear();
  normalizeCache.clear();

  emit('filter', {} as Record<string, string>);
}

// Watch optimizado para emitir cambios de filtros
watch(advancedFilters, (newFilters) => {
  // Solo emitir si hay filtros activos
  const activeFilters = Object.fromEntries(
    Object.entries(newFilters).filter(([_, value]) => value && value.trim() !== '')
  );
  emit('filter', activeFilters as Record<string, string>);
}, { flush: 'post' });

// Watch optimizado para emitir cambios de búsqueda
watch(search, (newSearch) => {
  handleSearch(newSearch);
}, { flush: 'post' });

// Watch para actualizar altura del contenedor
watch([isFullscreen, shouldUseVirtualScrolling], () => {
  if (shouldUseVirtualScrolling.value && tableContainer.value) {
    nextTick(() => {
      containerHeight.value = tableContainer.value!.clientHeight;
    });
  }
});

// Función optimizada para alternar pantalla completa
async function toggleFullscreen() {
  if (isAnimatingFullscreen.value) return;

  isAnimatingFullscreen.value = true;

  if (!isFullscreen.value) {
    // Entrando a pantalla completa
    const scrollY = window.scrollY;

    // Usar requestAnimationFrame para mejor rendimiento
    await new Promise(resolve => requestAnimationFrame(resolve));

    // Aplicar estilos para pantalla completa de forma más eficiente
    const bodyStyles = {
      overflow: 'hidden',
      position: 'fixed',
      top: `-${scrollY}px`,
      width: '100%'
    };

    Object.assign(document.body.style, bodyStyles);
    isFullscreen.value = true;

    // Reducir tiempo de animación
    await new Promise(resolve => setTimeout(resolve, 300));
  } else {
    // Saliendo de pantalla completa
    isFullscreen.value = false;

    await new Promise(resolve => setTimeout(resolve, 300));

    // Restaurar estilos de forma más eficiente
    const scrollY = document.body.style.top;
    const resetStyles = {
      position: '',
      top: '',
      width: '',
      overflow: ''
    };

    Object.assign(document.body.style, resetStyles);

    // Restaurar scroll usando requestAnimationFrame
    if (scrollY) {
      requestAnimationFrame(() => {
        window.scrollTo(0, parseInt(scrollY || '0') * -1);
      });
    }
  }

  isAnimatingFullscreen.value = false;
}

// Función para salir de pantalla completa con ESC
function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isFullscreen.value) {
    toggleFullscreen();
  }
}

// Agregar y remover listener de teclado

onMounted(() => {
  document.addEventListener('keydown', handleKeydown);
  // Inicializar filtros después del montaje
  initializeFilters();

  // Inicializar altura del contenedor para virtualización
  if (shouldUseVirtualScrolling.value && tableContainer.value) {
    containerHeight.value = tableContainer.value.clientHeight;
  }
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown);

  // Limpiar timeouts
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }
  if (hoverTimeout) {
    clearTimeout(hoverTimeout);
  }

  // Limpiar todos los caches
  filterCache.clear();
  sortCache.clear();
  normalizeCache.clear();

  // Limpiar todos los estilos aplicados al body de forma eficiente
  const resetStyles = {
    overflow: '',
    position: '',
    top: '',
    width: ''
  };
  Object.assign(document.body.style, resetStyles);
});

// Función para manejar clicks en botones personalizados
function handleButtonClick(buttonId: string, rowData?: Record<string, any>) {
  emit('buttonClick', buttonId, rowData);
}

// Función para manejar acciones de fila
function handleRowAction(actionId: string, rowData: Record<string, any>) {
  emit('rowAction', actionId, rowData);
}

// Función para verificar si una acción debe estar deshabilitada
function isActionDisabled(action: RowAction, row: Record<string, any>): boolean {
  if (typeof action.disabled === 'function') {
    return action.disabled(row);
  }
  return action.disabled || false;
}

// Función para verificar si una acción debe estar oculta
function isActionHidden(action: RowAction, row: Record<string, any>): boolean {
  if (typeof action.hidden === 'function') {
    return action.hidden(row);
  }
  return action.hidden || false;
}

// Función para obtener las clases CSS del botón según la variante
function getButtonClasses(variant: string = 'primary', disabled: boolean = false, loading: boolean = false) {
  const baseClasses = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-lg font-medium tracking-tight transition-all duration-200 shadow-sm hover:shadow-md active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100';

  const variantClasses = {
    primary: 'bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white focus-visible:ring-2 focus-visible:ring-blue-400',
    secondary: 'bg-gray-100 hover:bg-gray-200 active:bg-gray-300 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:active:bg-gray-500 dark:text-gray-200 focus-visible:ring-2 focus-visible:ring-gray-400',
    success: 'bg-green-600 hover:bg-green-700 active:bg-green-800 text-white focus-visible:ring-2 focus-visible:ring-green-400',
    warning: 'bg-yellow-500 hover:bg-yellow-600 active:bg-yellow-700 text-white focus-visible:ring-2 focus-visible:ring-yellow-400',
    danger: 'bg-red-600 hover:bg-red-700 active:bg-red-800 text-white focus-visible:ring-2 focus-visible:ring-red-400'
  };

  return `${baseClasses} ${variantClasses[variant as keyof typeof variantClasses] || variantClasses.primary}`;
}
</script>

<template>
  <div :class="[
    'w-full font-sans antialiased transition-all duration-500 ease-out flex flex-col',
    isFullscreen
      ? 'fixed inset-0 z-50 bg-white dark:bg-gray-900 overflow-hidden'
      : 'relative'
  ]" :style="{
    transition: 'all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)',
    transform: isAnimatingFullscreen && !isFullscreen ? 'scale(0.98) translateY(10px)' : 'scale(1) translateY(0)',
    opacity: isAnimatingFullscreen && !isFullscreen ? '0.9' : '1',
    backdropFilter: isFullscreen ? 'blur(0px)' : 'blur(0px)'
  }">
    <!-- Overlay sutil durante la transición -->
    <transition enter-active-class="transition-opacity duration-300 ease-out" enter-from-class="opacity-0"
      enter-to-class="opacity-100" leave-active-class="transition-opacity duration-300 ease-in"
      leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="isAnimatingFullscreen" class="absolute inset-0 bg-black/5 dark:bg-black/10 pointer-events-none z-40"
        aria-hidden="true">
      </div>
    </transition>

    <!-- Header Fijo [BUSCADOR + BOTONES], sticky o fixed superior -->
    <div :class="[
      'flex flex-col md:flex-row md:items-center gap-4 justify-between bg-gradient-to-br from-white via-gray-50/50 to-white dark:from-sidebar dark:via-sidebar/95 dark:to-sidebar px-6 py-5 border-x border-t border-gray-200/80 dark:border-sidebar-border/50 backdrop-blur-xl shadow-sm transition-all duration-500 ease-out z-20',
      isFullscreen ? 'rounded-none' : 'rounded-t-2xl'
    ]" :style="{
      transition: 'all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)',
      transform: isAnimatingFullscreen && isFullscreen ? 'translateY(-15px) scale(1.02)' : 'translateY(0) scale(1)',
      opacity: isAnimatingFullscreen && isFullscreen ? '0.95' : '1',
      boxShadow: isFullscreen ? '0 4px 20px rgba(0, 0, 0, 0.1)' : '0 2px 10px rgba(0, 0, 0, 0.05)',
      top: 0,
      position: 'sticky'
    }">
      <!-- Indicador de pantalla completa -->
      <transition enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 scale-95 translate-y-2" enter-to-class="opacity-100 scale-100 translate-y-0"
        leave-active-class="transition-all duration-200 ease-in" leave-from-class="opacity-100 scale-100 translate-y-0"
        leave-to-class="opacity-0 scale-95 translate-y-2">
        <div v-if="isFullscreen"
          class="absolute top-2 right-2 flex items-center gap-2 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-3 py-1.5 rounded-full text-xs font-medium shadow-sm border border-blue-200 dark:border-blue-700/50">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
          </svg>
          Pantalla completa
        </div>
      </transition>

      <!-- Buscador global mejorado -->
      <div class="w-full md:w-auto flex-1 md:max-w-md">
        <div class="relative group">

          <!-- Icono con mejor contraste -->
          <div
            class="absolute left-4 top-1/2 -translate-y-1/2 z-10 group-focus-within:scale-110 transition-all duration-300 pointer-events-none text-gray-700 dark:text-gray-300 group-focus-within:text-blue-600 dark:group-focus-within:text-blue-400"
            aria-hidden="true">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2.5" />
              <line x1="18" y1="18" x2="15.5" y2="15.5" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" />
            </svg>
          </div>
          <input v-model="search" type="text" @focus="isSearchFocused = true" @blur="isSearchFocused = false"
            @input="handleSearch(search)"
            class="w-full border border-gray-200/80 dark:border-sidebar-border/60 rounded-xl pl-12 pr-11 py-3.5 text-sm bg-white/90 dark:bg-sidebar/60 backdrop-blur-md shadow-sm hover:shadow-md focus:shadow-xl focus:ring-2 focus:ring-blue-500/40 dark:focus:ring-blue-400/30 focus:border-blue-400 dark:focus:border-blue-500 transition-all duration-300 !text-gray-900 dark:!text-white placeholder-gray-400 dark:placeholder-gray-500 outline-none font-medium tracking-tight leading-relaxed"
            :placeholder="props.searchPlaceholder || 'Buscar en todos los campos...'" data-expert="datatable-search"
            aria-label="Buscar en tabla" autocomplete="off" spellcheck="false" />
          <transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-75"
            enter-to-class="opacity-100 scale-100" leave-active-class="transition-all duration-150"
            leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-75">
            <button v-if="search" @click="search = ''" type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2 bg-gray-100 hover:bg-red-50 dark:bg-sidebar-border/50 dark:hover:bg-red-900/40 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 rounded-lg w-7 h-7 flex items-center justify-center transition-all duration-200 hover:scale-110 hover:rotate-90 active:scale-95 shadow-sm"
              aria-label="Limpiar búsqueda" tabindex="0">
              <!-- También color con clases Tailwind para control completo -->
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 20 20"
                stroke-linecap="round">
                <line x1="6" y1="6" x2="14" y2="14" />
                <line x1="14" y1="6" x2="6" y2="14" />
              </svg>
            </button>
          </transition>
        </div>
      </div>

      <!-- Filtros avanzados y botón de pantalla completa -->
      <div class="flex flex-wrap gap-2.5 items-center">
        <template v-for="column in props.columns" :key="'filter-'+column.key">
          <template v-if="column.filterType === 'text'">
            <div class="relative group">
              <div
                class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 group-focus-within:text-blue-500 transition-colors duration-200 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" d="M3 6h18M3 12h12M3 18h6" />
                </svg>
              </div>
              <input v-model="advancedFilters[column.key]" :placeholder="'Filtrar ' + column.label"
                class="w-44 pl-10 pr-3 !text-gray-900 dark:!text-white border border-gray-200/80 dark:border-sidebar-border/60 rounded-xl py-2.5 text-sm bg-white/90 dark:bg-sidebar/60 backdrop-blur-md shadow-sm hover:shadow-md focus:shadow-lg focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 transition-all duration-200 outline-none font-medium tracking-tight leading-relaxed"
                :aria-label="'Filtrar ' + column.label" spellcheck="false" />
            </div>
          </template>
          <template v-else-if="column.filterType === 'select' && column.options">
            <div class="relative group">
              <div
                class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 group-focus-within:text-blue-500 transition-colors duration-200 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
              </div>
              <select v-model="advancedFilters[column.key]" :aria-label="'Filtrar ' + column.label"
                class="pl-10 pr-8 rounded-xl border border-gray-200/80 dark:border-sidebar-border/60 bg-white/90 dark:bg-sidebar/60 backdrop-blur-md text-sm py-2.5 shadow-sm hover:shadow-md focus:shadow-lg focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 transition-all duration-200 outline-none cursor-pointer font-medium appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236b7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E')] bg-[length:1.25rem] bg-[right_0.5rem_center] bg-no-repeat tracking-tight leading-relaxed">
                <option value="">Todos</option>
                <option v-for="opt in column.options" :key="column.key + '-' + opt" :value="opt">
                  {{ opt }}
                </option>
              </select>
            </div>
          </template>
        </template>

        <!-- Botón limpiar filtros mejorado -->
        <transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-75"
          enter-to-class="opacity-100 scale-100" leave-active-class="transition-all duration-150"
          leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-75">
          <button v-if="hasActiveFilters" @click="clearAllFilters"
            class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-50 via-blue-100/90 to-blue-50 dark:from-blue-900/40 dark:via-blue-800/30 dark:to-blue-900/40 hover:from-blue-100 hover:via-blue-200 hover:to-blue-100 dark:hover:from-blue-900/50 dark:hover:via-blue-800/40 dark:hover:to-blue-900/50 px-4 py-2.5 rounded-xl text-blue-700 dark:text-blue-300 text-sm font-semibold border border-blue-200/80 dark:border-blue-700/50 shadow-sm hover:shadow-md transition-all duration-200 hover:scale-105 active:scale-95 tracking-tight"
            type="button" aria-label="Limpiar filtros avanzados">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
              stroke-linecap="round">
              <path d="M6 18L18 6M6 6l12 12" />
            </svg>
            Limpiar filtros
          </button>
        </transition>

        <!-- Botones personalizados -->
        <template v-if="props.customButtons && props.customButtons.length > 0">
          <button v-for="button in props.customButtons" :key="button.id" @click="handleButtonClick(button.id)"
            :disabled="button.disabled || button.loading"
            :class="getButtonClasses(button.variant, button.disabled, button.loading)" type="button"
            :aria-label="button.label">
            <!-- Icono del botón -->
            <svg v-if="button.icon && !button.loading" class="w-4 h-4" fill="none" stroke="currentColor"
              stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round">
              <path v-if="button.icon === 'add'" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              <path v-else-if="button.icon === 'edit'" stroke-linejoin="round"
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              <path v-else-if="button.icon === 'delete'" stroke-linejoin="round"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              <path v-else-if="button.icon === 'download'" stroke-linejoin="round"
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              <path v-else-if="button.icon === 'refresh'" stroke-linejoin="round"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              <path v-else stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <!-- Spinner de carga -->
            <svg v-if="button.loading" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2.5"
              viewBox="0 0 24 24" stroke-linecap="round">
              <path stroke-linejoin="round"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ button.label }}</span>
          </button>
        </template>

        <!-- Botón de pantalla completa -->
        <button @click="toggleFullscreen" :disabled="isAnimatingFullscreen"
          class="inline-flex items-center gap-2 bg-gradient-to-r from-gray-50 via-gray-100/90 to-gray-50 dark:from-gray-800/40 dark:via-gray-700/30 dark:to-gray-800/40 hover:from-gray-100 hover:via-gray-200 hover:to-gray-100 dark:hover:from-gray-700/50 dark:hover:via-gray-600/40 dark:hover:to-gray-700/50 px-4 py-2.5 rounded-xl text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white text-sm font-semibold border border-gray-200/80 dark:border-gray-600/50 shadow-sm hover:shadow-md transition-all duration-200 hover:scale-105 active:scale-95 tracking-tight disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
          type="button" :aria-label="isFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'">
          <svg v-if="!isFullscreen" :class="[
            'w-4 h-4 transition-all duration-200',
            isAnimatingFullscreen ? 'animate-spin' : ''
          ]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round">
            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3" />
          </svg>
          <svg v-else :class="[
            'w-4 h-4 transition-all duration-200',
            isAnimatingFullscreen ? 'animate-pulse' : ''
          ]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round">
            <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3" />
          </svg>
          <span class="hidden sm:inline">{{ isFullscreen ? 'Salir' : 'Pantalla completa' }}</span>
        </button>
      </div>
    </div>

    <!-- CONTENEDOR CENTRAL CON SCROLL Y TABLA -->
    <div :class="[
      'overflow-x-auto border-x border-b border-gray-200/80 dark:border-sidebar-border/50 bg-white dark:bg-sidebar shadow-xl transition-all duration-500 ease-out flex-1',
      isFullscreen ? 'rounded-none' : 'rounded-b-2xl',
      shouldUseVirtualScrolling ? 'overflow-y-auto' : ''
    ]" data-expert="table-container" :style="{
      transition: 'all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)',
      transform: isAnimatingFullscreen && isFullscreen ? 'translateY(15px) scale(1.01)' : 'translateY(0) scale(1)',
      opacity: isAnimatingFullscreen && isFullscreen ? '0.95' : '1',
      boxShadow: isFullscreen ? '0 8px 32px rgba(0, 0, 0, 0.12)' : '0 4px 16px rgba(0, 0, 0, 0.08)',
      maxHeight: isFullscreen ? 'calc(100vh - 168px)' : 'min(60vh,600px)',
      minHeight: '180px',
      position: 'relative'
    }" @scroll="shouldUseVirtualScrolling ? handleVirtualScroll : undefined" ref="tableContainer">
      <!-- Contenido de tabla con animación y OVERFLOW SCROLL Y PADDING INFERIOR PARA PAGINACION FIJA -->
      <transition enter-active-class="transition-all duration-400 ease-out"
        enter-from-class="opacity-0 transform translate-y-4 scale-95 blur-sm"
        enter-to-class="opacity-100 transform translate-y-0 scale-100 blur-0"
        leave-active-class="transition-all duration-300 ease-in"
        leave-from-class="opacity-100 transform translate-y-0 scale-100 blur-0"
        leave-to-class="opacity-0 transform -translate-y-4 scale-105 blur-sm" mode="out-in">
        <div v-if="showTableContent" key="table-content" :style="{
          overflowY: shouldUseVirtualScrolling ? 'hidden' : 'auto',
          maxHeight: isFullscreen ? 'calc(100vh - 168px)' : 'min(60vh,600px)',
          paddingBottom: shouldUseVirtualScrolling ? '0px' : '110px',
          position: shouldUseVirtualScrolling ? 'relative' : 'static'
        }" class="relative overflow-x-auto">
          <!-- Efecto shimmer durante la transición -->
          <div v-if="isPageChanging"
            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-pulse z-10 pointer-events-none">
          </div>

          <!-- Indicador de carga elegante -->
          <div v-if="isPageChanging" class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-20">
            <div
              class="flex items-center gap-2 bg-white/90 dark:bg-sidebar/90 backdrop-blur-md rounded-full px-4 py-2 shadow-lg border border-gray-200/50 dark:border-sidebar-border/50">
              <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Cargando...</span>
            </div>
          </div>
          <table class="min-w-full text-sm leading-relaxed select-text font-feature-settings-antialiased"
            data-expert="datatable-root">
            <thead
              class="bg-gradient-to-r from-gray-50 via-gray-100/90 to-gray-50 dark:from-sidebar-border/40 dark:via-sidebar-border/30 dark:to-sidebar-border/40 backdrop-blur-sm border-b border-gray-200 dark:border-sidebar-border/50"
              data-expert="datatable-thead">
              <tr>
                <th v-for="column in props.columns" :key="column.key" @click="setSort(column.key)"
                  @mouseenter="handleColumnHover(column.key)" @mouseleave="handleColumnHover(null)" :class="[
                    'px-6 py-4 font-bold text-gray-700 dark:text-gray-200 select-none transition-all duration-200 hover:bg-blue-50/80 dark:hover:bg-blue-900/20 whitespace-nowrap tracking-tight group relative first:rounded-tl-xl last:rounded-tr-xl',
                    column.align === 'center' ? 'text-center' : column.align === 'right' ? 'text-right' : 'text-left',
                    column.sortable === false ? 'cursor-default' : 'cursor-pointer hover:scale-[1.02]',
                    hoveredColumn === column.key ? 'bg-blue-50/60 dark:bg-blue-900/15' : ''
                  ]" :style="column.width ? `width: ${column.width}` : ''"
                  :aria-sort="sortKey === column.key ? (sortAsc ? 'ascending' : 'descending') : 'none'" scope="col"
                  data-expert="datatable-th">
                  <span class="flex items-center gap-2.5">
                    {{ column.label }}
                    <span v-if="column.sortable !== false"
                      class="text-gray-400 dark:text-gray-500 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-all duration-200">
                      <svg v-if="sortKey === column.key && sortAsc"
                        :class="['w-4 h-4 inline-block', isAnimating ? 'animate-pulse' : '']" fill="none"
                        stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                      </svg>
                      <svg v-else-if="sortKey === column.key && !sortAsc"
                        :class="['w-4 h-4 inline-block', isAnimating ? 'animate-pulse' : '']" fill="none"
                        stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                      </svg>
                      <svg v-else
                        class="w-4 h-4 inline-block opacity-0 group-hover:opacity-60 transition-opacity duration-200"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                          d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                      </svg>
                    </span>
                  </span>
                </th>
                <!-- Columna de acciones si hay acciones definidas -->
                <th v-if="props.rowActions && props.rowActions.length > 0"
                  class="px-6 py-4 font-bold text-gray-700 dark:text-gray-200 select-none text-center whitespace-nowrap tracking-tight last:rounded-tr-xl"
                  scope="col" data-expert="datatable-th-actions">
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody data-expert="datatable-tbody"
              :style="shouldUseVirtualScrolling ? { height: `${virtualContentHeight}px` } : {}">
              <!-- Spacer para virtualización -->
              <tr v-if="shouldUseVirtualScrolling && virtualRows.length > 0"
                :style="{ height: `${virtualRows[0]._virtualOffset}px` }" aria-hidden="true">
                <td :colspan="props.columns.length + (props.rowActions && props.rowActions.length > 0 ? 1 : 0)"></td>
              </tr>

              <tr v-for="(row, idx) in pagedRows"
                :key="shouldUseVirtualScrolling ? row._virtualIndex : (idx + '-' + page)"
                @mouseenter="handleRowHover(shouldUseVirtualScrolling ? row._virtualIndex : idx)"
                @mouseleave="handleRowHover(null)" :class="[
                  'border-t border-gray-100 dark:border-sidebar-border/30 transition-all duration-150 group',
                  hoveredRow === (shouldUseVirtualScrolling ? row._virtualIndex : idx) ? 'bg-gradient-to-r from-blue-50/60 via-blue-50/40 to-transparent dark:from-blue-900/15 dark:via-blue-900/10 dark:to-transparent shadow-sm' : 'hover:bg-gradient-to-r hover:from-blue-50/60 hover:via-blue-50/40 hover:to-transparent dark:hover:from-blue-900/15 dark:hover:via-blue-900/10 dark:hover:to-transparent'
                ]" data-expert="datatable-row" :style="shouldUseVirtualScrolling ? { height: `${rowHeight}px` } : {}">
                <td v-for="column in props.columns" :key="column.key" :class="[
                  'px-6 py-4 text-gray-700 dark:text-gray-200 max-w-xs truncate group-hover:text-gray-900 dark:group-hover:text-white transition-colors duration-150 font-medium tracking-tight leading-relaxed',
                  column.align === 'center' ? 'text-center' : column.align === 'right' ? 'text-right' : 'text-left'
                ]" data-expert="datatable-cell">
                  {{ formatValue((row as Record<string, any>)[column.key], column.format) }}
                </td>
                <!-- Celda de acciones si hay acciones definidas -->
                <td v-if="props.rowActions && props.rowActions.length > 0" class="px-6 py-4 text-center"
                  data-expert="datatable-cell-actions">
                  <div class="flex items-center justify-center gap-2">
                    <button v-for="action in props.rowActions" :key="action.id" v-show="!isActionHidden(action, row)"
                      @click="handleRowAction(action.id, row)" :disabled="isActionDisabled(action, row)"
                      :class="getButtonClasses(action.variant, isActionDisabled(action, row), false)"
                      class="text-xs px-3 py-1.5" type="button" :aria-label="action.label">
                      <!-- Icono de la acción -->
                      <svg v-if="action.icon" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24" stroke-linecap="round">
                        <path v-if="action.icon === 'edit'" stroke-linejoin="round"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        <path v-else-if="action.icon === 'delete'" stroke-linejoin="round"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        <path v-else-if="action.icon === 'view'" stroke-linejoin="round"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <path v-else-if="action.icon === 'download'" stroke-linejoin="round"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        <path v-else stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                      </svg>
                      <span class="hidden sm:inline">{{ action.label }}</span>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="pagedRows.length === 0">
                <td :colspan="props.columns.length + (props.rowActions && props.rowActions.length > 0 ? 1 : 0)"
                  class="text-center py-16" data-expert="datatable-empty">
                  <div class="flex flex-col items-center gap-4">
                    <div class="relative">
                      <svg class="w-20 h-20 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor"
                        stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      <div class="absolute inset-0 animate-ping opacity-20">
                        <svg class="w-20 h-20 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor"
                          stroke-width="1.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                      </div>
                    </div>
                    <div class="space-y-1">
                      <p class="text-gray-500 dark:text-gray-400 text-base font-semibold tracking-tight">{{
                        props.emptyMessage
                        || 'No se encontraron resultados' }}</p>
                      <p class="text-gray-400 dark:text-gray-600 text-sm tracking-tight leading-relaxed">Intenta ajustar
                        los
                        filtros de búsqueda</p>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </transition>
    </div>

    <!-- CONTROLES DE PAGINACIÓN FIJOS ABAJO EN CARD BLANCO -->
    <div v-if="!shouldUseVirtualScrolling && (totalPages > 1 || totalRows > 0)" :class="[
      'z-30 mx-4 mb-4',
      isFullscreen ? 'fixed bottom-4 left-4 right-4' : 'sticky bottom-4'
    ]">
      <!-- Card blanco para paginación -->
      <div
        class="bg-white dark:bg-sidebar border border-gray-200/80 dark:border-sidebar-border/50 rounded-2xl shadow-lg backdrop-blur-sm">
        <div class="flex flex-col md:flex-row items-center justify-between p-6 gap-4 select-none">
          <!-- Información y selector de elementos por página -->
          <div class="flex flex-col sm:flex-row items-center gap-4">
            <div class="text-sm text-gray-600 dark:text-gray-400 font-medium tracking-tight leading-relaxed">
              Mostrando
              <span class="font-bold text-blue-600 dark:text-blue-400 tracking-tight">
                {{ totalRows === 0 ? 0 : (perPage * (page - 1)) + 1 }}
              </span>
              -
              <span class="font-bold text-blue-600 dark:text-blue-400 tracking-tight">
                {{ Math.min(page * perPage, totalRows) }}
              </span>
              de
              <span class="font-bold text-gray-900 dark:text-white tracking-tight">{{ totalRows }}</span>
              registros
            </div>

            <!-- Selector de elementos por página -->
            <div class="flex items-center gap-2">
              <label for="perPageSelect"
                class="text-sm text-gray-600 dark:text-gray-400 font-medium whitespace-nowrap tracking-tight">
                Mostrar:
              </label>
              <select id="perPageSelect" v-model="perPage"
                class="px-3 py-1.5 rounded-lg border border-gray-200/80 dark:border-sidebar-border/60 bg-white/90 dark:bg-sidebar/60 backdrop-blur-md text-sm shadow-sm hover:shadow-md focus:shadow-lg focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 transition-all duration-200 outline-none cursor-pointer font-medium appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236b7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E')] bg-[length:1rem] bg-[right_0.5rem_center] bg-no-repeat pr-8 tracking-tight"
                aria-label="Elementos por página">
                <option v-for="option in perPageOptions" :key="option" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>
          </div>

          <!-- Navegación de páginas (solo si hay más de una página) -->
          <nav v-if="totalPages > 1" class="flex items-center gap-2" aria-label="Paginación navegación">
            <!-- Ir al inicio -->
            <button @click="gotoPage(1)" :disabled="page === 1"
              class="px-3 py-2.5 rounded-xl border text-sm font-semibold bg-white dark:bg-sidebar hover:bg-gradient-to-br hover:from-gray-50 hover:to-white dark:hover:from-sidebar-border/40 dark:hover:to-sidebar border-gray-200 dark:border-sidebar-border/40 text-gray-700 dark:text-gray-300 disabled:bg-gray-50 dark:disabled:bg-sidebar-border/20 disabled:text-gray-300 dark:disabled:text-gray-600 disabled:cursor-not-allowed shadow-sm hover:shadow-md transition-all duration-200 hover:scale-110 active:scale-95 disabled:scale-100 tracking-tight"
              aria-label="Primera página">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                stroke-linecap="round">
                <path d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
              </svg>
            </button>

            <!-- Página anterior -->
            <button @click="gotoPage(page - 1)" :disabled="page === 1"
              class="px-4 py-2.5 rounded-xl border text-sm font-semibold bg-white dark:bg-sidebar hover:bg-gradient-to-br hover:from-gray-50 hover:to-white dark:hover:from-sidebar-border/40 dark:hover:to-sidebar border-gray-200 dark:border-sidebar-border/40 text-gray-700 dark:text-gray-300 disabled:bg-gray-50 dark:disabled:bg-sidebar-border/20 disabled:text-gray-300 dark:disabled:text-gray-600 disabled:cursor-not-allowed shadow-sm hover:shadow-md transition-all duration-200 hover:scale-105 active:scale-95 disabled:scale-100 tracking-tight"
              aria-label="Página anterior">
              <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                  stroke-linecap="round">
                  <path d="M15 19l-7-7 7-7" />
                </svg>
                Anterior
              </span>
            </button>

            <!-- Números de página -->
            <template v-for="p in Math.min(totalPages, 7)" :key="'page-'+p">
              <button v-if="totalPages <= 7 || Math.abs(page - p) <= 2 || p === 1 || p === totalPages"
                @click="gotoPage(p)" :class="[
                  'px-4 py-2.5 text-sm font-bold rounded-xl border shadow-sm transition-all duration-200 tracking-tight',
                  page === p
                    ? 'bg-gradient-to-br from-blue-600 via-blue-600 to-blue-700 text-white border-blue-600 dark:border-blue-500 scale-110 shadow-lg shadow-blue-500/30 hover:shadow-xl'
                    : 'bg-white dark:bg-sidebar hover:bg-gradient-to-br hover:from-gray-50 hover:to-white dark:hover:from-sidebar-border/40 dark:hover:to-sidebar border-gray-200 dark:border-sidebar-border/40 text-gray-700 dark:text-gray-300 hover:scale-105 active:scale-95'
                ]" :aria-current="page === p ? 'page' : undefined">
                {{ p }}
              </button>
              <span v-else-if="(p === 2 && page > 4) || (p === totalPages - 1 && page < totalPages - 3)"
                class="px-2 py-2 text-gray-400 dark:text-gray-600 select-none font-bold text-base tracking-tight"
                aria-hidden="true">
                ⋯
              </span>
            </template>

            <!-- Página siguiente -->
            <button @click="gotoPage(page + 1)" :disabled="page === totalPages"
              class="px-4 py-2.5 rounded-xl border text-sm font-semibold bg-white dark:bg-sidebar hover:bg-gradient-to-br hover:from-gray-50 hover:to-white dark:hover:from-sidebar-border/40 dark:hover:to-sidebar border-gray-200 dark:border-sidebar-border/40 text-gray-700 dark:text-gray-300 disabled:bg-gray-50 dark:disabled:bg-sidebar-border/20 disabled:text-gray-300 dark:disabled:text-gray-600 disabled:cursor-not-allowed shadow-sm hover:shadow-md transition-all duration-200 hover:scale-105 active:scale-95 disabled:scale-100 tracking-tight"
              aria-label="Página siguiente">
              <span class="flex items-center gap-1.5">
                Siguiente
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                  stroke-linecap="round">
                  <path d="M9 5l7 7-7 7" />
                </svg>
              </span>
            </button>

            <!-- Ir al final -->
            <button @click="gotoPage(totalPages)" :disabled="page === totalPages"
              class="px-3 py-2.5 rounded-xl border text-sm font-semibold bg-white dark:bg-sidebar hover:bg-gradient-to-br hover:from-gray-50 hover:to-white dark:hover:from-sidebar-border/40 dark:hover:to-sidebar border-gray-200 dark:border-sidebar-border/40 text-gray-700 dark:text-gray-300 disabled:bg-gray-50 dark:disabled:bg-sidebar-border/20 disabled:text-gray-300 dark:disabled:text-gray-600 disabled:cursor-not-allowed shadow-sm hover:shadow-md transition-all duration-200 hover:scale-110 active:scale-95 disabled:scale-100 tracking-tight"
              aria-label="Última página">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                stroke-linecap="round">
                <path d="M13 5l7 7-7 7M5 5l7 7-7 7" />
              </svg>
            </button>
          </nav>
        </div>
      </div>
    </div>
  </div>
</template>
