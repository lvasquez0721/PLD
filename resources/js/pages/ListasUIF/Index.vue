<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Titulo from '@/components/ui/Titulo.vue';
import { FileText, Settings, Search, Pencil, Trash2, X, Download } from 'lucide-vue-next';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
import { activeTab, setTab } from "../../../scripts/setTab.js";
import { type BreadcrumbItem } from '@/types'
import Input from '@/components/forms/Input.vue';
import DateInput from '@/components/forms/DateInput.vue';
import YearPicker from '@/components/forms/YearPicker.vue';
import PrimaryButton from '@/components/forms/PrimaryButton.vue';
import Toast from '@/components/ui/alert/Toast.vue';
import InputError from '@/components/InputError.vue';
import ModalForm from '@/components/ui/modals/modalForm.vue';
import ModalConfirm from '@/components/ui/modals/modalConfirm.vue';

interface ListaUIF {
    IDRegistroListaUIF: number;
    Nombre: string;
    RFC: string;
    CURP: string;
    FechaNacimiento: string;
    FechaPubAcuerdo: string;
    Acuerdo: string;
    NoOficioUIF: string;
    AnioLista: number;
}

const props = defineProps<{
    listasUIF: ListaUIF[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Alta en listas UIF',
        href: '',
    },
]

const page = usePage();

// Formulario reactivo con useForm
const form = useForm({
    IDRegistroListaUIF: null as number | null,
    nombre: '',
    RFCCURP: '',
    fechaNacimiento: '',
    fechaPublicacionAcuerdo: '',
    acuerdo: '',
    noOficioUIF: '',
    anioLista: '',
});

const buscar = ref('');

// Variables para Modales
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const itemToDelete = ref<ListaUIF | null>(null);

// Variables para Toast
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref<'success' | 'warning' | 'error'>('success');

// Escuchar cambios en el prop toast del servidor
watch(() => (page.props as any).toast, (newToast) => {
    if (newToast) {
        toastMessage.value = newToast.message;
        toastType.value = newToast.type;
        showToast.value = true;
    }
}, { immediate: true });

watch(activeTab, (newTab) => {
    if (newTab === 'altaListas') {
        form.reset();
        fechaNacimientoTemp.value = '';
        fechaAcuerdoTemp.value = '';
    }
});

// Fechas reactivas (como strings para DateInput tipo nativo)
// Se manejan dentro del form directamente o como temporales
const fechaNacimientoTemp = ref('');
const fechaAcuerdoTemp = ref('');

// Formateo dd-MM-yyyy para el envío a la API si es necesario,
// aunque DateInput nativo usa yyyy-MM-dd
const formatFechaParaEnvio = (fechaStr: string) => {
    if (!fechaStr) return '';
    const [anio, mes, dia] = fechaStr.split('-');
    return `${dia}-${mes}-${anio}`;
};

// Formateo YYYY-MM-DD para el input nativo desde el formato de la DB
const formatFechaParaInput = (fechaStr: string | null) => {
    if (!fechaStr) return '';
    // Si viene como ISO string 2023-10-27T00:00:00.000000Z o 2023-10-27 00:00:00
    // Extraemos solo la parte de la fecha (YYYY-MM-DD)
    return fechaStr.split(/[T ]/)[0];
};

// Función submit para guardar (Nuevo Registro)
const guardar = () => {
    form.IDRegistroListaUIF = null;
    form.fechaNacimiento = formatFechaParaEnvio(fechaNacimientoTemp.value);
    form.fechaPublicacionAcuerdo = formatFechaParaEnvio(fechaAcuerdoTemp.value);

    form.post('/listas-uif/altaListas', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            fechaNacimientoTemp.value = '';
            fechaAcuerdoTemp.value = '';
        },
        onError: (errors) => {
            console.error('Error al guardar los datos:', errors);
            toastMessage.value = 'Error al validar el formulario. Por favor, revise los campos marcados.';
            toastType.value = 'error';
            showToast.value = true;
        }
    });
};

// Función para abrir modal de edición
const openEditModal = (item: ListaUIF) => {
    form.clearErrors();
    form.IDRegistroListaUIF = item.IDRegistroListaUIF;
    form.nombre = item.Nombre;
    form.RFCCURP = item.RFC || item.CURP;
    form.acuerdo = item.Acuerdo || '';
    form.noOficioUIF = item.NoOficioUIF;
    form.anioLista = item.AnioLista.toString();

    // Formatear fechas de la DB (YYYY-MM-DD) para el input nativo
    fechaNacimientoTemp.value = item.FechaNacimiento ? formatFechaParaInput(item.FechaNacimiento) : '';
    fechaAcuerdoTemp.value = item.FechaPubAcuerdo ? formatFechaParaInput(item.FechaPubAcuerdo) : '';

    showEditModal.value = true;
};

// Función submit para actualizar
const actualizar = () => {
    form.fechaNacimiento = formatFechaParaEnvio(fechaNacimientoTemp.value);
    form.fechaPublicacionAcuerdo = formatFechaParaEnvio(fechaAcuerdoTemp.value);

    form.post('/listas-uif/actualizaListas', {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            form.reset();
            fechaNacimientoTemp.value = '';
            fechaAcuerdoTemp.value = '';
        },
        onError: (errors) => {
            console.error('Error al actualizar los datos:', errors);
        }
    });
};

// Función para abrir modal de eliminación
const openDeleteModal = (item: ListaUIF) => {
    itemToDelete.value = item;
    showDeleteModal.value = true;
};

// Función para eliminar
const eliminar = () => {
    if (!itemToDelete.value) return;

    form.IDRegistroListaUIF = itemToDelete.value.IDRegistroListaUIF;

    form.post('/listas-uif/bajaListas', {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            itemToDelete.value = null;
            form.reset();
        },
        onError: (errors) => {
            console.error('Error al eliminar el registro:', errors);
        }
    });
};

const consultar = () => {
    router.get('/listas-uif/consultaListas', {
        nombre: buscar.value,
        RFCCURP: buscar.value
    }, {
        preserveState: true,
        onSuccess: (page) => {
            console.log('Datos consultados exitosamente');
        }
    });
};

const descargarCSV = () => {
    const url = `/listas-uif/exportar?nombre=${encodeURIComponent(buscar.value)}`;
    window.location.href = url;
};

/* ===== LÓGICA DE LA TABLA (DATATABLE) ===== */
const isLoading = ref(true);
onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 800);
});

const perPage = ref(10);
const currentPage = ref(1);
const goToPageInput = ref(currentPage.value);

function normalize(text: string): string {
    return text ? text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') : '';
}

const filteredListasUIF = computed(() => {
    if (!buscar.value) return props.listasUIF;
    const term = normalize(buscar.value);
    return props.listasUIF.filter(item => {
        const nombre = normalize(item?.Nombre || '');
        const rfc = normalize(item?.RFC || '');
        const curp = normalize(item?.CURP || '');
        return (nombre.includes(term) || rfc.includes(term) || curp.includes(term));
    });
});

const paginatedListasUIF = computed(() => {
    if (perPage.value === -1) return filteredListasUIF.value;
    const start = (currentPage.value - 1) * perPage.value;
    return filteredListasUIF.value.slice(start, start + perPage.value);
});

const totalPages = computed(() => {
    if (perPage.value === -1) return 1;
    return Math.ceil(filteredListasUIF.value.length / perPage.value);
});

function nextPage() { if (currentPage.value < totalPages.value) currentPage.value++; }
function prevPage() { if (currentPage.value > 1) currentPage.value--; }

function handleGoToPage() {
    const pageNum = Math.floor(Number(goToPageInput.value));
    if (isNaN(pageNum) || pageNum < 1 || pageNum > totalPages.value) {
        goToPageInput.value = currentPage.value;
    } else {
        currentPage.value = pageNum;
    }
}

watch(currentPage, (newPage) => {
    goToPageInput.value = newPage;
});

watch([buscar, perPage], () => (currentPage.value = 1));

const showingMessage = computed(() => {
    if (isLoading.value || !filteredListasUIF.value.length) return '';
    const total = filteredListasUIF.value.length;
    if (perPage.value === -1) return `Mostrando todos los ${total.toLocaleString()} registros.`;
    const start = (currentPage.value - 1) * perPage.value + 1;
    const end = Math.min(start + perPage.value - 1, total);
    return `Mostrando ${start.toLocaleString()} a ${end.toLocaleString()} de un total de ${total.toLocaleString()} registros.`;
});


</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
        <div class="relative">

        <!-- Tabs -->
        <div
            class="flex gap-1 mb-8 p-1 rounded-xl border border-slate-100 bg-gradient-to-r from-white via-slate-50/70 to-white shadow-sm backdrop-blur-sm dark:border-neutral-800 dark:bg-gradient-to-r dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90 w-fit transition-all duration-700">
            <button @click="setTab('altaListas')" :class="[
                'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 ease-out',
                activeTab === 'altaListas'
                    ? 'bg-blue-600 text-white shadow-md'
                    : 'text-slate-700 dark:text-neutral-300 hover:bg-slate-100 dark:hover:bg-neutral-800'
            ]">
                Alta de listas
            </button>
            <button @click="setTab('consulta')" :class="[
                'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 ease-out',
                activeTab === 'consulta'
                    ? 'bg-blue-600 text-white shadow-md'
                    : 'text-slate-700 dark:text-neutral-300 hover:bg-slate-100 dark:hover:bg-neutral-800'
            ]">
                Consulta de listas
            </button>
        </div>

        <!-- Alta de listas -->
        <div
            v-if="activeTab === 'altaListas'"
            class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <form @submit.prevent="guardar" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <Input
                            v-model="form.nombre"
                            id="nombre"
                            label="Nombre / Razón social:"
                            placeholder="Ingrese el nombre o razón social"
                            icon="user"
                        />
                        <InputError :message="form.errors.nombre" />
                    </div>
                    <div class="space-y-1">
                        <Input
                            v-model="form.RFCCURP"
                            id="rfc"
                            label="RFC / CURP:"
                            placeholder="Ingrese RFC o CURP"
                            icon="search"
                        />
                        <InputError :message="form.errors.RFCCURP" />
                    </div>
                    <div class="space-y-1">
                        <DateInput
                            v-model="fechaNacimientoTemp"
                            id="fecha-nac"
                            label="Fecha nacimiento:"
                        />
                        <InputError :message="form.errors.fechaNacimiento" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <DateInput
                            v-model="fechaAcuerdoTemp"
                            id="fecha-acuerdo"
                            label="Fecha publicación acuerdo:"
                        />
                        <InputError :message="form.errors.fechaPublicacionAcuerdo" />
                    </div>
                    <div class="space-y-1">
                        <Input
                            v-model="form.acuerdo"
                            id="acuerdo"
                            label="Acuerdo:"
                            placeholder="Ingrese el número de acuerdo"
                            icon="check"
                        />
                        <InputError :message="form.errors.acuerdo" />
                    </div>
                    <div class="space-y-1">
                        <Input
                            v-model="form.noOficioUIF"
                            id="no-oficio"
                            label="No Oficio UIF:"
                            placeholder="Ingrese número de oficio"
                            icon="phone"
                        />
                        <InputError :message="form.errors.noOficioUIF" />
                    </div>

                    <div class="space-y-1">
                        <YearPicker
                            v-model="form.anioLista"
                            id="anio-lista"
                            label="Año Lista:"
                            placeholder="Ingrese año de la lista"
                            icon="calendar"
                        />
                        <InputError :message="form.errors.anioLista" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <PrimaryButton type="submit" label="Guardar" :icon="FileText" :disabled="form.processing" />
                </div>
            </form>
        </div>
        <!-- Consulta -->
        <div
            v-if="activeTab === 'consulta'"
            class="space-y-6">

            <!-- FILTROS -->
            <div class="mt-6 flex flex-col gap-4 rounded-xl border border-slate-100 bg-gradient-to-r from-white/90 via-slate-50/70 to-white/90 p-4 shadow-sm backdrop-blur-sm transition-colors duration-200 ease-out focus-within:border-blue-400/80 focus-within:shadow-[0_0_0_1px_rgba(59,130,246,0.3)] md:flex-row md:items-end md:justify-start dark:border-neutral-800/80 dark:bg-gradient-to-r dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90">
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
                <div class="relative w-72 flex flex-col">
                    <label class="text-xs text-slate-600 dark:text-neutral-300 mb-1 block">Buscar una persona</label>
                    <div class="relative">
                        <input
                            v-model="buscar"
                            :disabled="isLoading"
                            type="text"
                            placeholder="Nombre, RFC o CURP"
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder-slate-400 shadow-inner outline-none ring-0 transition-all duration-150 focus:border-blue-500 focus:bg-white focus:shadow-[0_0_0_1px_rgba(59,130,246,0.35)] dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:placeholder-neutral-500 dark:focus:bg-neutral-900"
                        />
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 flex items-center justify-center h-5 w-5 text-slate-400">
                            <Search class="h-4 w-4" />
                        </span>
                    </div>
                </div>

                <!-- Botón Exportar CSV -->
                <div class="flex items-end">
                    <button
                        @click="descargarCSV"
                        :disabled="isLoading || filteredListasUIF.length === 0"
                        class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 transition-all duration-200 hover:bg-emerald-100 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-900/30 dark:bg-emerald-900/20 dark:text-emerald-400 dark:hover:bg-emerald-900/30"
                    >
                        <Download :size="18" />
                        Exportar CSV
                    </button>
                </div>
            </div>

            <!-- TABLA -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)]">
                <div class="p-4 flex items-center justify-between border-b border-slate-100 dark:border-neutral-800">
                    <div v-if="showingMessage" class="text-xs text-slate-500 dark:text-neutral-400">{{ showingMessage }}</div>
                </div>
                <div class="max-h-[28rem] overflow-y-auto">
                    <table class="min-w-full border-collapse text-sm text-slate-900 dark:text-white">
                        <thead>
                            <tr class="sticky top-0 z-10 bg-gradient-to-r from-slate-50 via-slate-50/95 to-blue-50/60 text-xs font-semibold uppercase tracking-wide text-slate-700 backdrop-blur-sm dark:bg-gradient-to-r dark:from-neutral-900/95 dark:via-neutral-900/95 dark:to-slate-900/95 dark:text-neutral-200">
                                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">#</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Nombre</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">RFC / CURP</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Año</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Acuerdo</th>
                                <th class="border-b border-slate-200 px-3 py-2 text-center align-middle text-[11px] font-semibold dark:border-neutral-800">Acciones</th>
                            </tr>
                        </thead>
                        <tbody v-if="paginatedListasUIF.length" class="table-body">
                            <tr v-for="item in paginatedListasUIF" :key="item.IDRegistroListaUIF" class="group cursor-pointer border-b border-l-2 border-slate-100 border-l-transparent bg-white transition-all duration-200 ease-out hover:-translate-y-[1px] hover:border-l-blue-400 hover:bg-gradient-to-r hover:from-white hover:via-slate-50/80 hover:to-blue-50/40 hover:shadow-[0_10px_30px_rgba(15,23,42,0.08)] dark:border-neutral-800/60 dark:border-l-transparent dark:bg-neutral-950/40 dark:hover:border-l-blue-500 dark:hover:bg-gradient-to-r dark:hover:from-neutral-950/90 dark:hover:via-neutral-900/90 dark:hover:to-slate-800/90 dark:hover:shadow-[0_18px_40px_rgba(0,0,0,0.75)]">
                                <td class="px-3 py-2 align-middle">{{ item.IDRegistroListaUIF }}</td>
                                <td class="px-3 py-2 align-middle font-medium">{{ item.Nombre }}</td>
                                <td class="px-3 py-2 align-middle">
                                    <div class="flex flex-col">
                                        <span class="text-xs text-slate-500 dark:text-neutral-400">RFC: {{ item.RFC || 'N/A' }}</span>
                                        <span class="text-xs text-slate-500 dark:text-neutral-400">CURP: {{ item.CURP || 'N/A' }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-middle">{{ item.AnioLista }}</td>
                                <td class="px-3 py-2 align-middle text-xs">{{ item.Acuerdo }}</td>
                                <td class="px-3 py-2 align-middle">
                                    <div class="flex items-center justify-center gap-2">
                                        <button @click.stop="openEditModal(item)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors dark:text-blue-400 dark:hover:bg-blue-900/30">
                                            <Pencil :size="16" />
                                        </button>
                                        <button @click.stop="openDeleteModal(item)" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors dark:text-red-400 dark:hover:bg-red-900/30">
                                            <Trash2 :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500 dark:text-neutral-400">
                                    <div v-if="isLoading" class="flex flex-col items-center gap-2">
                                        <svg class="animate-spin h-5 w-5 text-blue-600" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8z"/>
                                        </svg>
                                        <span>Cargando datos...</span>
                                    </div>
                                    <div v-else>
                                        No se encontraron registros para "{{ buscar }}"
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PAGINACIÓN -->
            <div class="mt-4 flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-100 bg-gradient-to-r from-white via-slate-50/70 to-white p-3 text-slate-900 shadow-sm backdrop-blur-sm sm:flex-row sm:items-center sm:gap-4 dark:border-neutral-800 dark:bg-gradient-to-r dark:from-neutral-950/95 dark:via-neutral-900/90 dark:to-neutral-950/95 dark:text-white">
                <p class="text-xs text-slate-500 dark:text-neutral-400">{{ showingMessage }}</p>
                <div class="flex items-center space-x-2">
                    <button @click="prevPage" :disabled="currentPage === 1"
                        class="rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-slate-700 shadow-sm transition-all duration-150 ease-out hover:-translate-y-[1px] hover:bg-slate-50 hover:shadow-md disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:bg-neutral-800/90">
                        Anterior
                    </button>
                    <span class="text-xs text-slate-600 dark:text-neutral-300">Página</span>
                    <input type="number" v-model.number="goToPageInput" @change="handleGoToPage" min="1" :max="totalPages"
                        class="w-16 rounded-lg border border-slate-300 bg-white px-3 py-2 text-center text-xs text-slate-900 outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900" />
                    <span class="text-xs text-slate-600 dark:text-neutral-300">de {{ totalPages }}</span>
                    <button @click="nextPage" :disabled="currentPage === totalPages"
                        class="rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-slate-700 shadow-sm transition-all duration-150 ease-out hover:-translate-y-[1px] hover:bg-slate-50 hover:shadow-md disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:bg-neutral-800/90">
                        Siguiente
                    </button>
                </div>
            </div>
        </div>

        <Toast v-model="showToast" :message="toastMessage" :type="toastType" />

        <!-- MODAL DE EDICIÓN -->
        <ModalForm v-model="showEditModal" title="Editar Registro UIF" subtitle="Actualice los datos del registro seleccionado">
            <form @submit.prevent="actualizar" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <Input v-model="form.nombre" id="edit-nombre" label="Nombre / Razón social:" icon="user" />
                        <InputError :message="form.errors.nombre" />
                    </div>
                    <div class="space-y-1">
                        <Input v-model="form.RFCCURP" id="edit-rfc" label="RFC / CURP:" icon="search" />
                        <InputError :message="form.errors.RFCCURP" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <DateInput v-model="fechaNacimientoTemp" id="edit-fecha-nac" label="Fecha nacimiento:" />
                        <InputError :message="form.errors.fechaNacimiento" />
                    </div>
                    <div class="space-y-1">
                        <DateInput v-model="fechaAcuerdoTemp" id="edit-fecha-acuerdo" label="Fecha publicación acuerdo:" />
                        <InputError :message="form.errors.fechaPublicacionAcuerdo" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <Input v-model="form.acuerdo" id="edit-acuerdo" label="Acuerdo:" icon="check" />
                        <InputError :message="form.errors.acuerdo" />
                    </div>
                    <div class="space-y-1">
                        <Input v-model="form.noOficioUIF" id="edit-no-oficio" label="No Oficio UIF:" icon="phone" />
                        <InputError :message="form.errors.noOficioUIF" />
                    </div>
                    <div class="space-y-1">
                        <YearPicker v-model="form.anioLista" id="edit-anio-lista" label="Año Lista:" icon="calendar" />
                        <InputError :message="form.errors.anioLista" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showEditModal = false" class="px-5 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition-colors dark:bg-neutral-900 dark:text-neutral-300 dark:border-neutral-700 dark:hover:bg-neutral-800">
                        Cancelar
                    </button>
                    <PrimaryButton type="submit" label="Actualizar" :icon="Pencil" :disabled="form.processing" />
                </div>
            </form>
        </ModalForm>

        <!-- MODAL DE ELIMINACIÓN -->
        <ModalConfirm v-model="showDeleteModal" @confirm="eliminar" @cancel="showDeleteModal = false" :loading="form.processing">
            <template #icon>
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <Trash2 class="h-6 w-6 text-red-600 dark:text-red-400" />
                </div>
            </template>
            <template #title>
                ¿Eliminar registro?
            </template>
            <template #description>
                ¿Está seguro de que desea eliminar a <span class="font-semibold text-slate-900 dark:text-white">{{ itemToDelete?.Nombre }}</span>? Esta acción no se puede deshacer.
            </template>
            <template #confirm>
                {{ form.processing ? 'Eliminando...' : 'Eliminar' }}
            </template>
        </ModalConfirm>
        </div>
        </FadeIn>
    </AppLayout>
</template>
