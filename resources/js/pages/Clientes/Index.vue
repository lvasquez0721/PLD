<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Titulo from '@/components/ui/Titulo.vue'
import Toast from '@/components/ui/alert/Toast.vue'
import { type BreadcrumbItem } from '@/types'
import { UserRound } from 'lucide-vue-next'

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
const showModal = ref(false)
const clienteSeleccionado = ref<any | null>(null)
const filtroTipoPersona = ref(props.filters?.tipo || 'todos')

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

const itemsPerPage = ref(Number(props.filters?.per_page) || 10)
const itemsPerPageOptions = [5, 10, 20, 50, 100]

// Watchers para búsqueda y filtros con debounce manual para la búsqueda
let searchTimeout: any = null
watch(busqueda, (val) => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        let categoriesToSend: string[] | string | undefined;

        if (filtroCategoriaPLD.value.length === pldCategoryOptions.length) {
            // Si todas las categorías están seleccionadas, mandamos 'todos'
            categoriesToSend = 'todos';
        } else if (filtroCategoriaPLD.value.length > 0) {
            // Si hay algunas seleccionadas, mandamos ese subconjunto
            categoriesToSend = filtroCategoriaPLD.value;
        } else {
            // Si no hay ninguna, no mandamos el parámetro
            categoriesToSend = undefined;
        }

        router.get('/clientes', {
            search: val,
            tipo: filtroTipoPersona.value,
            per_page: itemsPerPage.value,
            category: categoriesToSend
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        })
    }, 300)
})

watch([filtroTipoPersona, itemsPerPage, filtroCategoriaPLD], () => {
    let categoriesToSend: string[] | string | undefined;

    if (filtroCategoriaPLD.value.length === pldCategoryOptions.length) {
        // If all categories are selected in the UI, send 'todos' to maintain original behavior
        categoriesToSend = 'todos';
    } else if (filtroCategoriaPLD.value.length > 0) {
        // If some categories are selected, send them
        categoriesToSend = filtroCategoriaPLD.value;
    } else {
        // If no categories are selected, send undefined
        categoriesToSend = undefined;
    }

    router.get('/clientes', {
        search: busqueda.value,
        tipo: filtroTipoPersona.value,
        per_page: itemsPerPage.value,
        category: categoriesToSend // Now sends 'todos', array of categories, or undefined
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
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
    let categoriesToSend: string[] | string | undefined;

    if (filtroCategoriaPLD.value.length === pldCategoryOptions.length) {
        // Si todas las categorías están seleccionadas, mandamos 'todos'
        categoriesToSend = 'todos';
    } else if (filtroCategoriaPLD.value.length > 0) {
        // Si hay algunas seleccionadas, mandamos ese subconjunto
        categoriesToSend = filtroCategoriaPLD.value;
    } else {
        // Si no hay ninguna, no mandamos el parámetro
        categoriesToSend = undefined;
    }

    router.get('/clientes', {
        search: busqueda.value,
        tipo: filtroTipoPersona.value,
        per_page: itemsPerPage.value,
        page: page,
        category: categoriesToSend
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

function abrirModal(cliente: any) {
    clienteSeleccionado.value = cliente
    showModal.value = true
}

function cerrarModal() {
    showModal.value = false
    clienteSeleccionado.value = null
}

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
        tags.push({ color: 'bg-white border border-slate-300', tooltip: 'Sin coincidencia en listas', type: 'text' });
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
    window.addEventListener('scroll', handleScrollOrResize, true);
    window.addEventListener('resize', handleScrollOrResize);
});

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick);
    window.removeEventListener('scroll', handleScrollOrResize, true);
    window.removeEventListener('resize', handleScrollOrResize);
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

// Se añade después la ruta correcta para la descarga
// function descargarBasePersonas() {
//   window.open('/ruta/descargar/base/personas', '_blank')
// }
</script>

<template>

    <Head title="Clientes" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <Titulo :icon="UserRound" title="Consulta de Clientes" />

        <!-- Leyenda de colores PLD con tarjetas sensoriales -->
        <div
            class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-slate-100 p-4 shadow-sm backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:from-neutral-950 dark:via-neutral-900 dark:to-neutral-950 dark:shadow-inner dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.9)]">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                        <span
                            class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-blue-600 dark:bg-neutral-800 dark:text-blue-400">
                            <UserRound class="h-4 w-4" />
                        </span>
                        Código de color categorías PLD
                    </h4>
                    <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                        Cada color representa el nivel de atención que requiere el cliente en los filtros de listas.
                    </p>
                </div>

            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Sin coincidencia en listas -->
                <div
                    class="group flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900 transition-all duration-200 ease-out hover:-translate-y-[1px] hover:border-blue-500/70 hover:bg-blue-50/60 hover:shadow-lg hover:shadow-blue-500/10 dark:border-neutral-800 dark:bg-neutral-950/60 dark:text-white dark:hover:bg-neutral-900/80">
                    <span
                        class="mt-0.5 h-6 w-6 shrink-0 rounded-md border border-slate-300 bg-white transition-all duration-200 group-hover:border-blue-400/80 dark:border-neutral-700 dark:bg-transparent"></span>
                    <div>
                        <p class="text-xs font-semibold text-slate-800 dark:text-neutral-200">Sin coincidencia en listas</p>
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">
                            No se encontraron registros en listas de observación o sanciones.
                        </p>
                    </div>
                </div>



                <!-- Aparece en listas bloqueadas, necesita revisión -->
                <div
                    class="group flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900 transition-all duration-200 ease-out hover:-translate-y-[1px] hover:border-orange-500/70 hover:bg-orange-50/70 hover:shadow-lg hover:shadow-orange-500/20 dark:border-neutral-800 dark:bg-neutral-950/60 dark:text-white dark:hover:bg-neutral-900/80">
                    <span
                        class="mt-0.5 h-6 w-6 shrink-0 rounded-md border border-slate-200 bg-orange-500 transition-all duration-200 group-hover:border-orange-200/80 dark:border-neutral-700"></span>
                    <div>
                        <p class="text-xs font-semibold text-orange-700 dark:text-orange-300">Coincidencia, necesita revisión</p>
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">
                            Persona o empresa aparece en listas bloqueadas, necesita revisión.
                        </p>
                    </div>
                </div>



                <!-- PPE, necesita revisión -->
                <div
                    class="group flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900 transition-all duration-200 ease-out hover:-translate-y-[1px] hover:border-indigo-400/70 hover:bg-indigo-50/70 hover:shadow-lg hover:shadow-indigo-400/20 dark:border-neutral-800 dark:bg-neutral-950/60 dark:text-white dark:hover:bg-neutral-900/80">
                    <span
                        class="mt-0.5 h-6 w-6 shrink-0 rounded-md border border-slate-200 bg-indigo-400 transition-all duration-200 group-hover:border-indigo-200/80 dark:border-neutral-700"></span>
                    <div>
                        <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-200">PPE, necesita revisión</p>
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">
                            Clientes políticamente expuestos; requiere atención.
                        </p>
                    </div>
                </div>

                <!-- Autorizada que aparece en listas -->
                <div
                    class="group flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900 transition-all duration-200 ease-out hover:-translate-y-[1px] hover:border-yellow-300/70 hover:bg-yellow-50/70 hover:shadow-lg hover:shadow-yellow-300/20 dark:border-neutral-800 dark:bg-neutral-950/60 dark:text-white dark:hover:bg-neutral-900/80">
                    <span
                        class="mt-0.5 h-6 w-6 shrink-0 rounded-md border border-slate-200 bg-yellow-300 transition-all duration-200 group-hover:border-yellow-100/80 dark:border-neutral-700"></span>
                    <div>
                        <p class="text-xs font-semibold text-yellow-700 dark:text-yellow-200">Autorizada que aparece en listas</p>
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">
                            Persona / Empresa autorizada que aparece en listas.
                        </p>
                    </div>
                </div>

                <!-- Fuera de categoría Tláloc -->
                <div
                    class="group flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900 transition-all duration-200 ease-out hover:-translate-y-[1px] hover:border-purple-500/70 hover:bg-purple-50/70 hover:shadow-lg hover:shadow-purple-500/20 dark:border-neutral-800 dark:bg-neutral-950/60 dark:text-white dark:hover:bg-neutral-900/80">
                    <span
                        class="mt-0.5 h-6 w-6 shrink-0 rounded-md border border-slate-200 bg-purple-500 transition-all duration-200 group-hover:border-purple-200/80 dark:border-neutral-700"></span>
                    <div>
                        <p class="text-xs font-semibold text-purple-700 dark:text-purple-200">Fuera de categoría Tláloc</p>
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">
                            Persona / Empresa detectada fuera de categoría Tláloc, necesita revisión
                        </p>
                    </div>
                </div>

                <!-- Listas internas (oficios CNSF) -->
                <div
                    class="group flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900 transition-all duration-200 ease-out hover:-translate-y-[1px] hover:border-rose-400/70 hover:bg-rose-50/70 hover:shadow-lg hover:shadow-rose-400/20 dark:border-neutral-800 dark:bg-neutral-950/60 dark:text-white dark:hover:bg-neutral-900/80">
                    <span
                        class="mt-0.5 h-6 w-6 shrink-0 rounded-md border border-slate-200 bg-rose-400 transition-all duration-200 group-hover:border-rose-200/80 dark:border-neutral-700"></span>
                    <div>
                        <p class="text-xs font-semibold text-rose-700 dark:text-rose-200">Listas internas (oficios CNSF)</p>
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">
                            Identificado en listas internas o comunicados de la CNSF.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zona de búsqueda y filtros -->
        <div
            class="mt-6 rounded-xl border border-slate-100 bg-gradient-to-r from-white/90 via-slate-50/70 to-white/90 p-4 shadow-sm backdrop-blur-sm transition-colors duration-200 ease-out focus-within:border-blue-400/80 focus-within:shadow-[0_0_0_1px_rgba(59,130,246,0.3)] dark:border-neutral-800/80 dark:bg-gradient-to-r dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90">
            <!-- Informational Block -->
            <div class="mb-4 pb-4 border-b border-slate-200 dark:border-neutral-700 md:mb-0 md:pb-0 md:border-b-0">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-neutral-500">
                    Explorador de clientes
                </p>
                <p class="mt-1 text-sm text-slate-700 dark:text-neutral-300 ">
                    {{ totalResultados }} {{ totalResultados === 1 ? 'cliente encontrado' : 'clientes encontrados' }}
                </p>
                <p v-if="busqueda || filtroTipoPersona !== 'todos' || displayCategoryFilters" class="mt-1 text-xs text-slate-500 dark:text-neutral-400 mb-2">
                    Refinando por
                    <span v-if="busqueda" class="font-medium text-slate-800 dark:text-neutral-200">“{{ busqueda }}”</span>
                    <span v-if="busqueda && (filtroTipoPersona !== 'todos' || displayCategoryFilters)"> · </span>
                    <span v-if="filtroTipoPersona === 'fisica'" class="font-medium text-slate-800 dark:text-neutral-200">Personas físicas</span>
                    <span v-else-if="filtroTipoPersona === 'moral'" class="font-medium text-slate-800 dark:text-neutral-200">Personas morales</span>
                    <span v-if="(busqueda || filtroTipoPersona !== 'todos') && displayCategoryFilters" class="font-medium text-slate-800 dark:text-neutral-200"> · </span>
                    <span v-if="displayCategoryFilters" class="font-medium text-slate-800 dark:text-neutral-200">{{ displayCategoryFilters }}</span>
                </p>
            </div>

            <div style="height:0.5rem;"></div>

            <!-- Interactive Controls Block -->
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 items-start">
                <!-- Search Input -->
                <div class="relative w-full md:col-span-2 lg:col-span-2">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400 dark:text-neutral-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.5 15.5 20 20m-3-9a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                        </svg>
                    </span>
                    <input v-model="busqueda" type="text"
                        class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder-slate-400 shadow-inner outline-none ring-0 transition-all duration-150 focus:border-blue-500 focus:bg-white focus:shadow-[0_0_0_1px_rgba(59,130,246,0.35)] dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:placeholder-neutral-500 dark:focus:bg-neutral-900"
                        placeholder="Busca por nombre, RFC, CURP o razón social" />
                </div>

                <!-- Tipo de persona select -->
                <div class="flex flex-col gap-2">
                    <select id="filtro-tipo-persona" v-model="filtroTipoPersona"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-xs text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900">
                        <option value="todos">Todos</option>
                        <option value="fisica">Física</option>
                        <option value="moral">Moral</option>
                    </select>
                </div>

                <!-- Categoría PLD checkboxes grouped -->
                <div class="relative md:col-span-3 lg:col-span-1" ref="categoryDropdownRef">
                    <!-- Dropdown Button -->
                    <button
                        id="pld-category-dropdown-button"
                        ref="dropdownButtonRef"
                        @click="toggleCategoryDropdown"
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    >
                        <span>Categoría PLD</span>
                        <span v-if="selectedCategoryCount > 0" class="ml-2 px-2 py-0.5 bg-blue-500 text-white rounded-full text-[10px]">
                            {{ selectedCategoryCount }}
                        </span>
                        <svg class="h-4 w-4 text-slate-400 dark:text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Dropdown Panel (fixed positioning to escape overflow containers) -->
        <Teleport to="body">
            <div
                v-if="showCategoryDropdown"
                ref="dropdownPanelRef"
                class="fixed z-[9999] w-72 origin-top-right rounded-md border border-slate-200 bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                :style="{
                    top: dropdownPosition.top + 'px',
                    left: dropdownPosition.left + 'px'
                }"
                role="menu"
                aria-orientation="vertical"
                aria-labelledby="pld-category-dropdown-button"
            >
                <div class="p-4">
                    <div class="mb-2">
                        <label class="inline-flex items-center text-xs text-slate-900 dark:text-white">
                            <input type="checkbox" v-model="selectAllCategoriesComputed"
                                class="form-checkbox rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:checked:bg-blue-600 dark:focus:ring-offset-neutral-900">
                            <span class="ml-2 font-semibold">Seleccionar todas</span>
                        </label>
                    </div>
                    <div class="grid grid-cols-1 gap-2 border-t border-slate-200 pt-2 dark:border-neutral-700">
                        <label v-for="option in pldCategoryOptions" :key="option.value" class="inline-flex items-center text-xs text-slate-900 dark:text-white">
                            <input type="checkbox" :value="option.value" v-model="filtroCategoriaPLD"
                                class="form-checkbox rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:checked:bg-blue-600 dark:focus:ring-offset-neutral-900">
                            <span class="ml-2">{{ option.label }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Listado de clientes -->
        <div
            class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)]">
            <div class="max-h-[28rem] overflow-y-auto" ref="listaScrollRef">
                <table class="min-w-full border-collapse text-sm text-slate-900 dark:text-white">
                    <thead>
                        <tr
                            class="sticky top-0 z-10 bg-gradient-to-r from-slate-50 via-slate-50/95 to-blue-50/60 text-xs font-semibold uppercase tracking-wide text-slate-700 backdrop-blur-sm dark:bg-gradient-to-r dark:from-neutral-900/95 dark:via-neutral-900/95 dark:to-slate-900/95 dark:text-neutral-200">
                            <th
                                class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">
                                Nombre
                            </th>
                            <th
                                class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">
                                RFC
                            </th>
                            <th
                                class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">
                                CURP
                            </th>
                            <th
                                class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">
                                Tipo
                            </th>
                            <th
                                class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">
                                Categorias
                            </th>
                            <th
                                class="border-b border-slate-200 px-3 py-2 text-center align-middle text-[11px] font-semibold dark:border-neutral-800">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!clientesFiltrados.length">
                            <td colspan="6"
                                class="border-t border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500 dark:border-neutral-800 dark:text-neutral-400">
                                No se encontraron clientes con los filtros actuales.
                            </td>
                        </tr>
                        <tr v-for="cliente in clientesFiltrados" :key="cliente.IDCliente"
                            class="group cursor-pointer border-b border-l-2 border-slate-100 border-l-transparent bg-white transition-all duration-200 ease-out hover:-translate-y-[1px] hover:border-l-blue-400 hover:bg-gradient-to-r hover:from-white hover:via-slate-50/80 hover:to-blue-50/40 hover:shadow-[0_10px_30px_rgba(15,23,42,0.08)] dark:border-neutral-800/60 dark:border-l-transparent dark:bg-neutral-950/40 dark:hover:border-l-blue-500 dark:hover:bg-gradient-to-r dark:hover:from-neutral-950/90 dark:hover:via-neutral-900/90 dark:hover:to-slate-800/90 dark:hover:shadow-[0_18px_40px_rgba(0,0,0,0.75)]">
                            <td class="px-3 py-2 align-middle">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-slate-900 dark:text-neutral-50">
                                        {{ cliente.Nombre }} {{ cliente.ApellidoPaterno }} {{ cliente.ApellidoMaterno }}
                                    </span>
                                    <span v-if="cliente.RazonSocial"
                                        class="mt-0.5 text-[11px] text-slate-500 dark:text-neutral-400">{{ cliente.RazonSocial }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <span class="text-xs font-mono text-slate-700 dark:text-neutral-200">{{ cliente.RFC }}</span>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <span class="text-xs font-mono text-slate-700 dark:text-neutral-200">{{ cliente.CURP }}</span>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <span
                                    class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium dark:bg-neutral-800/80"
                                    :class="cliente.IDTipoPersona === 1 ? 'text-blue-700 dark:text-blue-200' : 'text-emerald-700 dark:text-emerald-200'">
                                    {{ cliente.IDTipoPersona === 1 ? 'Física' : 'Moral' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <div class="flex flex-wrap gap-1">
                                    <template v-for="(tag, index) in getCategoryTags(cliente)" :key="index">
                                        <span v-if="tag.type === 'text'" class="text-sm text-slate-500 dark:text-neutral-400">
                                            {{ tag.tooltip }}
                                        </span>
                                        <span v-else
                                            :class="[tag.color]"
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium leading-4 text-white shadow-sm"
                                            v-tooltip="tag.tooltip">
                                            {{ tag.tooltip }}
                                        </span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center align-middle">
                                <button
                                    class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 transition-all duration-200 ease-out hover:text-blue-500 hover:underline hover:underline-offset-4 hover:decoration-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/70 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:text-blue-400 dark:hover:text-blue-300 dark:focus-visible:ring-offset-neutral-950"
                                    @click="abrirModal(cliente)">
                                    Ver detalle
                                    <span
                                        class="inline-block text-[10px] text-blue-500 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:scale-110 dark:text-blue-300">›</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Controles de paginación -->
        <div
            class="mt-4 flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-100 bg-gradient-to-r from-white via-slate-50/70 to-white p-3 text-slate-900 shadow-sm backdrop-blur-sm sm:flex-row sm:items-center sm:gap-4 dark:border-neutral-800 dark:bg-gradient-to-r dark:from-neutral-950/95 dark:via-neutral-900/90 dark:to-neutral-950/95 dark:text-white">
            <!-- Items per page dropdown -->
            <div class="flex flex-col items-start space-y-1 sm:space-y-2">
                <div class="flex items-center space-x-2">
                    <label for="items-per-page" class="text-xs text-slate-600 dark:text-neutral-300">Registros por página</label>
                    <select id="items-per-page" v-model="itemsPerPage"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900">
                        <option v-for="option in itemsPerPageOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                </div>
                <p class="text-xs text-slate-500 dark:text-neutral-400">
                    Mostrando
                    <span class="font-medium text-slate-800 dark:text-neutral-200">{{ rangoInicio }}</span> –
                    <span class="font-medium text-slate-800 dark:text-neutral-200">{{ rangoFin }}</span>
                    de
                    <span class="font-medium text-slate-800 dark:text-neutral-200">{{ totalResultados }}</span>
                    clientes
                </p>
            </div>

            <!-- Page navigation controls -->
            <div class="flex items-center space-x-2">
                <button @click="prevPage" :disabled="currentPage === 1"
                    class="rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-slate-700 shadow-sm transition-all duration-150 ease-out hover:-translate-y-[1px] hover:bg-slate-50 hover:shadow-md disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:bg-neutral-800/90">
                    Anterior
                </button>
                <span class="text-xs text-slate-600 dark:text-neutral-300">Página</span>
                <input type="number" v-model.number="currentPage" min="1"
                    :max="totalPages"
                    class="w-16 rounded-lg border border-slate-300 bg-white px-3 py-2 text-center text-xs text-slate-900 outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900" />
                <span class="text-xs text-slate-600 dark:text-neutral-300">de {{ totalPages }}</span>
                <button @click="nextPage" :disabled="currentPage === totalPages"
                    class="rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-slate-700 shadow-sm transition-all duration-150 ease-out hover:-translate-y-[1px] hover:bg-slate-50 hover:shadow-md disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:bg-neutral-800/90">
                    Siguiente
                </button>
            </div>
        </div>

        <!-- Modal -->
        <Transition name="modal-fade">
            <div v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm transition-opacity">
                <div
                    class="modal-fade-card relative w-full max-w-xl rounded-2xl border border-slate-200 bg-gradient-to-b from-white via-slate-50 to-white p-6 text-slate-900 shadow-2xl shadow-slate-300/70 transition-transform duration-200 ease-out dark:border-neutral-700 dark:bg-gradient-to-b dark:from-neutral-950 dark:via-neutral-950 dark:to-neutral-950 dark:text-white dark:shadow-black/70">
                    <button
                        class="absolute right-3 top-3 rounded-full bg-white/0 px-2 py-1 text-slate-400 shadow-none transition-all duration-150 hover:bg-slate-100/80 hover:text-slate-600 hover:shadow-sm dark:bg-transparent dark:text-neutral-400 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-200"
                        @click="cerrarModal">
                        ✕
                    </button>
                    <h3 class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                        <span class="h-6 w-1 rounded-full bg-gradient-to-b from-blue-400 to-blue-600"></span>
                        Datos registrados del cliente
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                        Información clave para el análisis de listas y monitoreo de PLD.
                    </p>
                    <div v-if="clienteSeleccionado" class="mt-4 max-h-[60vh] space-y-3 overflow-y-auto pr-1 text-sm">
                        <p>
                            <strong class="text-slate-700 dark:text-neutral-300">Nombre:</strong>
                            <span class="text-slate-900 dark:text-neutral-100">
                                {{ clienteSeleccionado.Nombre }} {{ clienteSeleccionado.ApellidoPaterno }}
                                {{ clienteSeleccionado.ApellidoMaterno }}
                            </span>
                        </p>
                        <p>
                            <strong class="text-slate-700 dark:text-neutral-300">RFC:</strong>
                            <span class="font-mono text-slate-900 dark:text-neutral-100">{{ clienteSeleccionado.RFC }}</span>
                        </p>
                        <p>
                            <strong class="text-slate-700 dark:text-neutral-300">CURP:</strong>
                            <span class="font-mono text-slate-900 dark:text-neutral-100">{{ clienteSeleccionado.CURP }}</span>
                        </p>
                        <p v-if="clienteSeleccionado.RazonSocial">
                            <strong class="text-slate-700 dark:text-neutral-300">Razón Social:</strong>
                            <span class="text-slate-900 dark:text-neutral-100">{{ clienteSeleccionado.RazonSocial }}</span>
                        </p>
                        <p>
                            <strong class="text-slate-700 dark:text-neutral-300">Tipo de persona:</strong>
                            <span
                                class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium dark:bg-neutral-800/80"
                                :class="clienteSeleccionado.IDTipoPersona === 1 ? 'text-blue-700 dark:text-blue-200' : 'text-emerald-700 dark:text-emerald-200'">
                                {{ clienteSeleccionado.IDTipoPersona === 1 ? 'Física' : 'Moral' }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </Transition>

        <Toast v-model:modelValue="showToast" :type="toastType" :message="toastMessage" />
    </AppLayout>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.22s ease-out;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-fade-enter-active .modal-fade-card,
.modal-fade-leave-active .modal-fade-card {
  transition: transform 0.22s ease-out, opacity 0.22s ease-out, box-shadow 0.22s ease-out;
}

.modal-fade-enter-from .modal-fade-card {
  transform: translateY(12px) scale(0.96);
  opacity: 0;
}

.modal-fade-leave-to .modal-fade-card {
  transform: translateY(8px) scale(0.97);
  opacity: 0;
}
</style>
