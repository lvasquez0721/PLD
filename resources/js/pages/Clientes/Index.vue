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
            estatus: filtroEstatus.value,
            per_page: itemsPerPage.value,
            category: categoriesToSend
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        })
    }, 300)
})

watch([filtroTipoPersona, filtroEstatus, itemsPerPage, filtroCategoriaPLD], () => {
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
        estatus: filtroEstatus.value,
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
        estatus: filtroEstatus.value,
        per_page: itemsPerPage.value,
        page: page,
        category: categoriesToSend
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

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

function descargarCSV() {
    let categoriesToSend: string[] | string | undefined;

    if (filtroCategoriaPLD.value.length === pldCategoryOptions.length) {
        categoriesToSend = 'todos';
    } else if (filtroCategoriaPLD.value.length > 0) {
        categoriesToSend = filtroCategoriaPLD.value;
    } else {
        categoriesToSend = undefined;
    }

    const params = new URLSearchParams();
    if (busqueda.value) params.append('search', busqueda.value);
    if (filtroTipoPersona.value !== 'todos') params.append('tipo', filtroTipoPersona.value);
    if (filtroEstatus.value !== 'todos') params.append('estatus', filtroEstatus.value);
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
                    <p class="mt-1 text-sm text-gray-700 dark:text-neutral-300 ">
                        {{ totalResultados }} {{ totalResultados === 1 ? 'cliente encontrado' : 'clientes encontrados'
                        }}
                    </p>
                    <p v-if="busqueda || filtroTipoPersona !== 'todos' || filtroEstatus !== 'todos' || displayCategoryFilters"
                        class="mt-1.5 text-xs text-gray-500 dark:text-neutral-400 mb-2">
                        Filtrando por:
                        <span v-if="busqueda" class="font-semibold text-gray-800 dark:text-neutral-200">“{{ busqueda
                            }}”</span>
                        <span v-if="busqueda && (filtroTipoPersona !== 'todos' || filtroEstatus !== 'todos' || displayCategoryFilters)"> · </span>
                        <span v-if="filtroTipoPersona === 'fisica'"
                            class="font-semibold text-gray-800 dark:text-neutral-200">Personas
                            Físicas</span>
                        <span v-else-if="filtroTipoPersona === 'moral'"
                            class="font-semibold text-gray-800 dark:text-neutral-200">Personas Morales</span>
                        <span v-if="(busqueda || filtroTipoPersona !== 'todos') && filtroEstatus !== 'todos'"
                            class="font-semibold text-gray-800 dark:text-neutral-200"> · </span>
                        <span v-if="filtroEstatus === 'activo'"
                            class="font-semibold text-gray-800 dark:text-neutral-200">Activos</span>
                        <span v-else-if="filtroEstatus === 'inactivo'"
                            class="font-semibold text-gray-800 dark:text-neutral-200">Inactivos</span>
                        <span v-if="(busqueda || filtroTipoPersona !== 'todos' || filtroEstatus !== 'todos') && displayCategoryFilters"
                            class="font-semibold text-gray-800 dark:text-neutral-200"> · </span>
                        <span v-if="displayCategoryFilters" class="font-semibold text-gray-800 dark:text-neutral-200">{{
                            displayCategoryFilters }}</span>
                    </p>
                </div>


                <!-- Interactive Controls Block -->
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4 items-start">
                    <!-- Search Input -->
                    <div class="relative w-full md:col-span-2 lg:col-span-2">
                        <span
                            class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-gray-400 dark:text-neutral-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.5 15.5 20 20m-3-9a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                            </svg>
                        </span>
                        <input v-model="busqueda" type="text"
                            class="w-full rounded-lg border border-gray-300/80 bg-gray-50/50 py-2.5 pl-10 pr-3 text-sm text-gray-900 placeholder-gray-400 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:placeholder-neutral-500 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70"
                            placeholder="Buscar por nombre, RFC, CURP..." />
                    </div>

                    <!-- Tipo de persona select -->
                    <div class="flex flex-col gap-2">
                        <select id="filtro-tipo-persona" v-model="filtroTipoPersona"
                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                            <option value="todos">Todas las personas</option>
                            <option value="fisica">Personas Físicas</option>
                            <option value="moral">Personas Morales</option>
                        </select>
                    </div>

                    <!-- Estatus del cliente select -->
                    <div class="flex flex-col gap-2">
                        <select id="filtro-estatus-cliente" v-model="filtroEstatus"
                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                            <option value="todos">Todos los estatus</option>
                            <option value="activo">Activos</option>
                            <option value="inactivo">Inactivos</option>
                        </select>
                    </div>

                    <!-- Categoría PLD checkboxes grouped -->
                    <div class="relative md:col-span-2 lg:col-span-1" ref="categoryDropdownRef">
                        <!-- Dropdown Button -->
                        <button id="pld-category-dropdown-button" ref="dropdownButtonRef"
                            @click="toggleCategoryDropdown" type="button"
                            class="flex w-full items-center justify-between rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                            <span>Categoría PLD</span>
                            <span v-if="selectedCategoryCount > 0"
                                class="ml-2 flex h-5 w-5 items-center justify-center bg-blue-500 text-white rounded-full text-[10px] font-bold">
                                {{ selectedCategoryCount }}
                            </span>
                            <svg class="h-4 w-4 text-gray-400 dark:text-neutral-500 transition-transform duration-200"
                                :class="{ 'rotate-180': showCategoryDropdown }" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7">
                                </path>
                            </svg>
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
                                    todas</span>
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
                    </div>
                </div>
            </Teleport>

            <!-- Listado de clientes -->
            <div
                class="mt-8 overflow-hidden rounded-2xl border border-gray-200/70 bg-white/60 shadow-xl shadow-gray-200/50 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-2xl dark:shadow-black/20">
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
                            <tr v-if="!clientesFiltrados.length" key="no-results">
                                <td colspan="6"
                                    class="border-t border-dashed border-gray-200/80 px-4 py-16 text-center text-sm text-gray-500 dark:border-neutral-800 dark:text-neutral-400">
                                    No se encontraron clientes con los filtros actuales.
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
                                        :class="cliente.IDTipoPersona === 1 ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'">
                                        {{ cliente.IDTipoPersona === 1 ? 'Física' : 'Moral' }}
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
                    <button @click="prevPage" :disabled="currentPage === 1"
                        class="rounded-lg border border-gray-300/80 bg-white/80 px-4 py-2 text-xs font-medium text-gray-700 shadow-sm transition-all duration-200 ease-out hover:bg-gray-100/80 hover:shadow-md hover:shadow-gray-300/20 disabled:cursor-not-allowed disabled:opacity-50 motion-safe:hover:enabled:scale-105 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:enabled:bg-neutral-800/90">
                        Anterior
                    </button>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-600 dark:text-neutral-300">Página</span>
                        <input type="number" v-model.number="currentPage" min="1" :max="totalPages"
                            class="w-16 rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2 text-center text-xs text-gray-900 outline-none ring-blue-500/50 transition-all duration-150 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70" />
                        <span class="text-xs text-gray-600 dark:text-neutral-300">de {{ totalPages }}</span>
                    </div>
                    <button @click="nextPage" :disabled="currentPage === totalPages"
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
</style>
