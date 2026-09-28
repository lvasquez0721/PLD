<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Titulo from '@/components/ui/Titulo.vue'
import Toast from '@/components/ui/alert/Toast.vue'
import { type BreadcrumbItem } from '@/types'
import { UserRound } from 'lucide-vue-next'
import FadeIn from '@/components/ui/animation/fadeIn.vue'
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = defineProps<{
    clientes: {
        data: any[],
        current_page: number,
        last_page: number,
        per_page: number,
        total: number,
        from: number,
        to: number,
        links: any[]
    },
    filters?: {
        search?: string,
        tipo?: string,
        estatus?: string,
        per_page?: string | number,
        category?: string | string[]
    },
    toast?: { type: 'success' | 'error' | 'warning', message: string }
}>()

const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref<'success' | 'error' | 'warning'>('success')

watch(() => props.toast, (newToast) => {
    if (newToast) {
        toastMessage.value = newToast.message
        toastType.value = newToast.type
        showToast.value = true
    }
}, { immediate: true })

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clientes', href: '/clientes' },
]

const busqueda = ref(props.filters?.search || '')
const filtroTipoPersona = ref(props.filters?.tipo || 'todos')
const filtroEstatus = ref(props.filters?.estatus || 'todos')

const pldCategoryOptions = [
    { value: 'sin-coincidencia', label: 'Sin coincidencia en listas' },
    { value: 'coincidencia-revision', label: 'Coincidencia, necesita revisión' },
    { value: 'ppe-revision', label: 'PPE, necesita revisión' },
    { value: 'autorizada-listas', label: 'Autorizada que aparece en listas' },
    { value: 'fuera-categoria', label: 'Fuera de categoría Tláloc' },
    { value: 'listas-internas', label: 'Listas internas (oficios CNSF)' }
];

// Initialize filtroCategoriaPLD based on incoming props.filters?.category
const initialCategories = props.filters?.category;
const defaultFiltroCategoriaPLD = ref<string[]>([]);
if (initialCategories === 'todos') {
    defaultFiltroCategoriaPLD.value = pldCategoryOptions.map(opt => opt.value); // Select all if 'todos' was initially passed
} else if (Array.isArray(initialCategories)) {
    defaultFiltroCategoriaPLD.value = initialCategories;
} else if (typeof initialCategories === 'string' && initialCategories !== '') {
    defaultFiltroCategoriaPLD.value = [initialCategories];
}
const filtroCategoriaPLD = ref<string[]>(defaultFiltroCategoriaPLD.value);

const listaScrollRef = ref<HTMLElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)
const isSearching = ref(false)

const itemsPerPage = ref(Number(props.filters?.per_page) || 10)
const itemsPerPageOptions = [5, 10, 20, 50, 100]

function getCategoriesToSend(): string[] | string | undefined {
    if (filtroCategoriaPLD.value.length === pldCategoryOptions.length) {
        return 'todos'
    }
    if (filtroCategoriaPLD.value.length > 0) {
        return [...filtroCategoriaPLD.value]
    }
    // 0 seleccionadas = sin filtro (equivale a "todas") para no vaciar la tabla por error.
    return undefined
}

function buildParams(page?: number): Record<string, any> {
    const params: Record<string, any> = {
        search: busqueda.value.trim(),
        tipo: filtroTipoPersona.value,
        estatus: filtroEstatus.value,
        per_page: itemsPerPage.value,
        category: getCategoriesToSend(),
    }
    if (typeof page === 'number') params.page = page
    // Limpiar vacíos para URLs cortas y paginación predecible.
    if (!params.search) delete params.search
    if (params.tipo === 'todos') delete params.tipo
    if (params.estatus === 'todos') delete params.estatus
    if (params.category === undefined) delete params.category
    return params
}

function fetchClientes(page?: number, replace = true) {
    isSearching.value = true
    router.get('/clientes', buildParams(page), {
        preserveState: true,
        preserveScroll: true,
        replace,
        onFinish: () => { isSearching.value = false },
    })
}

function scrollListaToTop() {
    listaScrollRef.value?.scrollTo({ top: 0, behavior: 'smooth' })
}

// Watchers para búsqueda y filtros con debounce manual para la búsqueda
let searchTimeout: any = null
watch(busqueda, () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    isSearching.value = true
    searchTimeout = setTimeout(() => fetchClientes(1), 400)
})

watch([filtroTipoPersona, filtroEstatus, itemsPerPage, filtroCategoriaPLD], () => {
    fetchClientes(1)
}, { deep: true });

const clientesFiltrados = computed(() => props.clientes.data)

const totalPages = computed(() => props.clientes.last_page)

const totalResultados = computed(() => props.clientes.total)

const currentPage = computed({
    get: () => props.clientes.current_page,
    set: (val) => goToPage(val)
})

const rangoInicio = computed(() => props.clientes.from || 0)

const rangoFin = computed(() => props.clientes.to || 0)

const categoryDisplayNames: { [key: string]: string } = {
    'todos': 'Todas',
    'sin-coincidencia': 'Sin coincidencia en listas',
    'coincidencia-revision': 'Coincidencia, necesita revisión',
    'ppe-revision': 'PPE, necesita revisión',
    'autorizada-listas': 'Autorizada que aparece en listas',
    'fuera-categoria': 'Fuera de categoría Tláloc',
    'listas-internas': 'Listas internas (oficios CNSF)'
};

const displayCategoryFilters = computed(() => {
    if (filtroCategoriaPLD.value.length === pldCategoryOptions.length) {
        return 'Todas';
    }
    if (filtroCategoriaPLD.value.length === 0) {
        return ''; // No categories selected, so don't display anything
    }
    return filtroCategoriaPLD.value.map(cat => categoryDisplayNames[cat]).join(', ');
});

function nextPage() {
    if (currentPage.value < totalPages.value) {
        goToPage(currentPage.value + 1)
    }
}

function prevPage() {
    if (currentPage.value > 1) {
        goToPage(currentPage.value - 1)
    }
}

function goToPage(page: number) {
    const safePage = Math.min(Math.max(1, Math.floor(page) || 1), Math.max(1, totalPages.value))
    fetchClientes(safePage, false)
    scrollListaToTop()
}

function clearSearch() {
    busqueda.value = ''
    searchInputRef.value?.focus()
}

function clearAllFilters() {
    busqueda.value = ''
    filtroTipoPersona.value = 'todos'
    filtroEstatus.value = 'todos'
    filtroCategoriaPLD.value = pldCategoryOptions.map(opt => opt.value)
    itemsPerPage.value = 10
}

function removeCategory(cat: string) {
    filtroCategoriaPLD.value = filtroCategoriaPLD.value.filter(c => c !== cat)
}

const hasActiveFilters = computed(() =>
    busqueda.value.trim() !== '' ||
    filtroTipoPersona.value !== 'todos' ||
    filtroEstatus.value !== 'todos' ||
    (filtroCategoriaPLD.value.length !== pldCategoryOptions.length && filtroCategoriaPLD.value.length !== 0)
)

// Redirigir a la ruta de detalle de cliente
function irADetalleCliente(cliente: any) {
    router.get(`/clientes/ver-detalles/${cliente.IDCliente}`);
}

// No longer needed: Modal visibility or clienteSeleccionado

function getCategoryTags(cliente: any) {
    const tags = [];

    if (cliente.coincidencias) {
        tags.push({ color: 'bg-orange-500', tooltip: 'Coincidencia, necesita revisión' });
    }
    if (cliente.esPPE) {
        tags.push({ color: 'bg-indigo-400', tooltip: 'PPE, necesita revisión' });
    }
    if (cliente.autorizadoApareceEnListas) {
        tags.push({ color: 'bg-yellow-300', tooltip: 'Autorizada que aparece en listas' });
    }
    if (cliente.fueraDeCategoria) {
        tags.push({ color: 'bg-purple-500', tooltip: 'Fuera de categoría Tláloc' });
    }
    if (cliente.CNSF) {
        tags.push({ color: 'bg-rose-400', tooltip: 'Listas internas (oficios CNSF)' });
    }
    if (tags.length === 0) {
        tags.push({ color: 'bg-white border border-gray-300', tooltip: 'Sin coincidencia en listas', type: 'text' });
    }
    return tags;
}

// PLD Category Dropdown Logic
const showCategoryDropdown = ref(false);
const categoryDropdownRef = ref<HTMLElement | null>(null); // Ref for the dropdown container
const dropdownButtonRef = ref<HTMLElement | null>(null); // Ref for the dropdown button
const dropdownPanelRef = ref<HTMLElement | null>(null); // Ref for the dropdown panel
const dropdownPosition = ref({ top: 0, left: 0, width: 0 });

function updateDropdownPosition() {
    if (dropdownButtonRef.value) {
        const rect = dropdownButtonRef.value.getBoundingClientRect();
        dropdownPosition.value = {
            top: rect.bottom + 8, // 8px = mt-2 equivalent, fixed positioning is relative to viewport
            left: rect.left,
            width: rect.width
        };
    }
}

async function toggleCategoryDropdown() {
    showCategoryDropdown.value = !showCategoryDropdown.value;
    if (showCategoryDropdown.value) {
        await nextTick();
        updateDropdownPosition();
    }
}

// Update position on scroll/resize when dropdown is open
function handleScrollOrResize() {
    if (showCategoryDropdown.value) {
        updateDropdownPosition();
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleKeydown);
    window.addEventListener('scroll', handleScrollOrResize, true);
    window.addEventListener('resize', handleScrollOrResize);
    // Si viene sin categoría (primera carga), mostrar todas seleccionadas.
    if (!props.filters?.category) {
        filtroCategoriaPLD.value = pldCategoryOptions.map(opt => opt.value)
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('scroll', handleScrollOrResize, true);
    window.removeEventListener('resize', handleScrollOrResize);
    if (searchTimeout) clearTimeout(searchTimeout)
});

const selectAllCategoriesComputed = computed<boolean>({
    get: () => {
        return filtroCategoriaPLD.value.length === pldCategoryOptions.length;
    },
    set: (value: boolean) => {
        if (value) {
            filtroCategoriaPLD.value = pldCategoryOptions.map(opt => opt.value);
        } else {
            filtroCategoriaPLD.value = [];
        }
    }
});

const selectedCategoryCount = computed<number>(() => {
    return filtroCategoriaPLD.value.length;
});

// Global click handler to close dropdown
function handleDocumentClick(event: MouseEvent) {
    // Check if the click occurred outside the dropdown button AND outside the dropdown panel
    if (showCategoryDropdown.value &&
        dropdownButtonRef.value &&
        dropdownPanelRef.value &&
        !dropdownButtonRef.value.contains(event.target as Node) &&
        !dropdownPanelRef.value.contains(event.target as Node)
    ) {
        showCategoryDropdown.value = false;
    }
}

function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && showCategoryDropdown.value) {
        showCategoryDropdown.value = false;
    }
    // Ctrl/Cmd + K enfoca el buscador.
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        searchInputRef.value?.focus()
    }
}

function descargarCSV() {
    const params = new URLSearchParams();
    const built = buildParams()
    if (built.search) params.append('search', built.search);
    if (built.tipo) params.append('tipo', built.tipo);
    if (built.estatus) params.append('estatus', built.estatus);
    const categoriesToSend = getCategoriesToSend()
    if (categoriesToSend) {
        if (Array.isArray(categoriesToSend)) {
            categoriesToSend.forEach(cat => params.append('category[]', cat));
        } else {
            params.append('category', categoriesToSend);
        }
    }

    window.location.href = `/clientes/exportar?${params.toString()}`;
}

</script>

<template>

    <Head title="Clientes" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
            <div class="relative">

            <div
                class="fixed inset-0 -z-50 opacity-15 [background-image:url('data:image/svg+xml,%3Csvg%20width%3D%2240%22%20height%3D%2240%22%20viewBox%3D%220%200%2040%2040%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2Fsvg%22%3E%3Cg%20fill%3D%22%23a0aec0%22%20fill-opacity%3D%220.1%22%20fill-rule%3D%22evenodd%22%3E%3Cpath%20d%3D%22M0%2040L40%200H20L0%2020M40%2040V20L20%2040%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E')]" />
            <div
                class="fixed -top-1/2 left-1/2 -z-40 h-[1200px] w-[1200px] -translate-x-1/2 rounded-full bg-gradient-to-br from-blue-50 via-blue-50/0 to-blue-100/0 opacity-60 dark:from-blue-950/20" />


            <!-- Leyenda de colores PLD con tarjetas sensoriales -->
            <div
                class="mt-2 overflow-hidden rounded-2xl border border-gray-200/80 bg-white/60 p-4 shadow-lg shadow-gray-200/40 backdrop-blur-lg transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-gray-300/50 dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-2xl dark:shadow-black/20">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h4 class="flex items-center gap-2.5 text-base font-semibold text-gray-800 dark:text-white">
                            <span
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-blue-600 ring-1 ring-inset ring-gray-200/50 dark:bg-neutral-800/80 dark:text-blue-400 dark:ring-neutral-700/50">
                                <UserRound class="h-4 w-4" />
                            </span>
                            Código de Color PLD
                        </h4>
                        <p class="mt-1 text-xs text-gray-500 dark:text-neutral-400">
                            Cada color representa una categoría de riesgo para el monitoreo de clientes.
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Sin coincidencia en listas -->
                    <div
                        class="group flex items-start gap-3 rounded-xl border border-gray-200/80 bg-white/80 px-4 py-3 text-sm text-gray-900 transition-all duration-300 ease-out hover:scale-[1.03] hover:border-blue-400/80 hover:bg-white hover:shadow-2xl hover:shadow-blue-500/10 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-white dark:hover:bg-neutral-800/80">
                        <span
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md border border-gray-300 bg-white shadow-sm transition-all duration-300 group-hover:border-blue-400/80 dark:border-neutral-700 dark:bg-transparent"></span>
                        <div>
                            <p class="text-xs font-semibold text-gray-800 dark:text-neutral-200">Sin coincidencia</p>
                            <p class="mt-1 text-[11px] leading-snug text-gray-500 dark:text-neutral-400">
                                Cliente sin registros en listas de observación.
                            </p>
                        </div>
                    </div>

                    <!-- Aparece en listas bloqueadas, necesita revisión -->
                    <div
                        class="group flex items-start gap-3 rounded-xl border border-gray-200/80 bg-white/80 px-4 py-3 text-sm text-gray-900 transition-all duration-300 ease-out hover:scale-[1.03] hover:border-orange-400/80 hover:bg-white hover:shadow-2xl hover:shadow-orange-500/10 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-white dark:hover:bg-neutral-800/80">
                        <span
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md bg-orange-500 shadow-sm shadow-orange-500/30 transition-all duration-300"></span>
                        <div>
                            <p class="text-xs font-semibold text-orange-700 dark:text-orange-300">Coincidencia</p>
                            <p class="mt-1 text-[11px] leading-snug text-gray-500 dark:text-neutral-400">
                                Aparece en listas bloqueadas, requiere revisión.
                            </p>
                        </div>
                    </div>

                    <!-- PPE, necesita revisión -->
                    <div
                        class="group flex items-start gap-3 rounded-xl border border-gray-200/80 bg-white/80 px-4 py-3 text-sm text-gray-900 transition-all duration-300 ease-out hover:scale-[1.03] hover:border-indigo-400/80 hover:bg-white hover:shadow-2xl hover:shadow-indigo-500/10 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-white dark:hover:bg-neutral-800/80">
                        <span
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md bg-indigo-400 shadow-sm shadow-indigo-400/30 transition-all duration-300"></span>
                        <div>
                            <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-200">PPE</p>
                            <p class="mt-1 text-[11px] leading-snug text-gray-500 dark:text-neutral-400">
                                Persona Políticamente Expuesta; atención especial.
                            </p>
                        </div>
                    </div>

                    <!-- Autorizada que aparece en listas -->
                    <div
                        class="group flex items-start gap-3 rounded-xl border border-gray-200/80 bg-white/80 px-4 py-3 text-sm text-gray-900 transition-all duration-300 ease-out hover:scale-[1.03] hover:border-yellow-400/80 hover:bg-white hover:shadow-2xl hover:shadow-yellow-500/10 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-white dark:hover:bg-neutral-800/80">
                        <span
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md bg-yellow-300 shadow-sm shadow-yellow-300/30 transition-all duration-300"></span>
                        <div>
                            <p class="text-xs font-semibold text-yellow-700 dark:text-yellow-200">Autorizada en Listas
                            </p>
                            <p class="mt-1 text-[11px] leading-snug text-gray-500 dark:text-neutral-400">
                                Cliente autorizado que figura en listas de control.
                            </p>
                        </div>
                    </div>

                    <!-- Fuera de categoría Tláloc -->
                    <div
                        class="group flex items-start gap-3 rounded-xl border border-gray-200/80 bg-white/80 px-4 py-3 text-sm text-gray-900 transition-all duration-300 ease-out hover:scale-[1.03] hover:border-purple-400/80 hover:bg-white hover:shadow-2xl hover:shadow-purple-500/10 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-white dark:hover:bg-neutral-800/80">
                        <span
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md bg-purple-500 shadow-sm shadow-purple-500/30 transition-all duration-300"></span>
                        <div>
                            <p class="text-xs font-semibold text-purple-700 dark:text-purple-200">Fuera de Categoría</p>
                            <p class="mt-1 text-[11px] leading-snug text-gray-500 dark:text-neutral-400">
                                Detectado fuera de categoría Tláloc, necesita revisión.
                            </p>
                        </div>
                    </div>

                    <!-- Listas internas (oficios CNSF) -->
                    <div
                        class="group flex items-start gap-3 rounded-xl border border-gray-200/80 bg-white/80 px-4 py-3 text-sm text-gray-900 transition-all duration-300 ease-out hover:scale-[1.03] hover:border-rose-400/80 hover:bg-white hover:shadow-2xl hover:shadow-rose-500/10 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-white dark:hover:bg-neutral-800/80">
                        <span
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md bg-rose-400 shadow-sm shadow-rose-400/30 transition-all duration-300"></span>
                        <div>
                            <p class="text-xs font-semibold text-rose-700 dark:text-rose-200">Listas Internas (CNSF)</p>
                            <p class="mt-1 text-[11px] leading-snug text-gray-500 dark:text-neutral-400">
                                Identificado en comunicados internos de la CNSF.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zona de búsqueda y filtros -->
            <div
                class="mt-6 rounded-2xl border border-gray-200/70 bg-white/60 p-4 shadow-lg shadow-gray-200/40 backdrop-blur-lg transition-all duration-300 ease-out focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-500/10 dark:border-neutral-800/80 dark:bg-neutral-950/60 dark:focus-within:border-blue-500/80 dark:focus-within:ring-blue-400/10">
                <!-- Informational Block -->
                <div class="mb-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-500">
                        Explorador de clientes
                    </p>
                    <p class="mt-1 flex items-center gap-2 text-sm text-gray-700 dark:text-neutral-300">
                        <span v-if="isSearching" class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent" aria-hidden="true"></span>
                        <span v-if="isSearching">Buscando…</span>
                        <span v-else>{{ totalResultados }} {{ totalResultados === 1 ? 'cliente encontrado' : 'clientes encontrados' }}</span>
                    </p>
                    <p class="mb-1 mt-0.5 text-[11px] text-gray-400 dark:text-neutral-500">
                        La búsqueda es por palabras (en cualquier orden) e incluye nombre, RFC, CURP, ID, domicilio, NCliente y póliza.
                    </p>
                    <!-- Chips de filtros activos -->
                    <div v-if="hasActiveFilters" class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span v-if="busqueda.trim()" class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-medium text-blue-700 ring-1 ring-inset ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/30">
                            “{{ busqueda.trim() }}”
                            <button @click="clearSearch" type="button" class="ml-0.5 font-bold hover:text-blue-900 dark:hover:text-blue-100" aria-label="Quitar búsqueda">×</button>
                        </span>
                        <span v-if="filtroTipoPersona !== 'todos'" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30">
                            {{ filtroTipoPersona === 'fisica' ? 'Personas Físicas' : 'Personas Morales' }}
                            <button @click="filtroTipoPersona = 'todos'" type="button" class="ml-0.5 font-bold hover:text-emerald-900" aria-label="Quitar filtro tipo">×</button>
                        </span>
                        <span v-if="filtroEstatus !== 'todos'" class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30">
                            {{ filtroEstatus === 'activo' ? 'Activos' : 'Inactivos' }}
                            <button @click="filtroEstatus = 'todos'" type="button" class="ml-0.5 font-bold hover:text-amber-900" aria-label="Quitar filtro estatus">×</button>
                        </span>
                        <template v-if="filtroCategoriaPLD.length !== pldCategoryOptions.length && filtroCategoriaPLD.length > 0">
                            <span v-for="cat in filtroCategoriaPLD" :key="cat" class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-medium text-violet-700 ring-1 ring-inset ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30">
                                {{ categoryDisplayNames[cat] || cat }}
                                <button @click="removeCategory(cat)" type="button" class="ml-0.5 font-bold hover:text-violet-900" :aria-label="`Quitar ${cat}`">×</button>
                            </span>
                        </template>
                        <button @click="clearAllFilters" type="button" class="rounded-full px-2.5 py-1 text-[11px] font-semibold text-gray-500 underline-offset-2 hover:text-gray-800 hover:underline dark:text-neutral-400 dark:hover:text-white">
                            Limpiar todo
                        </button>
                    </div>
                </div>


                <!-- Interactive Controls Block -->
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4 items-start">
                    <!-- Search Input -->
                    <div class="relative w-full md:col-span-2 lg:col-span-2">
                        <span
                            class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-gray-400 dark:text-neutral-500">
                            <svg v-if="!isSearching" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.5 15.5 20 20m-3-9a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                            </svg>
                            <span v-else class="h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></span>
                        </span>
                        <input ref="searchInputRef" v-model="busqueda" type="search" role="search" aria-label="Buscar clientes"
                            @keydown.escape="clearSearch"
                            class="w-full rounded-lg border border-gray-300/80 bg-gray-50/50 py-2.5 pl-10 pr-10 text-sm text-gray-900 placeholder-gray-400 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:placeholder-neutral-500 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70"
                            placeholder="Buscar por nombre, RFC, CURP, ID, póliza… (Ctrl+K)" />
                        <button v-if="busqueda" @click="clearSearch" type="button" aria-label="Limpiar búsqueda"
                            class="absolute inset-y-0 right-2.5 flex items-center rounded-full px-1.5 text-lg leading-none text-gray-400 transition-colors hover:text-gray-700 dark:text-neutral-500 dark:hover:text-white">
                            ×
                        </button>
                    </div>

                    <!-- Tipo de persona select -->
                    <div class="flex flex-col gap-1.5">
                        <label for="filtro-tipo-persona" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Tipo persona</label>
                        <select id="filtro-tipo-persona" v-model="filtroTipoPersona"
                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                            <option value="todos">Todas las personas</option>
                            <option value="fisica">Personas Físicas</option>
                            <option value="moral">Personas Morales</option>
                        </select>
                    </div>

                    <!-- Estatus del cliente select -->
                    <div class="flex flex-col gap-1.5">
                        <label for="filtro-estatus-cliente" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Estatus</label>
                        <select id="filtro-estatus-cliente" v-model="filtroEstatus"
                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                            <option value="todos">Todos los estatus</option>
                            <option value="activo">Activos</option>
                            <option value="inactivo">Inactivos</option>
                        </select>
                    </div>

                    <!-- Categoría PLD checkboxes grouped -->
                    <div class="relative md:col-span-2 lg:col-span-1" ref="categoryDropdownRef">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Categoría PLD</span>
                        <!-- Dropdown Button -->
                        <button id="pld-category-dropdown-button" ref="dropdownButtonRef"
                            @click="toggleCategoryDropdown" type="button" :aria-expanded="showCategoryDropdown"
                            class="flex w-full items-center justify-between rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                            <span class="truncate">{{ displayCategoryFilters || 'Todas' }}</span>
                            <span class="ml-2 flex items-center gap-1.5">
                                <span v-if="selectedCategoryCount !== pldCategoryOptions.length"
                                    class="flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-500 px-1.5 text-white text-[10px] font-bold">
                                    {{ selectedCategoryCount }}/{{ pldCategoryOptions.length }}
                                </span>
                                <svg class="h-4 w-4 text-gray-400 dark:text-neutral-500 transition-transform duration-200"
                                    :class="{ 'rotate-180': showCategoryDropdown }" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7">
                                    </path>
                                </svg>
                            </span>
                        </button>
                    </div>

                    <!-- Botón Descargar CSV -->
                    <div class="flex items-start">
                        <button @click="descargarCSV" type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm shadow-emerald-600/20 transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-emerald-700 hover:shadow-md hover:shadow-emerald-600/30 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Descargar CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Dropdown Panel (fixed positioning to escape overflow containers) -->
            <Teleport to="body">
                <div v-if="showCategoryDropdown" ref="dropdownPanelRef"
                    class="fixed z-[9999] w-72 origin-top-right rounded-xl border border-gray-200/80 bg-white/80 shadow-2xl shadow-gray-500/20 backdrop-blur-xl ring-1 ring-black ring-opacity-5 focus:outline-none dark:border-neutral-700/80 dark:bg-neutral-900/80 dark:shadow-black/50"
                    :style="{
                        top: dropdownPosition.top + 'px',
                        left: dropdownPosition.left + 'px'
                    }" role="menu" aria-orientation="vertical" aria-labelledby="pld-category-dropdown-button">
                    <div class="p-4">
                        <div class="mb-3 border-b border-gray-200 pb-3 dark:border-neutral-700">
                            <label
                                class="inline-flex w-full items-center rounded-md p-1 transition-colors hover:bg-gray-100 dark:hover:bg-neutral-800">
                                <input type="checkbox" v-model="selectAllCategoriesComputed"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500/50 focus:ring-offset-0 dark:border-neutral-600 dark:bg-neutral-800 dark:checked:bg-blue-600">
                                <span class="ml-2 text-xs font-semibold text-gray-800 dark:text-white">Seleccionar
                                    todas ({{ selectedCategoryCount }}/{{ pldCategoryOptions.length }})</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 gap-1">
                            <label v-for="option in pldCategoryOptions" :key="option.value"
                                class="inline-flex items-center rounded-md p-1 text-xs text-gray-900 transition-colors hover:bg-gray-100 dark:text-white dark:hover:bg-neutral-800">
                                <input type="checkbox" :value="option.value" v-model="filtroCategoriaPLD"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500/50 focus:ring-offset-0 dark:border-neutral-600 dark:bg-neutral-800 dark:checked:bg-blue-600">
                                <span class="ml-2">{{ option.label }}</span>
                            </label>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3 dark:border-neutral-700">
                            <button @click="filtroCategoriaPLD = []" type="button" class="text-[11px] font-semibold text-gray-500 hover:text-gray-800 hover:underline dark:text-neutral-400 dark:hover:text-white">Limpiar</button>
                            <button @click="showCategoryDropdown = false" type="button" class="rounded-lg bg-blue-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-blue-700">Aplicar</button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- Listado de clientes -->
            <div
                class="mt-8 overflow-hidden rounded-2xl border border-gray-200/70 bg-white/60 shadow-xl shadow-gray-200/50 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-2xl dark:shadow-black/20">
                <div v-if="isSearching" class="h-0.5 w-full overflow-hidden bg-blue-100 dark:bg-blue-950">
                    <div class="h-full w-1/3 animate-[loading-bar_1s_ease-in-out_infinite] bg-blue-500"></div>
                </div>
                <div class="max-h-[32rem] overflow-y-auto" ref="listaScrollRef">
                    <table class="min-w-full border-collapse text-sm text-gray-800 dark:text-neutral-200">
                        <thead class="sticky top-0 z-10">
                            <tr
                                class="bg-gray-50/80 text-xs font-semibold uppercase tracking-wider text-gray-600 backdrop-blur-md dark:bg-neutral-900/80 dark:text-neutral-300">
                                <th
                                    class="border-b border-gray-200/80 px-4 py-3 text-left align-middle font-semibold dark:border-neutral-800">
                                    Nombre
                                </th>
                                <th
                                    class="border-b border-gray-200/80 px-4 py-3 text-left align-middle font-semibold dark:border-neutral-800">
                                    RFC
                                </th>
                                <th
                                    class="border-b border-gray-200/80 px-4 py-3 text-left align-middle font-semibold dark:border-neutral-800">
                                    CURP
                                </th>
                                <th
                                    class="border-b border-gray-200/80 px-4 py-3 text-left align-middle font-semibold dark:border-neutral-800">
                                    Tipo
                                </th>
                                <th
                                    class="border-b border-gray-200/80 px-4 py-3 text-left align-middle font-semibold dark:border-neutral-800">
                                    Categorías
                                </th>
                                <th
                                    class="border-b border-gray-200/80 px-4 py-3 text-center align-middle font-semibold dark:border-neutral-800">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <TransitionGroup tag="tbody" name="list" appear>
                            <tr v-if="isSearching && !clientesFiltrados.length" key="loading">
                                <td colspan="6" class="px-4 py-6">
                                    <div class="space-y-2" aria-hidden="true">
                                        <div v-for="i in 4" :key="i" class="h-10 animate-pulse rounded-lg bg-gray-100 dark:bg-neutral-800"></div>
                                    </div>
                                    <p class="mt-3 text-center text-xs text-gray-400">Buscando clientes…</p>
                                </td>
                            </tr>
                            <tr v-else-if="!clientesFiltrados.length" key="no-results">
                                <td colspan="6"
                                    class="border-t border-dashed border-gray-200/80 px-4 py-16 text-center text-sm text-gray-500 dark:border-neutral-800 dark:text-neutral-400">
                                    <p class="text-sm font-semibold text-gray-700 dark:text-neutral-200">Sin resultados para los filtros actuales</p>
                                    <p class="mx-auto mt-1 max-w-md text-xs">Prueba con menos palabras, revisa la ortografía o quita algún filtro. La búsqueda ignora mayúsculas, acentos y el orden de las palabras.</p>
                                    <div class="mt-4 flex items-center justify-center gap-2">
                                        <button @click="clearAllFilters" type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700">Limpiar filtros</button>
                                        <button @click="clearSearch" type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">Solo quitar búsqueda</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-for="(cliente, index) in clientesFiltrados" :key="cliente.IDCliente"
                                :data-index="index"
                                class="group cursor-pointer border-b border-gray-100 bg-white/80 transition-all duration-300 ease-out will-change-transform hover:!opacity-100 hover:bg-gray-50/80 hover:shadow-lg hover:shadow-gray-300/30 motion-safe:hover:!scale-[1.01] dark:border-neutral-800/60 dark:bg-neutral-900/50 dark:hover:bg-neutral-800/60 dark:hover:shadow-black/20">
                                <td class="px-4 py-3 align-middle">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold text-gray-800 dark:text-neutral-50">
                                            {{ cliente.Nombre }} {{ cliente.ApellidoPaterno }} {{
                                            cliente.ApellidoMaterno }}
                                        </span>
                                        <span v-if="cliente.RazonSocial"
                                            class="mt-0.5 text-xs text-gray-500 dark:text-neutral-400">{{
                                                cliente.RazonSocial }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <span class="text-xs font-mono text-gray-600 dark:text-neutral-300">{{ cliente.RFC
                                        }}</span>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <span class="text-xs font-mono text-gray-600 dark:text-neutral-300">{{ cliente.CURP
                                        }}</span>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium"
                                        :class="Number(cliente.IDTipoPersona) === 1 ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'">
                                        {{ Number(cliente.IDTipoPersona) === 1 ? 'Física' : 'Moral' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="flex items-center gap-1.5">
                                        <template v-for="(tag, i) in getCategoryTags(cliente)" :key="i">
                                            <span v-if="tag.type === 'text'"
                                                class="text-xs italic text-gray-500 dark:text-neutral-400">
                                                {{ tag.tooltip }}
                                            </span>
                                            <TooltipProvider v-else :delay-duration="0">
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <div :class="tag.color"
                                                            class="h-3 w-5 cursor-help rounded-sm border border-black/5 shadow-sm">
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p class="text-xs font-medium">{{ tag.tooltip }}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </template>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center align-middle">
                                    <button
                                        class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 transition-all duration-200 ease-out hover:bg-blue-100/60 hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/70 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:text-blue-400 dark:hover:bg-blue-900/20 dark:hover:text-blue-300 dark:focus-visible:ring-offset-neutral-950"
                                        @click="irADetalleCliente(cliente)">
                                        Ver detalle
                                        <span
                                            class="text-blue-500 transition-transform duration-200 group-hover:translate-x-0.5 dark:text-blue-400">→</span>
                                    </button>
                                </td>
                            </tr>
                        </TransitionGroup>
                    </table>
                </div>
            </div>

            <!-- Controles de paginación -->
            <div
                class="mt-4 flex flex-col items-start justify-between gap-4 rounded-2xl border border-gray-200/70 bg-white/60 p-3 shadow-lg shadow-gray-200/40 backdrop-blur-lg sm:flex-row sm:items-center dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-2xl dark:shadow-black/20">
                <!-- Items per page dropdown -->
                <div class="flex flex-col items-start">
                    <div class="flex items-center space-x-2">
                        <label for="items-per-page" class="text-xs text-gray-600 dark:text-neutral-300">Mostrar:</label>
                        <select id="items-per-page" v-model="itemsPerPage"
                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 py-2 pl-3 pr-8 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                            <option v-for="option in itemsPerPageOptions" :key="option" :value="option">{{ option }}
                            </option>
                        </select>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-neutral-400">
                        Mostrando
                        <span class="font-semibold text-gray-800 dark:text-neutral-200">{{ rangoInicio }}</span>–<span
                            class="font-semibold text-gray-800 dark:text-neutral-200">{{ rangoFin }}</span>
                        de
                        <span class="font-semibold text-gray-800 dark:text-neutral-200">{{ totalResultados }}</span>
                    </p>
                </div>

                <!-- Page navigation controls -->
                <div class="flex items-center space-x-2">
                    <button @click="prevPage" :disabled="currentPage === 1 || isSearching"
                        class="rounded-lg border border-gray-300/80 bg-white/80 px-4 py-2 text-xs font-medium text-gray-700 shadow-sm transition-all duration-200 ease-out hover:bg-gray-100/80 hover:shadow-md hover:shadow-gray-300/20 disabled:cursor-not-allowed disabled:opacity-50 motion-safe:hover:enabled:scale-105 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:enabled:bg-neutral-800/90">
                        Anterior
                    </button>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-600 dark:text-neutral-300">Página</span>
                        <input type="number" v-model.number="currentPage" min="1" :max="Math.max(1, totalPages)" :disabled="isSearching"
                            class="w-16 rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2 text-center text-xs text-gray-900 outline-none ring-blue-500/50 transition-all duration-150 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70" />
                        <span class="text-xs text-gray-600 dark:text-neutral-300">de {{ Math.max(1, totalPages) }}</span>
                    </div>
                    <button @click="nextPage" :disabled="currentPage === totalPages || totalPages === 0 || isSearching"
                        class="rounded-lg border border-gray-300/80 bg-white/80 px-4 py-2 text-xs font-medium text-gray-700 shadow-sm transition-all duration-200 ease-out hover:bg-gray-100/80 hover:shadow-md hover:shadow-gray-300/20 disabled:cursor-not-allowed disabled:opacity-50 motion-safe:hover:enabled:scale-105 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:enabled:bg-neutral-800/90">
                        Siguiente
                    </button>
                </div>
            </div>

        <!-- Ambient background elements -->


            <Toast v-model:modelValue="showToast" :type="toastType" :message="toastMessage" />
            </div>
        </FadeIn>
    </AppLayout>
</template>

<style>
/* Staggered list animation */
.list-enter-active,
.list-leave-active {
    transition: all 0.5s ease;
}

.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

.list-enter-active {
    transition-delay: calc(0.02s * var(--stagger-index));
}

/* Modal animation */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .modal-card,
.modal-leave-active .modal-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.modal-enter-from .modal-card {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
}

.modal-leave-to .modal-card {
    opacity: 0;
    transform: translateY(10px) scale(0.98);
}

/* Custom scrollbar for webkit browsers */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background-color: transparent;
}

::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.4);
    border-radius: 10px;
    border: 2px solid transparent;
    background-clip: content-box;
}

::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.6);
}

/* Keyframe for subtle background animation if needed */
@keyframes subtle-pan {
    0% {
        background-position: 0% 0%;
    }

    100% {
        background-position: 10% 10%;
    }
}

@keyframes loading-bar {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(300%); }
}
</style>
