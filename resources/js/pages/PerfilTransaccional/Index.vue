<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

// Componentes
import AppLayout from '@/layouts/AppLayout.vue'
import Titulo from '@/components/ui/Titulo.vue'
import { Users, FileSpreadsheet, Search, Loader2, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { type BreadcrumbItem } from '@/types';
import { buzonPreocupantes } from '@/routes/index.js';
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Perfil transaccional',
        href: buzonPreocupantes().url,
    },
];

const page = usePage()

// -----------------------------------------
// PROPS
// -----------------------------------------
interface Campo {
    IDCampo: number
    IDModulo: number
    Seccion: number
    Tipo: string
    NombreCampo: string
    EtiquetaCampo?: string | null
    Placeholder?: string | null
    Requerido?: number | boolean
    Visible?: number | boolean
    Value?: string | null
}

const props = defineProps<{
    campos: Campo[]
    periodos: { FechaEjecucción: string; PeriodoFormateado: string }[]
}>()

// -----------------------------------------
// ESTADOS
// -----------------------------------------
const loading = ref(false)
const formRegistrar = ref<Record<string, any>>({})
const resultados = ref<any[]>([])
const csvUrl = ref('')
const filtroNombre = ref('')

// -----------------------------------------
// PAGINACIÓN
// -----------------------------------------
const currentPage = ref(1)
const itemsPerPage = ref(10)

// Resetear página cuando cambian los resultados o el filtro
watch([resultados, filtroNombre], () => {
    currentPage.value = 1
})

const totalPages = computed(() => {
    return Math.ceil(resultadosFiltrados.value.length / itemsPerPage.value)
})

const paginatedResults = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value
    const end = start + itemsPerPage.value
    return resultadosFiltrados.value.slice(start, end)
})

const startIndex = computed(() => {
    return resultadosFiltrados.value.length === 0
        ? 0
        : (currentPage.value - 1) * itemsPerPage.value + 1
})

const endIndex = computed(() => {
    const end = currentPage.value * itemsPerPage.value
    return end > resultadosFiltrados.value.length ? resultadosFiltrados.value.length : end
})

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page
        // Scroll suave hacia la tabla
        const tableElement = document.querySelector('.overflow-x-auto')
        if (tableElement) {
            tableElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
        }
    }
}

const getPageNumbers = computed(() => {
    const pages: (number | string)[] = []
    const total = totalPages.value
    const current = currentPage.value

    if (total <= 7) {
        // Mostrar todas las páginas si son 7 o menos
        for (let i = 1; i <= total; i++) {
            pages.push(i)
        }
    } else {
        // Lógica para mostrar páginas con elipsis
        if (current <= 3) {
            // Inicio: 1, 2, 3, 4, ..., total
            for (let i = 1; i <= 4; i++) {
                pages.push(i)
            }
            pages.push('...')
            pages.push(total)
        } else if (current >= total - 2) {
            // Final: 1, ..., total-3, total-2, total-1, total
            pages.push(1)
            pages.push('...')
            for (let i = total - 3; i <= total; i++) {
                pages.push(i)
            }
        } else {
            // Medio: 1, ..., current-1, current, current+1, ..., total
            pages.push(1)
            pages.push('...')
            for (let i = current - 1; i <= current + 1; i++) {
                pages.push(i)
            }
            pages.push('...')
            pages.push(total)
        }
    }

    return pages
})

// -----------------------------------------
// MODALES
// -----------------------------------------
const showModalRegistrar = ref(false)
const showModalEjecutar = ref(false)

const openModalRegistrar = () => (showModalRegistrar.value = true)
const closeModalRegistrar = () => {
    showModalRegistrar.value = false
    formRegistrar.value = {}
}

const openModalEjecutar = () => (showModalEjecutar.value = true)
const closeModalEjecutar = () => (showModalEjecutar.value = false)

// -----------------------------------------
// ALERTAS (FLASH + LOCAL)
// -----------------------------------------
const flashSuccess = computed(() => (page.props as any).flash?.success || null)
const flashError = computed(() => (page.props as any).flash?.error || null)

const alertaLocal = ref<null | { tipo: 'success' | 'error'; mensaje: string }>(null)
const mostrarFlash = ref(false)
let flashTimeout: number | null = null

const mostrarAlerta = (tipo: 'success' | 'error', mensaje: string) => {
    alertaLocal.value = { tipo, mensaje }
    mostrarFlash.value = true

    if (flashTimeout) clearTimeout(flashTimeout)
    flashTimeout = window.setTimeout(() => {
        mostrarFlash.value = false
        alertaLocal.value = null
    }, 5000)
}

const modalFlashTitle = computed(() => {
    if (alertaLocal.value?.tipo === 'error') return 'Error'
    if (alertaLocal.value?.tipo === 'success') return 'Éxito'
    if (flashError.value) return 'Error'
    if (flashSuccess.value) return 'Éxito'
    return ''
})

watch([flashSuccess, flashError], () => {
    if (flashSuccess.value || flashError.value) {
        mostrarFlash.value = true
        if (flashTimeout) clearTimeout(flashTimeout)
        flashTimeout = window.setTimeout(() => (mostrarFlash.value = false), 5000)
    }
})

// -----------------------------------------
// GUARDAR PERFIL
// -----------------------------------------
const submitRegistrar = () => {
    if (loading.value) return
    loading.value = true

    //console.log("Datos enviados al Registrar Perfil:", JSON.parse(JSON.stringify(formRegistrar.value)))

    router.post('/perfil-transaccional/insert', formRegistrar.value, {
        preserveScroll: true,
        onSuccess: () => {
            // El mensaje se mostrará automáticamente desde el watch
            closeModalRegistrar()
        },
        onError: (errors) => {
            console.error(errors)
            mostrarAlerta('error', 'No se pudo guardar el perfil.')
        },
        onFinish: () => {
            loading.value = false
        },
    })
}

// -----------------------------------------
// BUSCAR INFORMACIÓN
// -----------------------------------------
const buscarInformacion = async () => {
    if (loading.value) return
    loading.value = true

    if (!formRegistrar.value['Periodo']) {
        mostrarAlerta('error', 'Seleccione un periodo antes de continuar.')
        loading.value = false
        return
    }

    try {
        const { data } = await axios.post('/perfil-transaccional/buscar', {
            Periodo: formRegistrar.value['Periodo'],
        })
        //console.log('Datos recibidos desde Laravel:', data) // imprime todo lo que llega
        if (data.success) {
            resultados.value = data.datos
            csvUrl.value = data.csvUrl
            mostrarAlerta('success', data.mensaje)
        } else {
            mostrarAlerta('error', data.mensaje || 'No se encontraron registros.')
        }
    } catch (e: any) {
        mostrarAlerta('error', e.response?.data?.mensaje || 'Error al consultar información.')
    }

    loading.value = false
}

// -----------------------------------------
// Filtrar resultados por nombre
// -----------------------------------------
const resultadosFiltrados = computed(() => {
    if (!filtroNombre.value) return resultados.value

    const texto = filtroNombre.value.toLowerCase()

    return resultados.value.filter((f: any) =>
        `${f.Nombre ?? ''} ${f.ApellidoPaterno ?? ''} ${f.ApellidoMaterno ?? ''}`
            .toLowerCase()
            .includes(texto)
    )
})

// -----------------------------------------
// EJECUTAR PERFIL
// -----------------------------------------
const confirmarEjecutar = async () => {
    closeModalEjecutar()
    loading.value = true

    router.post('/perfil-transaccional/ejecutar', {}, {
        preserveScroll: true,
        onSuccess: () => {
            mostrarAlerta('success', 'Perfil transaccional ejecutado correctamente.')
        },
        onError: () => {
            mostrarAlerta('error', 'No se pudo ejecutar el perfil.')
        },
        onFinish: () => {
            loading.value = false
        },
    })
}

// -----------------------------------------
// TÍTULOS DE SECCIÓN
// -----------------------------------------
const getTituloSeccion = (index: number) => {
    switch (index) {
        case 0:
            return 'Física'
        case 3:
            return 'Moral'
        case 5:
            return 'Datos complementarios'
        default:
            return null
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Header Section -->
        <!-- <div class="mb-8">
      <Titulo :icon="Users" title="Perfil Transaccional" size="md" weight="bold" />
    </div> -->

        <!-- Alert Banner -->
        <transition name="fade-slide">
            <div v-if="mostrarFlash && modalFlashTitle"
                class="mb-6 rounded-xl border px-4 py-3.5 shadow-lg backdrop-blur-sm" :class="alertaLocal?.tipo === 'success' || flashSuccess
                        ? 'border-green-200/80 bg-gradient-to-r from-green-50/95 to-green-50/80 text-green-900 shadow-green-100/50 dark:border-green-800/60 dark:from-green-950/40 dark:to-green-950/20 dark:text-green-100 dark:shadow-green-950/30'
                        : 'border-red-200/80 bg-gradient-to-r from-red-50/95 to-red-50/80 text-red-900 shadow-red-100/50 dark:border-red-800/60 dark:from-red-950/40 dark:to-red-950/20 dark:text-red-100 dark:shadow-red-950/30'
                    ">
                <div class="flex items-start gap-3">
                    <div class="flex-1">
                        <p class="font-semibold tracking-tight">{{ modalFlashTitle }}</p>
                        <p class="mt-1 text-sm opacity-90 leading-relaxed">
                            {{ alertaLocal?.mensaje || flashSuccess || flashError }}
                        </p>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Primary Actions -->
        <div class="mb-8 flex flex-wrap items-center gap-3">
            <button @click="openModalRegistrar"
                class="group inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-all duration-200 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl hover:shadow-blue-500/30 hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 dark:from-blue-500 dark:to-blue-600 dark:shadow-blue-500/20 dark:hover:from-blue-600 dark:hover:to-blue-700 dark:focus:ring-blue-400 dark:focus:ring-offset-neutral-950">
                <span class="relative">Registrar Perfil</span>
            </button>

            <button @click="openModalEjecutar"
                class="group inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300/80 bg-white px-6 py-3 text-sm font-semibold text-gray-700 shadow-md shadow-gray-200/50 transition-all duration-200 hover:border-gray-400/80 hover:bg-gradient-to-br hover:from-gray-50 hover:to-white hover:shadow-lg hover:shadow-gray-300/30 hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 dark:border-neutral-700/80 dark:bg-neutral-950 dark:text-neutral-200 dark:shadow-neutral-900/50 dark:hover:border-neutral-600 dark:hover:from-neutral-900 dark:hover:to-neutral-950 dark:hover:shadow-neutral-800/30 dark:focus:ring-blue-400 dark:focus:ring-offset-neutral-950">
                <span class="relative">Ejecutar Perfil Transaccional</span>
            </button>
        </div>

        <!-- Main Content Container -->
        <div
            class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-xl shadow-gray-200/50 dark:border-neutral-800/80 dark:bg-neutral-950 dark:shadow-neutral-900/50">
            <!-- Section Header -->
            <div
                class="border-b border-gray-200/60 bg-gradient-to-r from-gray-50/95 to-gray-50/80 px-6 py-4.5 backdrop-blur-sm dark:border-neutral-800/60 dark:from-neutral-900/95 dark:to-neutral-900/80">
                <h2 class="text-base font-semibold tracking-tight text-gray-900 dark:text-neutral-100">
                    Consulta de información
                </h2>
            </div>

            <!-- Content Area -->
            <div class="p-6">
                <!-- Filters Section -->
                <div
                    class="mb-6 flex flex-col gap-4 border-b border-gray-200/60 pb-6 dark:border-neutral-800/60 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                        <label class="text-sm font-semibold tracking-tight text-gray-700 dark:text-neutral-300 sm:w-20">
                            Periodo:
                        </label>
                        <select v-model="formRegistrar['Periodo']"
                            class="flex-1 rounded-xl border border-gray-300/80 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm transition-all duration-200 hover:border-gray-400/80 hover:shadow-md focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-0 disabled:bg-gray-50 disabled:text-gray-500 dark:border-neutral-700/80 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:border-neutral-600 dark:focus:border-blue-400 dark:focus:ring-blue-400/50 dark:disabled:bg-neutral-900">
                            <option value="">Seleccione un periodo</option>
                            <option v-for="(p, i) in props.periodos" :key="i" :value="p.FechaEjecucción">
                                {{ p.PeriodoFormateado }}
                            </option>
                        </select>
                    </div>

                    <button @click="buscarInformacion" :disabled="loading"
                        class="group inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-all duration-200 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl hover:shadow-blue-500/30 hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 dark:from-blue-500 dark:to-blue-600 dark:shadow-blue-500/20 dark:hover:from-blue-600 dark:hover:to-blue-700 dark:focus:ring-blue-400 dark:focus:ring-offset-neutral-950">
                        <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                        <Search v-else class="h-4 w-4 transition-transform group-hover:scale-110" />
                        Buscar Información
                    </button>
                </div>

                <!-- Search Input -->
                <div v-if="resultados.length" class="mb-6">
                    <div class="relative">
                        <input v-model="filtroNombre" type="text" placeholder="Buscar por nombre del cliente"
                            class="w-full rounded-xl border border-gray-300/80 bg-white px-4 py-3 pr-11 text-sm text-gray-900 shadow-sm transition-all duration-200 placeholder:text-gray-400 hover:border-gray-400/80 hover:shadow-md focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-0 dark:border-neutral-700/80 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder:text-neutral-500 dark:hover:border-neutral-600 dark:focus:border-blue-400 dark:focus:ring-blue-400/50" />
                        <Search
                            class="absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 transition-colors dark:text-neutral-500" />
                    </div>
                </div>

                <!-- Results Section -->
                <div v-if="resultados.length" class="overflow-hidden">
                    <!-- Results Header -->
                    <div
                        class="mb-4 flex items-center justify-between border-b border-gray-200/60 pb-3 dark:border-neutral-800/60">
                        <div class="flex items-center gap-2.5">
                            <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-neutral-100">
                                Resultados
                            </h3>
                            <span
                                class="rounded-full bg-gradient-to-r from-gray-100 to-gray-50 px-3 py-1 text-xs font-semibold text-gray-700 shadow-sm dark:from-neutral-800 dark:to-neutral-900 dark:text-neutral-300">
                                {{ resultadosFiltrados.length }}
                            </span>
                        </div>
                        <a :href="csvUrl" target="_blank"
                            class="group inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold text-green-700 transition-all duration-200 hover:bg-green-50 hover:text-green-800 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500/50 focus:ring-offset-2 dark:text-green-400 dark:hover:bg-green-950/30 dark:hover:text-green-300 dark:focus:ring-green-400/50 dark:focus:ring-offset-neutral-950">
                            <FileSpreadsheet class="h-4 w-4 transition-transform group-hover:scale-110" />
                            Descargar CSV
                        </a>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto rounded-xl">
                        <table class="min-w-full divide-y divide-gray-200/60 dark:divide-neutral-800/60">
                            <thead
                                class="bg-gradient-to-r from-gray-50/95 to-gray-50/80 backdrop-blur-sm dark:from-neutral-900/95 dark:to-neutral-900/80">
                                <tr>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Nombre
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Evaluación Perfil
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Periodo
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200/60 bg-white dark:divide-neutral-800/60 dark:bg-neutral-950">
                                <tr v-for="(fila, index) in paginatedResults" :key="index"
                                    class="transition-all duration-150 hover:bg-gradient-to-r hover:from-gray-50/80 hover:to-gray-50/50 hover:shadow-sm dark:hover:from-neutral-900/50 dark:hover:to-neutral-900/30">
                                    <td
                                        class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-900 dark:text-neutral-100">
                                        {{ fila.Nombre }} {{ fila.ApellidoPaterno }} {{ fila.ApellidoMaterno }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-center">
                                        <span
                                            class="inline-flex items-center justify-center rounded-lg px-3.5 py-1.5 text-sm font-bold shadow-sm transition-all duration-200 hover:scale-105"
                                            :style="{
                                                background: fila.Perfil >= 3
                                                    ? 'linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%)'
                                                    : fila.Perfil == 2
                                                        ? 'linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%)'
                                                        : 'linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%)',
                                                color:
                                                    fila.Perfil >= 3
                                                        ? '#991B1B'
                                                        : fila.Perfil == 2
                                                            ? '#92400E'
                                                            : '#065F46',
                                            }">
                                            {{ fila.Perfil }}
                                        </span>
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-5 py-4 text-right text-sm font-medium text-gray-900 dark:text-neutral-100">
                                        {{ fila.FechaEjecucción }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="resultadosFiltrados.length > itemsPerPage"
                        class="mt-6 flex flex-col items-center justify-between gap-4 border-t border-gray-200/60 pt-6 dark:border-neutral-800/60 sm:flex-row">
                        <!-- Información de registros -->
                        <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-neutral-300">
                            <span class="font-medium">Mostrando</span>
                            <span
                                class="rounded-lg bg-gradient-to-r from-gray-100 to-gray-50 px-2.5 py-1 font-bold text-gray-900 shadow-sm dark:from-neutral-800 dark:to-neutral-900 dark:text-neutral-100">
                                {{ startIndex }}
                            </span>
                            <span class="font-medium">a</span>
                            <span
                                class="rounded-lg bg-gradient-to-r from-gray-100 to-gray-50 px-2.5 py-1 font-bold text-gray-900 shadow-sm dark:from-neutral-800 dark:to-neutral-900 dark:text-neutral-100">
                                {{ endIndex }}
                            </span>
                            <span class="font-medium">de</span>
                            <span
                                class="rounded-lg bg-gradient-to-r from-gray-100 to-gray-50 px-2.5 py-1 font-bold text-gray-900 shadow-sm dark:from-neutral-800 dark:to-neutral-900 dark:text-neutral-100">
                                {{ resultadosFiltrados.length }}
                            </span>
                            <span class="font-medium">registros</span>
                        </div>

                        <!-- Controles de paginación -->
                        <div class="flex items-center gap-2">
                            <!-- Botón Anterior -->
                            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1"
                                class="group inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-300/80 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition-all duration-200 hover:border-gray-400/80 hover:bg-gradient-to-br hover:from-gray-50 hover:to-white hover:shadow-md hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100 disabled:hover:shadow-sm dark:border-neutral-700/80 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:border-neutral-600 dark:hover:from-neutral-900 dark:hover:to-neutral-950 dark:focus:ring-blue-400/50 dark:focus:ring-offset-neutral-950"
                                aria-label="Página anterior">
                                <ChevronLeft class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
                                <span class="hidden sm:inline">Anterior</span>
                            </button>

                            <!-- Números de página -->
                            <div class="flex items-center gap-1">
                                <button v-for="(page, index) in getPageNumbers" :key="index"
                                    @click="typeof page === 'number' ? goToPage(page) : null"
                                    :disabled="typeof page === 'string'" :class="[
                                        'group relative inline-flex items-center justify-center rounded-xl px-3.5 py-2 text-sm font-semibold transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 disabled:cursor-default dark:focus:ring-blue-400/50 dark:focus:ring-offset-neutral-950',
                                        typeof page === 'string'
                                            ? 'cursor-default text-gray-400 dark:text-neutral-600'
                                            : page === currentPage
                                                ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg shadow-blue-500/25 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl hover:shadow-blue-500/30 dark:from-blue-500 dark:to-blue-600 dark:shadow-blue-500/20 dark:hover:from-blue-600 dark:hover:to-blue-700'
                                                : 'border border-gray-300/80 bg-white text-gray-700 shadow-sm hover:border-gray-400/80 hover:bg-gradient-to-br hover:from-gray-50 hover:to-white hover:shadow-md hover:scale-[1.05] active:scale-[0.95] dark:border-neutral-700/80 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:border-neutral-600 dark:hover:from-neutral-900 dark:hover:to-neutral-950'
                                    ]" :aria-label="typeof page === 'number' ? `Ir a página ${page}` : 'Más páginas'"
                                    :aria-current="page === currentPage ? 'page' : undefined">
                                    {{ page }}
                                </button>
                            </div>

                            <!-- Botón Siguiente -->
                            <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages"
                                class="group inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-300/80 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition-all duration-200 hover:border-gray-400/80 hover:bg-gradient-to-br hover:from-gray-50 hover:to-white hover:shadow-md hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100 disabled:hover:shadow-sm dark:border-neutral-700/80 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:border-neutral-600 dark:hover:from-neutral-900 dark:hover:to-neutral-950 dark:focus:ring-blue-400/50 dark:focus:ring-offset-neutral-950"
                                aria-label="Página siguiente">
                                <span class="hidden sm:inline">Siguiente</span>
                                <ChevronRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                            </button>
                        </div>

                        <!-- Selector de items por página -->
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-neutral-300">
                                Por página:
                            </label>
                            <select v-model="itemsPerPage" @change="currentPage = 1"
                                class="rounded-xl border border-gray-300/80 bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 shadow-sm transition-all duration-200 hover:border-gray-400/80 hover:shadow-md focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-0 dark:border-neutral-700/80 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:border-neutral-600 dark:focus:border-blue-400 dark:focus:ring-blue-400/50">
                                <option :value="5">5</option>
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                                <option :value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!resultados.length && !loading" class="py-16 text-center">
                    <div class="mx-auto max-w-sm">
                        <div
                            class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-neutral-800">
                            <Search class="h-6 w-6 text-gray-400 dark:text-neutral-500" />
                        </div>
                        <p class="text-sm font-medium text-gray-500 dark:text-neutral-400">
                            Seleccione un periodo y busque información para ver los resultados.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Registrar Perfil -->
        <transition name="backdrop-fade">
            <div v-if="showModalRegistrar" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="closeModalRegistrar">
                <!-- Backdrop -->
                <div class="modal-backdrop fixed inset-0 bg-black/60" aria-hidden="true" />

                <!-- Modal Container -->
                <transition name="modal-scale">
                    <div v-if="showModalRegistrar"
                        class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-2xl shadow-gray-900/25 dark:border-neutral-800/80 dark:bg-neutral-950 dark:shadow-neutral-950/50"
                        role="dialog" aria-modal="true" aria-labelledby="modal-title">
                        <!-- Header -->
                        <div
                            class="border-b border-gray-200/60 bg-gradient-to-r from-gray-50/95 to-gray-50/80 px-6 py-5 backdrop-blur-sm dark:border-neutral-800/60 dark:from-neutral-900/95 dark:to-neutral-900/80">
                            <div class="flex items-center justify-between">
                                <h2 id="modal-title"
                                    class="text-lg font-semibold tracking-tight text-gray-900 dark:text-neutral-100">
                                    Registrar Perfil
                                </h2>
                                <button @click="closeModalRegistrar" type="button"
                                    class="group rounded-xl p-2 text-gray-400 transition-all duration-200 hover:bg-gray-100 hover:text-gray-600 hover:scale-110 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:hover:bg-neutral-800 dark:hover:text-neutral-300 dark:focus:ring-blue-400/50"
                                    aria-label="Cerrar">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="max-h-[calc(90vh-140px)] overflow-y-auto px-6 py-6">
                            <form @submit.prevent="submitRegistrar" class="space-y-6">
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <template v-for="(campo, index) in props.campos" :key="campo.IDCampo">
                                        <!-- Section Title -->
                                        <div v-if="getTituloSeccion(index)" class="col-span-2">
                                            <h3
                                                class="border-b border-gray-200/60 pb-2.5 text-base font-bold tracking-tight text-gray-900 dark:border-neutral-800/60 dark:text-neutral-100">
                                                {{ getTituloSeccion(index) }}
                                            </h3>
                                        </div>

                                        <!-- Field -->
                                        <div v-show="campo.Visible === 1" class="flex flex-col gap-2">
                                            <label
                                                class="text-sm font-semibold tracking-tight text-gray-700 dark:text-neutral-300">
                                                {{ campo.EtiquetaCampo }}
                                                <span v-if="campo.Requerido === 1"
                                                    class="ml-1 text-red-600 dark:text-red-400" aria-label="requerido">
                                                    *
                                                </span>
                                            </label>

                                            <input v-if="campo.Tipo !== 'select'" :type="campo.Tipo"
                                                v-model="formRegistrar[campo.NombreCampo]"
                                                :placeholder="campo.Placeholder ?? ''" :required="campo.Requerido === 1"
                                                class="rounded-xl border border-gray-300/80 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm transition-all duration-200 placeholder:text-gray-400 hover:border-gray-400/80 hover:shadow-md focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-0 dark:border-neutral-700/80 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder:text-neutral-500 dark:hover:border-neutral-600 dark:focus:border-blue-400 dark:focus:ring-blue-400/50" />

                                            <select v-else v-model="formRegistrar[campo.NombreCampo]"
                                                :required="campo.Requerido === 1"
                                                class="rounded-xl border border-gray-300/80 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm transition-all duration-200 hover:border-gray-400/80 hover:shadow-md focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-0 dark:border-neutral-700/80 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:border-neutral-600 dark:focus:border-blue-400 dark:focus:ring-blue-400/50">
                                                <option value="">Seleccione...</option>
                                                <option v-for="op in campo.Value?.split(',')" :key="op"
                                                    :value="op.trim()">
                                                    {{ op }}
                                                </option>
                                            </select>
                                        </div>
                                    </template>
                                </div>
                            </form>
                        </div>

                        <!-- Footer -->
                        <div
                            class="border-t border-gray-200/60 bg-gradient-to-r from-gray-50/95 to-gray-50/80 px-6 py-4 backdrop-blur-sm dark:border-neutral-800/60 dark:from-neutral-900/95 dark:to-neutral-900/80">
                            <div class="flex justify-end gap-3">
                                <button @click="closeModalRegistrar" type="button"
                                    class="group inline-flex items-center justify-center rounded-xl border border-gray-300/80 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-all duration-200 hover:border-gray-400/80 hover:bg-gradient-to-br hover:from-gray-50 hover:to-white hover:shadow-md hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 dark:border-neutral-700/80 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:border-neutral-600 dark:hover:from-neutral-900 dark:hover:to-neutral-950 dark:focus:ring-blue-400/50 dark:focus:ring-offset-neutral-950">
                                    Cancelar
                                </button>
                                <button @click="submitRegistrar" :disabled="loading"
                                    class="group inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-all duration-200 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl hover:shadow-blue-500/30 hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 dark:from-blue-500 dark:to-blue-600 dark:shadow-blue-500/20 dark:hover:from-blue-600 dark:hover:to-blue-700 dark:focus:ring-blue-400 dark:focus:ring-offset-neutral-950">
                                    Guardar Perfil
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </transition>

        <!-- Modal: Ejecutar Perfil -->
        <transition name="backdrop-fade">
            <div v-if="showModalEjecutar" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="closeModalEjecutar">
                <!-- Backdrop -->
                <div class="modal-backdrop fixed inset-0 bg-black/60" aria-hidden="true" />

                <!-- Modal Container -->
                <transition name="modal-scale">
                    <div v-if="showModalEjecutar"
                        class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-2xl shadow-gray-900/25 dark:border-neutral-800/80 dark:bg-neutral-950 dark:shadow-neutral-950/50"
                        role="dialog" aria-modal="true" aria-labelledby="execute-modal-title">
                        <!-- Header -->
                        <div
                            class="border-b border-gray-200/60 bg-gradient-to-r from-gray-50/95 to-gray-50/80 px-6 py-5 backdrop-blur-sm dark:border-neutral-800/60 dark:from-neutral-900/95 dark:to-neutral-900/80">
                            <div class="flex items-center justify-between">
                                <h2 id="execute-modal-title"
                                    class="text-lg font-semibold tracking-tight text-gray-900 dark:text-neutral-100">
                                    Ejecutar Perfil Transaccional
                                </h2>
                                <button @click="closeModalEjecutar" type="button"
                                    class="group rounded-xl p-2 text-gray-400 transition-all duration-200 hover:bg-gray-100 hover:text-gray-600 hover:scale-110 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:hover:bg-neutral-800 dark:hover:text-neutral-300 dark:focus:ring-blue-400/50"
                                    aria-label="Cerrar">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="px-6 py-6">
                            <p class="text-sm leading-relaxed text-gray-700 dark:text-neutral-300">
                                Se iniciará el proceso del perfil transaccional. Esta acción puede tomar varios minutos.
                            </p>
                        </div>

                        <!-- Footer -->
                        <div
                            class="border-t border-gray-200/60 bg-gradient-to-r from-gray-50/95 to-gray-50/80 px-6 py-4 backdrop-blur-sm dark:border-neutral-800/60 dark:from-neutral-900/95 dark:to-neutral-900/80">
                            <div class="flex justify-end gap-3">
                                <button @click="closeModalEjecutar" type="button"
                                    class="group inline-flex items-center justify-center rounded-xl border border-gray-300/80 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-all duration-200 hover:border-gray-400/80 hover:bg-gradient-to-br hover:from-gray-50 hover:to-white hover:shadow-md hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 dark:border-neutral-700/80 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:border-neutral-600 dark:hover:from-neutral-900 dark:hover:to-neutral-950 dark:focus:ring-blue-400/50 dark:focus:ring-offset-neutral-950">
                                    Cancelar
                                </button>
                                <button @click="confirmarEjecutar" :disabled="loading"
                                    class="group inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-all duration-200 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl hover:shadow-blue-500/30 hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 dark:from-blue-500 dark:to-blue-600 dark:shadow-blue-500/20 dark:hover:from-blue-600 dark:hover:to-blue-700 dark:focus:ring-blue-400 dark:focus:ring-offset-neutral-950">
                                    Aceptar
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </transition>

        <!-- Global Loading Overlay -->
        <transition name="fade">
            <div v-if="loading"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-md"
                aria-live="polite" aria-busy="true">
                <div
                    class="rounded-2xl border border-gray-200/80 bg-white p-10 shadow-2xl shadow-gray-900/25 dark:border-neutral-800/80 dark:bg-neutral-950 dark:shadow-neutral-950/50">
                    <div class="flex flex-col items-center gap-5">
                        <div class="relative">
                            <Loader2 class="h-10 w-10 animate-spin text-blue-600 dark:text-blue-400" />
                            <div
                                class="absolute inset-0 h-10 w-10 animate-ping rounded-full bg-blue-600/20 dark:bg-blue-400/20">
                            </div>
                        </div>
                        <p class="text-sm font-semibold tracking-tight text-gray-700 dark:text-neutral-200">
                            Procesando información…
                        </p>
                    </div>
                </div>
            </div>
        </transition>
    </AppLayout>
</template>

<style scoped>
/* Backdrop transition - suave y natural con blur progresivo */
.backdrop-fade-enter-active {
    transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.backdrop-fade-leave-active {
    transition: opacity 0.25s cubic-bezier(0.4, 0, 1, 1);
}

.backdrop-fade-enter-from,
.backdrop-fade-leave-to {
    opacity: 0;
}

/* Blur progresivo y natural en el backdrop */
.modal-backdrop {
    backdrop-filter: blur(0px);
    -webkit-backdrop-filter: blur(0px);
    will-change: backdrop-filter, opacity;
}

/* Aplicar blur de forma progresiva durante la entrada */
.backdrop-fade-enter-active .modal-backdrop {
    transition: backdrop-filter 0.5s cubic-bezier(0.4, 0, 0.2, 1),
        -webkit-backdrop-filter 0.5s cubic-bezier(0.4, 0, 0.2, 1),
        opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.backdrop-fade-enter-to .modal-backdrop {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

/* Mantener blur durante la salida y luego removerlo */
.backdrop-fade-leave-active .modal-backdrop {
    transition: backdrop-filter 0.3s cubic-bezier(0.4, 0, 1, 1),
        -webkit-backdrop-filter 0.3s cubic-bezier(0.4, 0, 1, 1),
        opacity 0.25s cubic-bezier(0.4, 0, 1, 1);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.backdrop-fade-leave-to .modal-backdrop {
    backdrop-filter: blur(0px);
    -webkit-backdrop-filter: blur(0px);
}

/* Modal container transition - aparece después del backdrop con delay sutil */
.modal-scale-enter-active {
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) 0.05s;
}

.modal-scale-leave-active {
    transition: all 0.25s cubic-bezier(0.4, 0, 1, 1);
}

.modal-scale-enter-from {
    opacity: 0;
    transform: scale(0.97) translateY(6px);
}

.modal-scale-leave-to {
    opacity: 0;
    transform: scale(0.99) translateY(3px);
}

/* Alert transitions with enhanced motion */
.fade-slide-enter-active {
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.fade-slide-leave-active {
    transition: all 0.25s cubic-bezier(0.4, 0, 1, 1);
}

.fade-slide-enter-from {
    opacity: 0;
    transform: translateY(-12px) scale(0.96);
}

.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.98);
}

/* Loading overlay transition */
.fade-enter-active {
    transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.fade-leave-active {
    transition: opacity 0.2s cubic-bezier(0.4, 0, 1, 1);
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Focus visible improvements */
*:focus-visible {
    outline: 2px solid transparent;
    outline-offset: 2px;
}

/* Smooth scrollbar styling with gradient */
.overflow-y-auto::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgb(203 213 225) 0%, rgb(148 163 184) 100%);
    border-radius: 10px;
    border: 2px solid transparent;
    background-clip: padding-box;
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgb(63 63 70) 0%, rgb(82 82 91) 100%);
    background-clip: padding-box;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, rgb(148 163 184) 0%, rgb(100 116 139) 100%);
    background-clip: padding-box;
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, rgb(82 82 91) 0%, rgb(113 113 122) 100%);
    background-clip: padding-box;
}

/* Table row animation */
@keyframes fadeInRow {
    from {
        opacity: 0;
        transform: translateY(4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

tbody tr {
    animation: fadeInRow 0.3s ease-out;
}

tbody tr:nth-child(1) {
    animation-delay: 0.02s;
}

tbody tr:nth-child(2) {
    animation-delay: 0.04s;
}

tbody tr:nth-child(3) {
    animation-delay: 0.06s;
}

tbody tr:nth-child(4) {
    animation-delay: 0.08s;
}

tbody tr:nth-child(5) {
    animation-delay: 0.1s;
}

/* Smooth transitions for all interactive elements */
button,
a,
input,
select {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Subtle glow effect on focus */
input:focus,
select:focus {
    box-shadow: 0 0 0 3px rgb(59 130 246 / 0.1);
}

.dark input:focus,
.dark select:focus {
    box-shadow: 0 0 0 3px rgb(96 165 250 / 0.15);
}
</style>
