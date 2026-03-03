<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
import {
    AlertTriangle,
    Users,
    FileSearch,
    FileText,
    BarChart2,
    ShieldCheck,
    ArrowRight,
    Search,
    FilePlus
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard PLD',
        href: dashboard().url,
    },
];

// --- Placeholder Data ---
// In a real application, this would come from props or an API call.
const stats = {
    alertasCriticas: { value: 12, trend: 5 },
    clientesRevision: { value: 8 },
    operacionesInusuales: { value: 43, trend: -10 },
    reportesPendientes: { value: 3 },
};

const alertasRecientes = [
    { id: 1, cliente: 'Juan Carlos Schmid', riesgo: 'Alto', patron: 'Operación fraccionada', fecha: 'Hace 2 horas', url: '#' },
    { id: 2, cliente: 'Constructora del Sureste SA de CV', riesgo: 'Alto', patron: 'Movimientos sin justificación económica', fecha: 'Hace 1 día', url: '#' },
    { id: 3, cliente: 'Marco Antonio Regil', riesgo: 'Medio', patron: 'Actividad atípica para el perfil', fecha: 'Hace 2 días', url: '#' },
];

const clientesAltoRiesgo = [
    { id: 1, nombre: 'Empresaria Hotelera del Caribe', tipo: 'Nuevo PPE Identificado', fecha: 'Ayer', url: '#' },
    { id: 2, nombre: 'José Pérez López', tipo: 'Coincidencia en Listas de Observación', fecha: 'Hace 3 días', url: '#' },
];

</script>

<template>

    <Head title="Dashboard PLD" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn class="space-y-6">
            <!-- Sección de Tarjetas de Estadísticas Principales -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Alertas Críticas -->
                <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white/60 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg transition-all duration-300 ease-out hover:shadow-xl hover:shadow-gray-300/50 dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-black/20 dark:hover:border-orange-500/30">
                    <div class="flex items-center gap-4">
                        <div class="flex h-11 w-11 flex-none items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/40">
                            <AlertTriangle class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-neutral-400">Alertas Críticas</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.alertasCriticas.value }}</p>
                        </div>
                    </div>
                </div>
                <!-- Clientes en Revisión -->
                <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white/60 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg transition-all duration-300 ease-out hover:shadow-xl hover:shadow-gray-300/50 dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-black/20 dark:hover:border-yellow-500/30">
                    <div class="flex items-center gap-4">
                        <div class="flex h-11 w-11 flex-none items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900/40">
                            <Users class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-neutral-400">Clientes en Revisión</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.clientesRevision.value }}</p>
                        </div>
                    </div>
                </div>
                <!-- Operaciones Inusuales -->
                <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white/60 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg transition-all duration-300 ease-out hover:shadow-xl hover:shadow-gray-300/50 dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-black/20 dark:hover:border-blue-500/30">
                    <div class="flex items-center gap-4">
                        <div class="flex h-11 w-11 flex-none items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                            <FileSearch class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-neutral-400">Op. Inusuales</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.operacionesInusuales.value }}</p>
                        </div>
                    </div>
                </div>
                <!-- Reportes Pendientes -->
                <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white/60 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg transition-all duration-300 ease-out hover:shadow-xl hover:shadow-gray-300/50 dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-black/20 dark:hover:border-purple-500/30">
                    <div class="flex items-center gap-4">
                        <div class="flex h-11 w-11 flex-none items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/40">
                            <FileText class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-neutral-400">Reportes Pendientes</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.reportesPendientes.value }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección de Gráfico Principal y Acciones Rápidas -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Gráfico de Alertas -->
                <div class="rounded-xl border border-gray-200/80 bg-white/60 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg lg:col-span-2 dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-black/20">
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white">Alertas en los Últimos 30 Días</h2>
                    <div class="mt-4 flex h-64 items-center justify-center rounded-lg bg-gray-100/80 dark:bg-neutral-900/50">
                        <div class="text-center">
                             <BarChart2 class="mx-auto h-12 w-12 text-gray-400 dark:text-neutral-600" />
                             <p class="mt-2 text-sm text-gray-500 dark:text-neutral-400">Placeholder para gráfico de alertas</p>
                        </div>
                    </div>
                </div>
                <!-- Acciones Rápidas -->
                <div class="space-y-4">
                     <h2 class="text-base font-semibold text-gray-800 dark:text-white">Acciones Rápidas</h2>
                     <div class="space-y-3">
                         <Link href="#" class="group flex w-full items-center justify-between rounded-lg border border-gray-200/80 bg-white/80 p-4 text-left transition-all duration-200 ease-out hover:border-blue-400/80 hover:bg-white hover:shadow-xl hover:shadow-blue-500/10 dark:border-neutral-800 dark:bg-neutral-900/50 dark:hover:border-blue-500/60 dark:hover:bg-neutral-800/80">
                            <div>
                                <h3 class="font-semibold text-gray-800 dark:text-neutral-100">Analizar Cliente</h3>
                                <p class="text-xs text-gray-500 dark:text-neutral-400">Buscar perfiles, operaciones y alertas.</p>
                            </div>
                            <Search class="h-5 w-5 text-gray-400 transition-transform duration-200 group-hover:scale-110 group-hover:text-blue-500 dark:group-hover:text-blue-400" />
                         </Link>
                          <Link href="#" class="group flex w-full items-center justify-between rounded-lg border border-gray-200/80 bg-white/80 p-4 text-left transition-all duration-200 ease-out hover:border-purple-400/80 hover:bg-white hover:shadow-xl hover:shadow-purple-500/10 dark:border-neutral-800 dark:bg-neutral-900/50 dark:hover:border-purple-500/60 dark:hover:bg-neutral-800/80">
                            <div>
                                <h3 class="font-semibold text-gray-800 dark:text-neutral-100">Generar Reporte Regulatorio</h3>
                                <p class="text-xs text-gray-500 dark:text-neutral-400">Crear reportes para la autoridad.</p>
                            </div>
                            <FilePlus class="h-5 w-5 text-gray-400 transition-transform duration-200 group-hover:scale-110 group-hover:text-purple-500 dark:group-hover:text-purple-400" />
                         </Link>
                         <Link href="#" class="group flex w-full items-center justify-between rounded-lg border border-gray-200/80 bg-white/80 p-4 text-left transition-all duration-200 ease-out hover:border-gray-400/80 hover:bg-white hover:shadow-xl hover:shadow-gray-500/10 dark:border-neutral-800 dark:bg-neutral-900/50 dark:hover:border-gray-500/60 dark:hover:bg-neutral-800/80">
                            <div>
                                <h3 class="font-semibold text-gray-800 dark:text-neutral-100">Consultar Listas</h3>
                                <p class="text-xs text-gray-500 dark:text-neutral-400">Ver listas de PPL, CNSF, UIF, etc.</p>
                            </div>
                            <FileSearch class="h-5 w-5 text-gray-400 transition-transform duration-200 group-hover:scale-110" />
                         </Link>
                     </div>
                </div>
            </div>

            <!-- Sección de Listas de Actividad -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Alertas Críticas Recientes -->
                <div class="rounded-xl border border-gray-200/80 bg-white/60 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-black/20">
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white">Alertas Críticas Recientes</h2>
                    <div class="mt-4 flow-root">
                        <ul role="list" class="-my-3 divide-y divide-gray-200/80 dark:divide-neutral-800">
                             <li v-for="alerta in alertasRecientes" :key="alerta.id" class="py-3">
                                 <Link :href="alerta.url" class="group flex items-center justify-between gap-4">
                                     <div class="min-w-0 flex-1">
                                         <p class="truncate text-sm font-semibold text-gray-800 dark:text-neutral-100">{{ alerta.cliente }}</p>
                                         <p class="mt-0.5 flex items-center gap-1.5 truncate text-xs text-gray-500 dark:text-neutral-400">
                                             <span class="inline-block h-2 w-2 rounded-full" :class="alerta.riesgo === 'Alto' ? 'bg-red-500' : 'bg-yellow-500'"></span>
                                             <span>{{ alerta.patron }}</span>
                                         </p>
                                     </div>
                                     <div class="flex-shrink-0 text-right">
                                         <p class="text-xs text-gray-500 dark:text-neutral-400">{{ alerta.fecha }}</p>
                                         <p class="mt-1 text-xs font-medium text-blue-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-blue-400">Revisar &rarr;</p>
                                     </div>
                                 </Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Nuevos Clientes de Alto Riesgo -->
                <div class="rounded-xl border border-gray-200/80 bg-white/60 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/60 dark:shadow-black/20">
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white">Nuevos Clientes de Alto Riesgo</h2>
                    <div class="mt-4 flow-root">
                        <ul role="list" class="-my-3 divide-y divide-gray-200/80 dark:divide-neutral-800">
                             <li v-for="cliente in clientesAltoRiesgo" :key="cliente.id" class="py-3">
                                 <Link :href="cliente.url" class="group flex items-center justify-between gap-4">
                                     <div class="min-w-0 flex-1">
                                         <p class="truncate text-sm font-semibold text-gray-800 dark:text-neutral-100">{{ cliente.nombre }}</p>
                                         <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-neutral-400">{{ cliente.tipo }}</p>
                                     </div>
                                     <div class="flex-shrink-0 text-right">
                                         <p class="text-xs text-gray-500 dark:text-neutral-400">{{ cliente.fecha }}</p>
                                         <p class="mt-1 text-xs font-medium text-blue-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-blue-400">Ver perfil &rarr;</p>
                                     </div>
                                 </Link>
                             </li>
                        </ul>
                    </div>
                </div>
            </div>
        </FadeIn>
    </AppLayout>
</template>
