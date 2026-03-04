<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { type BreadcrumbItem } from '@/types'
import { computed, ref } from 'vue'
import {
    User,
    Home,
    Briefcase,
    ShieldAlert,
    BookX,
    ClipboardList,
    CheckCircle,
    XCircle,
    FileText,
    Building,
    UserCircle,
    AlertTriangle,
    BadgeCheck,
    Paperclip,
    ArrowLeft,
    AlertCircle
} from 'lucide-vue-next'

// --- PROPS & BREADCRUMBS ---
const props = defineProps<{
    cliente: any
    domicilios: any[]
    operaciones: any[]
    alertas: any[]
    listasNegras: any[]
    perfilTransaccional: any
    listasUIF: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clientes', href: '/clientes' },
    { title: 'Detalles del cliente' },
]

// --- STATE MANAGEMENT ---
const activeTab = ref('resumen')

const navItems = [
    { id: 'resumen', label: 'Resumen', icon: FileText },
    { id: 'info', label: 'Datos Generales', icon: User },
    { id: 'dom', label: 'Domicilios', icon: Home },
    { id: 'op', label: 'Operaciones', icon: Briefcase },
    { id: 'alertas', label: 'Alertas PLD', icon: ShieldAlert },
    { id: 'listas', label: 'Listas de Observación', icon: BookX },
    { id: 'perfil', label: 'Perfil Transaccional', icon: ClipboardList },
]

// --- BACK BUTTON HANDLER ---
function goBack() {
    window.history.back()
}

// --- COMPUTED PROPERTIES FOR SUMMARY ---
const fullName = computed(() => {
    if (props.cliente.RazonSocial) return props.cliente.RazonSocial
    return [props.cliente.Nombre, props.cliente.ApellidoPaterno, props.cliente.ApellidoMaterno].filter(Boolean).join(' ')
})

const isPpe = computed(() => props.cliente.EsPPEActivo)
const onBlacklist = computed(() => props.listasNegras.length > 0 || props.listasUIF.length > 0)
const hasAlerts = computed(() => props.alertas.length > 0)

const personaTipo = computed(() => props.cliente.IDTipoPersona === 1 ? 'Persona Física' : 'Persona Moral')
const personaIcon = computed(() => props.cliente.IDTipoPersona === 1 ? UserCircle : Building)

const hasRFC = computed(() => !!props.cliente.RFC && String(props.cliente.RFC).trim() !== '')

const isExtranjero = computed(() => {
    return props.cliente.IDNacionalidad && props.cliente.IDNacionalidad !== 'MX'
})

// --- Categoria/Rechazo -- Nuevo calculo de razones fuera de categoría ---
const outCategoryReasons = computed(() => {
    const reasons = []
    if (!hasRFC.value) reasons.push('No cuenta con RFC')
    if (isExtranjero.value) reasons.push('Nacionalidad diferente a MX')
    return reasons
})
const isOutCategory = computed(() => outCategoryReasons.value.length > 0)

// --- FORMATTING & HELPER UTILS ---
type EvidenceFile = {
    path: string;
    original?: string;
};
type EvidencePayment = {
    Monto: number | string;
    IDMoneda: string;
    FechaPago: string;
    IDFormaPago?: number | string;
};

// Detectar cualquier JSON con la estructura de pagos fraccionados/acumulados
function getPagosFraccionadosVisual(evidenceStr: string | null | undefined): any | null {
    if (!evidenceStr) return null;
    try {
        const parsed = JSON.parse(evidenceStr);
        if (
            typeof parsed === 'object' &&
            parsed !== null &&
            typeof parsed.total_pagos === 'number' &&
            typeof parsed.total_pagado !== 'undefined' &&
            Array.isArray(parsed.detalle_pagos) &&
            Array.isArray(parsed.analisis_fraccionado)
        ) {
            return parsed;
        }
    } catch (e) {
        return null;
    }
    return null;
}

function getEvidenceFiles(evidenceStr: string | null | undefined): EvidenceFile[] | null {
    if (!evidenceStr) return null;
    try {
        const parsed = JSON.parse(evidenceStr);
        if (Array.isArray(parsed) && parsed.length > 0 && typeof parsed[0]?.path === 'string') {
            return parsed as EvidenceFile[];
        }
    } catch (e) {
        return null;
    }
    return null;
}

function getEvidencePayments(evidenceStr: string | null | undefined): EvidencePayment[] | null {
    if (!evidenceStr) return null;
    try {
        const parsed = JSON.parse(evidenceStr);
        // Case 1: { "pagos": [...] }
        if (typeof parsed === 'object' && parsed !== null && Array.isArray(parsed.pagos) && parsed.pagos.length > 0) {
            return parsed.pagos as EvidencePayment[];
        }
        // Case 2: It's directly an array of payments
        if (Array.isArray(parsed) && parsed.length > 0 && parsed[0].Monto !== undefined && parsed[0].FechaPago !== undefined) {
            return parsed as EvidencePayment[];
        }
    } catch (e) {
        return null;
    }
    return null;
}

function getPublicUrl(path: string): string {
    return `/storage/${path}`;
}

function formatEvidenceText(evidenceStr: string): string {
    try {
        return JSON.stringify(JSON.parse(evidenceStr), null, 2);
    } catch (e) {
        return evidenceStr;
    }
}

function formatDate(dateStr?: string | null): string {
    if (!dateStr) return 'N/A'
    const date = new Date(dateStr)
    if (isNaN(date.getTime())) return dateStr
    return new Intl.DateTimeFormat('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(date)
}

function formatCurrency(value?: number | string | null): string {
    const numberValue = Number(value)
    if (value === null || value === undefined || isNaN(numberValue)) return '$0.00'
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(numberValue)
}

function riesgoNombre(n: number): string {
    switch (n) {
        case 1: return 'Bajo'
        case 2: return 'Medio'
        case 3: return 'Alto'
        default: return 'Desconocido'
    }
}
</script>

<template>

    <Head :title="`Detalle de ${fullName}`" />
    <AppLayout :breadcrumbs="breadcrumbs" container-size="full">
        <!-- Botón de Back -->
        <div class="mb-4">
          <button
            @click="goBack"
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-600 shadow-sm transition hover:bg-gray-50 hover:text-blue-700 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:hover:text-blue-300"
          >
            <ArrowLeft class="h-4 w-4"/>
            <span>Regresar</span>
          </button>
        </div>
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">
            <!-- Columna Izquierda: Navegación y Resumen -->
            <aside class="lg:col-span-1 lg:sticky lg:top-24 h-fit">
                <!-- Tarjeta de Héroe del Cliente -->
                <div
                    class="rounded-xl border border-gray-200/80 bg-white/70 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 ring-1 ring-gray-200 dark:bg-neutral-800 dark:ring-neutral-700">
                            <component :is="personaIcon" class="h-6 w-6 text-gray-600 dark:text-neutral-300" />
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ fullName }}</h1>
                            <p class="text-xs text-gray-500 dark:text-neutral-400">{{ personaTipo }}</p>

                        </div>
                    </div>
                    <div class="mt-4 space-y-1 text-xs">
                        <div class="flex items-center gap-2 text-gray-600 dark:text-neutral-300">
                            <span class="w-15 font-semibold">ID Cliente </span>
                            <span class="font-mono">{{ props.cliente.IDCliente }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-gray-600 dark:text-neutral-300">
                            <span class="w-15 font-semibold">RFC</span>
                            <span v-if="hasRFC" class="font-mono">{{ props.cliente.RFC }}</span>
                            <span v-else class="inline-flex items-center gap-1 rounded bg-yellow-100 text-yellow-800 px-2 py-0.5 font-semibold dark:bg-yellow-900/40 dark:text-yellow-200">No cuenta con RFC</span>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
                            :class="isPpe ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200' : 'bg-gray-100 text-gray-700 dark:bg-neutral-800 dark:text-neutral-200'">
                            <BadgeCheck v-if="!isPpe" class="h-3.5 w-3.5" />
                            <AlertTriangle v-else class="h-3.5 w-3.5" />
                            {{ isPpe ? 'Es PPE' : 'No es PPE' }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
                            :class="onBlacklist ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200' : 'bg-gray-100 text-gray-700 dark:bg-neutral-800 dark:text-neutral-200'">
                            <BookX v-if="onBlacklist" class="h-3.5 w-3.5" />
                            <BadgeCheck v-else class="h-3.5 w-3.5" />
                            {{ onBlacklist ? 'En Listas' : 'Sin coincidencias' }}
                        </span>
                        <span
                          v-if="isExtranjero"
                          class="inline-flex items-center gap-1.5 rounded-full bg-pink-100 text-pink-800 px-2.5 py-1 font-medium dark:bg-pink-900/40 dark:text-pink-200"
                        >
                          <GlobeIcon class="h-3.5 w-3.5" />
                          Extranjero
                        </span>
                    </div>
                </div>

                <!-- Navegación Vertical -->
                <nav class="mt-6 space-y-1">
                    <button v-for="item in navItems" :key="item.id" @click="activeTab = item.id"
                        class="group flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-all duration-200 ease-out"
                        :class="activeTab === item.id
                                ? 'bg-blue-50 text-blue-700 shadow-sm shadow-blue-500/10 dark:bg-blue-500/10 dark:text-blue-300'
                                : 'text-gray-600 hover:bg-gray-100/80 hover:text-gray-800 dark:text-neutral-400 dark:hover:bg-neutral-800/60 dark:hover:text-neutral-200'
                            ">
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                        <span>{{ item.label }}</span>
                        <span v-if="item.id === 'alertas' && hasAlerts"
                            class="ml-auto h-2.5 w-2.5 rounded-full bg-orange-500"></span>
                    </button>
                </nav>
            </aside>

            <!-- Columna Derecha: Contenido Detallado -->
            <main class="lg:col-span-3">
                <Transition name="fade" mode="out-in">
                    <div :key="activeTab">
                        <!-- RESUMEN -->
                        <section v-if="activeTab === 'resumen'" class="space-y-6">
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Resumen del Cliente</h2>

                            <!-- Nueva Sección: Cliente fuera de categoría -->
                            <div v-if="isOutCategory" class="rounded-xl border-2 border-dashed border-red-400 bg-red-50/60 p-6 text-center flex flex-col items-center gap-2 dark:border-red-700 dark:bg-red-900/30">
                              <AlertCircle class="w-8 h-8 text-red-500 mb-1" />
                              <h3 class="font-bold text-red-800 dark:text-red-200 text-lg mb-1">Cliente fuera de categoría</h3>
                              <div class="text-sm text-red-700 dark:text-red-200">
                                <span>Este cliente está fuera de categoría por la/s razón/es:</span>
                                <ul class="mt-2 list-disc list-inside text-left mx-auto w-fit">
                                  <li v-for="reason in outCategoryReasons" :key="reason">{{ reason }}</li>
                                </ul>
                              </div>
                            </div>
                            <!-- /Nueva Sección -->

                             <!-- Alertas Activas -->
                            <div v-if="hasAlerts">
                                <h3 class="mb-3 text-lg font-semibold text-orange-800 dark:text-orange-300">Alertas Recientes</h3>
                                <div class="space-y-4">
                                    <div v-for="alerta in props.alertas.slice(0, 3)" :key="alerta.IDRegistroAlerta" class="rounded-lg border border-orange-200 bg-orange-50/80 p-4 dark:border-orange-500/20 dark:bg-orange-900/20">
                                         <p class="font-semibold text-orange-800 dark:text-orange-200">{{ alerta.Patron }}</p>
                                         <p class="mt-1 text-sm text-gray-600 dark:text-neutral-300">{{ alerta.Descripcion }}</p>
                                         <p class="mt-2 text-xs text-gray-500 dark:text-neutral-400">Detectado: {{ formatDate(alerta.FechaDeteccion) }}</p>
                                    </div>
                                    <Link v-if="props.alertas.length > 3" href="#" @click.prevent="activeTab = 'alertas'" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                                        Ver todas las {{ props.alertas.length }} alertas &rarr;
                                    </Link>
                                </div>
                            </div>

                             <!-- Coincidencias en Listas -->
                             <div v-if="onBlacklist">
                                <h3 class="mb-3 text-lg font-semibold text-rose-800 dark:text-rose-300">Coincidencias en Listas</h3>
                                <div class="space-y-3">
                                    <div v-for="l in props.listasNegras" :key="l.IDRegistroListaCNSF" class="rounded-lg border border-rose-200 bg-rose-50/80 p-3 text-sm dark:border-rose-500/20 dark:bg-rose-900/20">
                                        <p class="font-semibold text-rose-800 dark:text-rose-300">CNSF: {{ l.Nombre }} ({{ l.RFC }})</p>
                                    </div>
                                    <div v-for="l in props.listasUIF" :key="l.IDRegistroListaUIF" class="rounded-lg border border-rose-200 bg-rose-50/80 p-3 text-sm dark:border-rose-500/20 dark:bg-rose-900/20">
                                         <p class="font-semibold text-rose-800 dark:text-rose-300">UIF: {{ l.Nombre }} ({{ l.RFC }})</p>
                                    </div>
                                </div>
                             </div>

                             <div v-if="!hasAlerts && !onBlacklist" class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/80 p-12 text-center dark:border-neutral-700 dark:bg-neutral-900/50">
                                 <CheckCircle class="h-12 w-12 text-green-500" />
                                 <h3 class="mt-4 text-lg font-semibold text-gray-800 dark:text-white">Todo en Orden</h3>
                                 <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">Este cliente no presenta alertas activas ni coincidencias en listas de observación.</p>
                             </div>
                        </section>

                        <!-- DATOS GENERALES -->
                        <section v-if="activeTab === 'info'" class="space-y-6">
                            <div class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-white">Identificación</h3>
                                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                    <div v-if="props.cliente.IDTipoPersona === 2"><dt class="font-medium text-gray-500 dark:text-neutral-400">Razón Social</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ props.cliente.RazonSocial }}</dd></div>
                                    <div v-if="props.cliente.IDTipoPersona === 1"><dt class="font-medium text-gray-500 dark:text-neutral-400">Nombre Completo</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ fullName }}</dd></div>
                                    <div>
                                        <dt class="font-medium text-gray-500 dark:text-neutral-400">RFC</dt>
                                        <dd class="mt-1 font-mono text-gray-900 dark:text-neutral-100">
                                            <span v-if="hasRFC">{{ props.cliente.RFC }}</span>
                                            <span v-else class="inline-flex items-center gap-1 rounded bg-yellow-100 text-yellow-800 px-2 py-0.5 font-semibold dark:bg-yellow-900/40 dark:text-yellow-200">No cuenta con RFC</span>
                                        </dd>
                                    </div>
                                    <div v-if="props.cliente.CURP"><dt class="font-medium text-gray-500 dark:text-neutral-400">CURP</dt><dd class="mt-1 font-mono text-gray-900 dark:text-neutral-100">{{ props.cliente.CURP }}</dd></div>
                                    <div v-if="props.cliente.FolioMercantil"><dt class="font-medium text-gray-500 dark:text-neutral-400">Folio Mercantil</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ props.cliente.FolioMercantil }}</dd></div>
                                    <div>
                                        <dt class="font-medium text-gray-500 dark:text-neutral-400">Fecha de Nacimiento/Constitución</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ formatDate(props.cliente.FechaNacimiento || props.cliente.FechaConstitucion) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-500 dark:text-neutral-400">Nacionalidad</dt>
                                        <dd class="mt-1 text-gray-900 dark:text-neutral-100">
                                            {{ props.cliente.IDNacionalidad }}
                                            <span v-if="isExtranjero" class="inline-flex items-center gap-1.5 rounded-full bg-pink-100 text-pink-800 px-2.5 py-1 font-medium text-xs ml-2 dark:bg-pink-900/40 dark:text-pink-200">
                                              <GlobeIcon class="h-3.5 w-3.5" /> Extranjero
                                            </span>
                                        </dd>
                                    </div>
                                    <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Estado de Nacimiento</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ props.cliente.IDEstadoNacimiento }}</dd></div>
                                </dl>
                            </div>
                             <div class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-white">Clasificación PLD</h3>
                                 <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                      <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Ocupación / Giro</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ props.cliente.IDOcupacionGiro }}</dd></div>
                                      <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Ingresos Estimados</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ formatCurrency(props.cliente.IngresosEstimados) }}</dd></div>
                                     <div>
                                         <dt class="font-medium text-gray-500 dark:text-neutral-400">Coincide en Listas de Observación</dt>
                                         <dd class="mt-1 font-semibold" :class="onBlacklist ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400'">
                                             {{ onBlacklist ? 'Sí' : 'No' }}
                                         </dd>
                                     </div>
                                     <div>
                                         <dt class="font-medium text-gray-500 dark:text-neutral-400">Es Persona Políticamente Expuesta</dt>
                                         <dd class="mt-1 font-semibold" :class="props.cliente.EsPPEActivo ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400'">{{ props.cliente.EsPPEActivo ? 'Sí' : 'No' }}</dd>
                                     </div>
                                     <div v-if="props.cliente.Preguntas" class="sm:col-span-2 lg:col-span-3"><dt class="font-medium text-gray-500 dark:text-neutral-400">Notas / Perfil PLD</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ props.cliente.Preguntas }}</dd></div>
                                 </dl>
                            </div>
                        </section>

                        <!-- DOMICILIOS -->
                        <section v-if="activeTab === 'dom'" class="space-y-4">
                             <div v-if="props.domicilios.length" v-for="(d, i) in props.domicilios" :key="d.IDDomicilio"
                                class="rounded-xl border border-gray-200/80 bg-white/70 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                <h3 class="font-semibold text-gray-800 dark:text-white">Domicilio {{ i + 1 }}</h3>
                                <address class="mt-2 text-sm not-italic text-gray-700 dark:text-neutral-300">
                                    {{ d.Calle }}, No. {{ d.NoExterior }} {{ d.NoInterior ? 'Int. ' + d.NoInterior : '' }}<br>
                                    Col. {{ d.Colonia }}, C.P. {{ d.CP }}<br>
                                    {{ d.IDEstado }}
                                </address>
                                <div v-if="d.Telefono" class="mt-2 text-sm text-gray-600 dark:text-neutral-400">
                                    <span class="font-medium">Tel:</span> {{ d.Telefono }}
                                </div>
                             </div>
                            <div v-else class="text-center text-gray-500 dark:text-neutral-400 italic py-10">Sin domicilios registrados.</div>
                        </section>

                        <!-- OPERACIONES -->
                        <section v-if="activeTab === 'op'" class="space-y-4">
                             <div v-if="props.operaciones.length" v-for="op in props.operaciones" :key="op.IDOperacion"
                                class="rounded-xl border border-gray-200/80 bg-white/70 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                     <div>
                                         <h3 class="text-base font-bold text-blue-800 dark:text-blue-300">{{ op.FolioPoliza }}</h3>
                                         <p class="text-sm text-gray-500 dark:text-neutral-400">Endoso: {{ op.FolioEndoso }}</p>
                                     </div>

                                </div>
                                 <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Prima Total</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-neutral-100">{{ formatCurrency(op.PrimaTotal) }} ({{ op.IDMoneda }})</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Fecha Emisión</dt><dd class="mt-1 text-gray-700 dark:text-neutral-300">{{ formatDate(op.FechaEmision) }}</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Inicio Vigencia</dt><dd class="mt-1 text-gray-700 dark:text-neutral-300">{{ formatDate(op.FechaInicioVigencia) }}</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Fin Vigencia</dt><dd class="mt-1 text-gray-700 dark:text-neutral-300">{{ formatDate(op.FechaFinVigencia) }}</dd></div>
                                     <div class="sm:col-span-2"><dt class="font-medium text-gray-500 dark:text-neutral-400">Agente</dt><dd class="mt-1 text-gray-700 dark:text-neutral-300">{{ op.NombreAgente }} {{ op.APaternoAgente }} ({{ op.RazonSocialAgente }})</dd></div>
                                 </dl>
                                 <div v-if="op.pagos && op.pagos.length" class="mt-4">
                                     <h4 class="text-sm font-semibold text-gray-700 dark:text-neutral-200">Pagos Registrados</h4>
                                     <ul class="mt-2 list-disc list-inside space-y-1 text-sm text-gray-600 dark:text-neutral-300">
                                         <li v-for="p in op.pagos" :key="p.IDOperacionPago">
                                             {{ formatCurrency(p.Monto) }} {{ p.IDMoneda }} - {{ formatDate(p.FechaPago) }} <span v-if="p.IDFormaPago" class="text-xs text-gray-400 dark:text-neutral-500">(FP: {{ p.IDFormaPago }})</span>
                                         </li>
                                     </ul>
                                 </div>
                             </div>
                             <div v-else class="text-center text-gray-500 dark:text-neutral-400 italic py-10">Sin operaciones registradas.</div>
                        </section>

                        <!-- ALERTAS -->
                        <section v-if="activeTab === 'alertas'" class="space-y-4">
                            <div v-if="props.alertas.length" v-for="alerta in props.alertas" :key="alerta.IDRegistroAlerta"
                                class="rounded-xl p-5 shadow-lg backdrop-blur-lg"
                                :class="alerta.Estatus==='Generado' ? 'border border-orange-200/80 bg-orange-50/70 shadow-orange-200/40 dark:border-orange-500/20 dark:bg-orange-900/20 dark:shadow-black/20' : 'border border-green-200/80 bg-green-50/70 shadow-green-200/40 dark:border-green-500/20 dark:bg-green-900/20 dark:shadow-black/20'">
                                <div class="flex items-start justify-between">
                                    <h3 class="text-base font-semibold" :class="alerta.Estatus==='Generado' ? 'text-orange-800 dark:text-orange-200' : 'text-green-800 dark:text-green-200'">Patrón: {{ alerta.Patron }}</h3>
                                    <span class="rounded-full px-3 py-1 text-xs font-medium" :class="alerta.Estatus==='Generado' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/50' : 'bg-green-100 text-green-800 dark:bg-green-900/50'">{{ alerta.Estatus }}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-neutral-300">{{ alerta.Descripcion }}</p>
                                <div class="mt-4 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-neutral-700/50 dark:text-neutral-400 space-y-1">
                                    <p><b>Razones:</b> {{ alerta.Razones }}</p>
                                    <p><b>Monto:</b> {{ formatCurrency(alerta.MontoOperacion) }} | <b>Póliza:</b> {{ alerta.Poliza }}</p>
                                    <p><b>Fecha Detección:</b> {{ formatDate(alerta.FechaDeteccion) }} {{ alerta.HoraDeteccion }}</p>
                                </div>
                                <div v-if="alerta.Evidencias" class="mt-3">
                                    <b class="text-xs font-medium text-gray-600 dark:text-neutral-300">Evidencias:</b>

                                    <!-- Caso especial: Mostrar visual para JSON de pagos fraccionados/acumulados -->
                                    <template v-if="getPagosFraccionadosVisual(alerta.Evidencias)">
                                        <div class="mt-3 border border-orange-200 bg-orange-50/60 rounded-lg p-4 dark:border-orange-500/20 dark:bg-orange-900/20">
                                            <div class="flex flex-wrap gap-6">
                                                <div class="flex-1 min-w-[170px]">
                                                    <div class="text-xs text-gray-500 dark:text-neutral-400 mb-1 font-medium">Total de Pagos</div>
                                                    <div class="text-base font-bold text-orange-900 dark:text-orange-200">{{ getPagosFraccionadosVisual(alerta.Evidencias)?.total_pagos }}</div>
                                                </div>
                                                <div class="flex-1 min-w-[170px]">
                                                    <div class="text-xs text-gray-500 dark:text-neutral-400 mb-1 font-medium">Total Pagado</div>
                                                    <div class="text-base font-bold text-green-800 dark:text-green-300">{{ formatCurrency(getPagosFraccionadosVisual(alerta.Evidencias)?.total_pagado) }}</div>
                                                </div>
                                                <div class="flex-1 min-w-[170px]">
                                                    <div class="text-xs text-gray-500 dark:text-neutral-400 mb-1 font-medium">Saldo Pendiente</div>
                                                    <div :class="getPagosFraccionadosVisual(alerta.Evidencias)?.saldo_pendiente === 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-300'"
                                                        class="text-base font-bold">
                                                        {{ formatCurrency(getPagosFraccionadosVisual(alerta.Evidencias)?.saldo_pendiente) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-4 flex flex-wrap gap-6">
                                                <div>
                                                    <span class="text-xs text-gray-500 dark:text-neutral-400 font-medium">¿Operación pagada?</span>
                                                    <span class="ml-2 font-bold px-2 py-1 rounded-full text-xs" :class="getPagosFraccionadosVisual(alerta.Evidencias)?.operacion_pagada ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'">
                                                        {{ getPagosFraccionadosVisual(alerta.Evidencias)?.operacion_pagada ? 'Sí' : 'No' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-6">
                                                <div class="font-semibold text-orange-900 dark:text-orange-100 text-sm mb-1">Detalle de Pagos</div>
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-[260px] w-full text-xs text-left border rounded-sm">
                                                        <thead>
                                                            <tr class="bg-orange-100 dark:bg-orange-900/30 text-orange-900 dark:text-orange-200">
                                                                <th class="py-1 px-2 font-semibold">Monto</th>
                                                                <th class="py-1 px-2 font-semibold">Moneda</th>
                                                                <th class="py-1 px-2 font-semibold">Forma de Pago</th>
                                                                <th class="py-1 px-2 font-semibold">Fecha de Pago</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(pago, index) in getPagosFraccionadosVisual(alerta.Evidencias)?.detalle_pagos" :key="index">
                                                                <td class="py-1 px-2 font-mono">{{ formatCurrency(pago.monto) }}</td>
                                                                <td class="py-1 px-2">{{ pago.moneda }}</td>
                                                                <td class="py-1 px-2">{{ pago.forma_pago }}</td>
                                                                <td class="py-1 px-2">{{ formatDate(pago.fecha_pago) }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="mt-6">
                                                <div class="font-semibold text-orange-900 dark:text-orange-100 text-sm mb-1">Análisis Fraccionado</div>
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-[260px] w-full text-xs text-left border rounded-sm">
                                                        <thead>
                                                            <tr class="bg-orange-100 dark:bg-orange-900/30 text-orange-900 dark:text-orange-200">
                                                                <th class="py-1 px-2 font-semibold">Monto pago</th>
                                                                <th class="py-1 px-2 font-semibold">Esperado</th>
                                                                <th class="py-1 px-2 font-semibold">¿Dentro de tolerancia?</th>
                                                                <th class="py-1 px-2 font-semibold">Diferencia</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(analisis, index) in getPagosFraccionadosVisual(alerta.Evidencias)?.analisis_fraccionado" :key="index">
                                                                <td class="py-1 px-2 font-mono">{{ formatCurrency(analisis.monto_pago) }}</td>
                                                                <td class="py-1 px-2 font-mono">{{ formatCurrency(analisis.esperado) }}</td>
                                                                <td class="py-1 px-2">
                                                                    <span class="font-semibold" :class="analisis.dentro_tolerance ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                                                                        {{ analisis.dentro_tolerance ? 'Sí' : 'No' }}
                                                                    </span>
                                                                </td>
                                                                <td class="py-1 px-2 font-mono"
                                                                    :class="analisis.diferencia !== 0 ? 'text-orange-800 dark:text-orange-200 font-semibold' : ''">
                                                                    {{ formatCurrency(analisis.diferencia) }}
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Fin del caso especial -->

                                    <!-- Case 1: List of files -->
                                    <div v-else-if="getEvidenceFiles(alerta.Evidencias)" class="mt-2 space-y-1.5">
                                        <a v-for="(file, index) in getEvidenceFiles(alerta.Evidencias)" :key="index" :href="getPublicUrl(file.path)" target="_blank" rel="noopener noreferrer"
                                           class="group inline-flex items-center gap-2 rounded-md bg-gray-100/80 p-2 text-sm transition-colors hover:bg-blue-100/70 dark:bg-neutral-800/60 dark:hover:bg-blue-900/30">
                                            <Paperclip class="h-4 w-4 text-gray-500 group-hover:text-blue-600 dark:text-neutral-400 dark:group-hover:text-blue-400" />
                                            <span class="font-medium text-gray-700 group-hover:text-blue-700 dark:text-neutral-300 dark:group-hover:text-blue-300">{{ file.original || file.path.split('/').pop() }}</span>
                                        </a>
                                    </div>

                                    <!-- Case 2: List of payments -->
                                    <div v-else-if="getEvidencePayments(alerta.Evidencias)" class="mt-2 rounded-lg border border-gray-200/80 dark:border-neutral-800/80 overflow-hidden">
                                        <ul class="divide-y divide-gray-200/80 dark:divide-neutral-800/80">
                                            <li v-for="(pago, index) in getEvidencePayments(alerta.Evidencias)" :key="index" class="px-3 py-2 text-xs bg-gray-50/50 dark:bg-neutral-900/30">
                                                <div class="flex justify-between items-center">
                                                    <span class="font-semibold text-gray-800 dark:text-neutral-200">{{ formatCurrency(pago.Monto) }} <span class="font-normal text-gray-500 dark:text-neutral-400">{{ pago.IDMoneda }}</span></span>
                                                    <span class="text-gray-500 dark:text-neutral-400">{{ formatDate(pago.FechaPago) }}</span>
                                                </div>
                                                <div v-if="pago.IDFormaPago" class="text-gray-500 dark:text-neutral-500 mt-0.5">
                                                    Forma de Pago: {{ pago.IDFormaPago }}
                                                </div>
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- Case 3: Other JSON or plain string -->
                                    <pre v-else class="mt-1 whitespace-pre-wrap rounded-md bg-gray-100/70 p-2 text-xs text-gray-800 dark:bg-neutral-800/50 dark:text-neutral-200">{{ formatEvidenceText(alerta.Evidencias) }}</pre>
                                </div>
                            </div>
                            <div v-else class="text-center text-gray-500 dark:text-neutral-400 italic py-10">Sin alertas para este cliente.</div>
                        </section>

                        <!-- LISTAS DE OBSERVACIÓN -->
                        <section v-if="activeTab === 'listas'" class="grid grid-cols-1 gap-8 md:grid-cols-2">
                             <div class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                 <h3 class="font-semibold text-rose-900 dark:text-rose-300">CNSF (Listas Negras)</h3>
                                 <ul v-if="props.listasNegras.length" class="mt-3 divide-y divide-gray-200/80 dark:divide-neutral-800">
                                     <li v-for="l in props.listasNegras" :key="l.IDRegistroListaCNSF" class="py-3 text-sm">
                                         <p class="font-semibold text-gray-800 dark:text-neutral-200">{{ l.Nombre }} <span class="font-mono text-xs text-gray-500 dark:text-neutral-400">({{ l.RFC }})</span></p>
                                         <p class="text-xs text-gray-500 dark:text-neutral-400">{{ l.Direccion }}, {{ l.Pais }}</p>
                                     </li>
                                 </ul>
                                 <p v-else class="mt-3 text-sm text-gray-500 dark:text-neutral-400 italic">Sin coincidencias.</p>
                             </div>
                             <div class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                 <h3 class="font-semibold text-rose-900 dark:text-rose-300">UIF (Personas Bloqueadas)</h3>
                                 <ul v-if="props.listasUIF.length" class="mt-3 divide-y divide-gray-200/80 dark:divide-neutral-800">
                                     <li v-for="l in props.listasUIF" :key="l.IDRegistroListaUIF" class="py-3 text-sm">
                                         <p class="font-semibold text-gray-800 dark:text-neutral-200">{{ l.Nombre }} <span class="font-mono text-xs text-gray-500 dark:text-neutral-400">({{ l.RFC }})</span></p>
                                         <p class="text-xs text-gray-500 dark:text-neutral-400">Oficio: {{ l.NoOficioUIF }} ({{ l.AnioLista }})</p>
                                     </li>
                                 </ul>
                                 <p v-else class="mt-3 text-sm text-gray-500 dark:text-neutral-400 italic">Sin coincidencias.</p>
                             </div>
                        </section>

                        <!-- PERFIL TRANSACCIONAL -->
                        <section v-if="activeTab === 'perfil'">
                            <div v-if="props.perfilTransaccional" class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                <h3 class="text-base font-semibold text-purple-900 dark:text-purple-300">Perfil Transaccional Calculado</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-neutral-400">Fecha de ejecución: {{ formatDate(props.perfilTransaccional.FechaEjecucción) }}</p>
                                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Perfil Total</dt><dd class="mt-1 text-lg font-bold text-purple-800 dark:text-purple-200">{{ props.perfilTransaccional.Perfil }}</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Nivel Riesgo Ocupación</dt><dd class="mt-1 font-semibold text-gray-800 dark:text-neutral-200">{{ riesgoNombre(props.perfilTransaccional.NivelRiesgo) }}</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Rubro/Giro</dt><dd class="mt-1 text-gray-700 dark:text-neutral-300">{{ props.perfilTransaccional.OcupGiro }}</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Riesgo Nacionalidad</dt><dd class="mt-1 font-semibold text-gray-800 dark:text-neutral-200">{{ riesgoNombre(props.perfilTransaccional.NivelRiesgoNac) }}</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Riesgo Residencia</dt><dd class="mt-1 font-semibold text-gray-800 dark:text-neutral-200">{{ riesgoNombre(props.perfilTransaccional.NivelRiesgoResidencia) }}</dd></div>
                                </dl>
                            </div>
                             <div v-else class="text-center text-gray-500 dark:text-neutral-400 italic py-10">Sin perfil transaccional registrado.</div>
                        </section>
                    </div>
                </Transition>
            </main>
        </div>
    </AppLayout>
</template>

<script lang="ts">
// Icono de mundo para extranjero
import { Globe as GlobeIcon } from 'lucide-vue-next'
</script>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease-out;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
