<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import FadeIn from '@/components/ui/animation/fadeIn.vue';

type UltimaAlerta = {
    IDRegistroAlerta: number;
    Cliente: string;
    Patron: string;
    Descripcion: string;
    created_at: string;
    Estatus: string;
};

type DashboardProps = {
    totalAlertas: number;
    alertasHoy: number;
    alertasAbiertas: number;
    alertasPorEstatus: Record<string, number>;
    alertasPorPatron: Record<string, number>;
    alertasPorReportar: number;
    ultimasAlertas: UltimaAlerta[];
    buzonPendiente: number;
    cantClientes: number;
    cantClientesActivos: number;
    cantClientesNacionalidadMX: number;
    cantClientesExtranjeros: number;
    cantClientesPPE: number;
    cantClientesEnListaNegra: number;
    perfilesPorRiesgo: Record<string, number>;
};

const props = defineProps<DashboardProps>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: dashboard().url }];

const estatusData = computed(() =>
    Object.entries(props.alertasPorEstatus ?? {}).map(([estatus, total]) => ({ estatus, total })),
);
const totalAlertasEstatus = computed(() =>
    estatusData.value.reduce((acc, item) => acc + item.total, 0),
);

const patronData = computed(() =>
    Object.entries(props.alertasPorPatron ?? {}).map(([patron, total]) => ({ patron, total })),
);
const totalAlertasPatron = computed(() =>
    patronData.value.reduce((acc, item) => acc + item.total, 0),
);

const perfilData = computed(() =>
    Object.entries(props.perfilesPorRiesgo ?? {}).map(([perfil, total]) => ({ perfil, total })),
);
const totalPerfiles = computed(() =>
    perfilData.value.reduce((acc, item) => acc + item.total, 0),
);

function getEstatusColor(estatus: string) {
    switch (estatus) {
        case 'Generado':   return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-200';
        case 'Analizado':  return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200';
        case 'Cerrado':    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200';
        case 'Reportado':  return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-200';
        case 'Enviado':    return 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-200';
        default:           return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
}

function getEstatusBarColor(estatus: string) {
    switch (estatus) {
        case 'Generado':   return 'bg-yellow-400';
        case 'Analizado':  return 'bg-blue-400';
        case 'Cerrado':    return 'bg-emerald-500';
        case 'Reportado':  return 'bg-purple-500';
        case 'Enviado':    return 'bg-sky-500';
        default:           return 'bg-gray-400';
    }
}

function getPatronColor(patron: string) {
    switch (patron) {
        case 'Relevante':    return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-200';
        case 'Inusual':      return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200';
        case 'Preocupante':  return 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-200';
        default:             return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
    }
}

function getPatronBarColor(patron: string) {
    switch (patron) {
        case 'Relevante':    return 'bg-orange-400';
        case 'Inusual':      return 'bg-red-500';
        case 'Preocupante':  return 'bg-rose-500';
        default:             return 'bg-gray-400';
    }
}

function getPerfilColor(perfil: string) {
    const p = perfil?.toLowerCase();
    if (p === 'alto')  return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200';
    if (p === 'medio') return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200';
    if (p === 'bajo')  return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200';
    return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
}

function getPerfilBarColor(perfil: string) {
    const p = perfil?.toLowerCase();
    if (p === 'alto')  return 'bg-red-500';
    if (p === 'medio') return 'bg-amber-400';
    if (p === 'bajo')  return 'bg-emerald-500';
    return 'bg-gray-400';
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
            <main class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-2xl border border-sidebar-border/60 bg-gradient-to-b from-muted/40 via-transparent to-transparent p-6 shadow-sm">

                <!-- Encabezado -->
                <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1.5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                            Vista general operativa
                        </p>
                        <h1 class="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                            Panel de Control PLD
                        </h1>
                        <p class="max-w-2xl text-xs text-muted-foreground sm:text-sm">
                            Estado consolidado de alertas, buzón de preocupantes, perfiles de riesgo y base de clientes.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 self-stretch sm:self-auto sm:items-end sm:justify-end">
                        <div class="inline-flex items-center gap-2 rounded-full border border-dashed border-sidebar-border/70 bg-background/80 px-3 py-1 text-[11px] text-muted-foreground shadow-sm backdrop-blur-sm">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.25)]" />
                            <span>Datos en tiempo real</span>
                        </div>
                    </div>
                </header>

                <!-- Fila 1: 4 KPIs principales -->
                <section aria-label="KPIs principales" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

                    <!-- Alertas hoy -->
                    <article class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-sidebar-border/70 bg-card/95 p-4 shadow-sm backdrop-blur-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                        <div class="pointer-events-none absolute inset-y-3 right-3 w-10 rounded-full bg-amber-500/10 opacity-80 transition-opacity group-hover:opacity-100" />
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Alertas hoy</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-foreground">
                            {{ props.alertasHoy.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                props.totalAlertas > 0
                                    ? `${((props.alertasHoy / props.totalAlertas) * 100).toFixed(1)}% del total histórico`
                                    : 'Sin alertas registradas'
                            }}
                        </p>
                    </article>

                    <!-- Alertas abiertas -->
                    <article class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-rose-400/70 bg-card/95 p-4 shadow-sm backdrop-blur-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-rose-500/70">
                        <div class="pointer-events-none absolute inset-y-3 right-3 w-10 rounded-full bg-rose-500/10 opacity-80 transition-opacity group-hover:opacity-100" />
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-600 dark:text-rose-300">Alertas abiertas</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-foreground">
                            {{ props.alertasAbiertas.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                props.totalAlertas > 0
                                    ? `${((props.alertasAbiertas / props.totalAlertas) * 100).toFixed(1)}% del total de alertas`
                                    : 'Sin alertas registradas'
                            }}
                        </p>
                    </article>

                    <!-- Buzón pendiente -->
                    <article class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-orange-400/70 bg-card/95 p-4 shadow-sm backdrop-blur-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-orange-500/70">
                        <div class="pointer-events-none absolute inset-y-3 right-3 w-10 rounded-full bg-orange-500/10 opacity-80 transition-opacity group-hover:opacity-100" />
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-300">Buzón pendiente</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-foreground">
                            {{ props.buzonPendiente.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ props.buzonPendiente > 0 ? 'Operaciones por clasificar' : 'Sin pendientes' }}
                        </p>
                    </article>

                    <!-- Por reportar -->
                    <article class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-purple-400/70 bg-card/95 p-4 shadow-sm backdrop-blur-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-purple-500/70">
                        <div class="pointer-events-none absolute inset-y-3 right-3 w-10 rounded-full bg-purple-500/10 opacity-80 transition-opacity group-hover:opacity-100" />
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-purple-600 dark:text-purple-300">Por reportar (PLD)</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-foreground">
                            {{ props.alertasPorReportar.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ props.alertasPorReportar > 0 ? 'Alertas pendientes de envío regulatorio' : 'Sin reportes pendientes' }}
                        </p>
                    </article>
                </section>

                <!-- Fila 2: Layout principal -->
                <section class="grid gap-6 xl:grid-cols-[minmax(0,2.2fr)_minmax(0,1.2fr)]">

                    <!-- Columna izquierda -->
                    <div class="flex flex-col gap-4">

                        <!-- Estatus + Patrón (dos columnas) -->
                        <div class="grid gap-4 md:grid-cols-2">

                            <!-- Alertas por estatus -->
                            <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border">
                                <div class="mb-3">
                                    <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">Alertas por estatus</h2>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Ciclo de vida de las alertas activas.</p>
                                </div>
                                <div v-if="estatusData.length" class="space-y-2.5">
                                    <div
                                        v-for="item in estatusData"
                                        :key="item.estatus"
                                        class="space-y-1">
                                        <div class="flex items-center justify-between text-xs">
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ring-black/5 dark:ring-white/5"
                                                :class="getEstatusColor(item.estatus)">
                                                {{ item.estatus }}
                                            </span>
                                            <div class="flex items-baseline gap-1.5">
                                                <span class="font-semibold tabular-nums text-foreground">{{ item.total.toLocaleString() }}</span>
                                                <span class="text-muted-foreground">
                                                    {{ totalAlertasEstatus > 0 ? `${((item.total / totalAlertasEstatus) * 100).toFixed(1)}%` : '0%' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                            <div
                                                class="h-full rounded-full transition-all duration-300 ease-out"
                                                :class="getEstatusBarColor(item.estatus)"
                                                :style="{ width: totalAlertasEstatus > 0 ? `${(item.total / totalAlertasEstatus) * 100}%` : '0%' }" />
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-muted-foreground">Sin datos de estatus.</p>
                            </div>

                            <!-- Alertas por patrón PLD -->
                            <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border">
                                <div class="mb-3">
                                    <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">Alertas por patrón</h2>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Clasificación regulatoria de operaciones sospechosas.</p>
                                </div>
                                <div v-if="patronData.length" class="space-y-2.5">
                                    <div
                                        v-for="item in patronData"
                                        :key="item.patron"
                                        class="space-y-1">
                                        <div class="flex items-center justify-between text-xs">
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ring-black/5 dark:ring-white/5"
                                                :class="getPatronColor(item.patron)">
                                                {{ item.patron }}
                                            </span>
                                            <div class="flex items-baseline gap-1.5">
                                                <span class="font-semibold tabular-nums text-foreground">{{ item.total.toLocaleString() }}</span>
                                                <span class="text-muted-foreground">
                                                    {{ totalAlertasPatron > 0 ? `${((item.total / totalAlertasPatron) * 100).toFixed(1)}%` : '0%' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                            <div
                                                class="h-full rounded-full transition-all duration-300 ease-out"
                                                :class="getPatronBarColor(item.patron)"
                                                :style="{ width: totalAlertasPatron > 0 ? `${(item.total / totalAlertasPatron) * 100}%` : '0%' }" />
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-muted-foreground">Sin alertas PLD clasificadas.</p>

                                <!-- Total alertas históricas -->
                                <div class="mt-4 flex items-center justify-between border-t border-sidebar-border/40 pt-3 text-xs text-muted-foreground">
                                    <span>Total histórico</span>
                                    <span class="font-semibold tabular-nums text-foreground">{{ props.totalAlertas.toLocaleString() }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Últimas alertas -->
                        <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">Últimas alertas generadas</h2>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Registros más recientes para priorizar atención inmediata.</p>
                                </div>
                                <span class="text-xs text-muted-foreground">{{ props.ultimasAlertas.length }} registros</span>
                            </div>

                            <div class="-mx-4 overflow-x-auto border-t border-sidebar-border/60">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="border-b border-sidebar-border/70 bg-muted/60 text-[11px] uppercase text-muted-foreground">
                                        <tr>
                                            <th class="px-3 py-2 font-medium">ID</th>
                                            <th class="px-3 py-2 font-medium">Cliente</th>
                                            <th class="px-3 py-2 font-medium">Patrón</th>
                                            <th class="px-3 py-2 font-medium">Descripción</th>
                                            <th class="px-3 py-2 font-medium">Fecha</th>
                                            <th class="px-3 py-2 font-medium">Estatus</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="alerta in props.ultimasAlertas"
                                            :key="alerta.IDRegistroAlerta"
                                            class="border-b border-sidebar-border/40 text-xs last:border-0 odd:bg-muted/20 hover:bg-muted/40 transition-colors">
                                            <td class="px-3 py-2 font-mono text-[11px] text-muted-foreground tabular-nums">
                                                {{ alerta.IDRegistroAlerta }}
                                            </td>
                                            <td class="px-3 py-2 text-foreground">{{ alerta.Cliente }}</td>
                                            <td class="px-3 py-2">
                                                <span
                                                    v-if="alerta.Patron"
                                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ring-black/5 dark:ring-white/5"
                                                    :class="getPatronColor(alerta.Patron)">
                                                    {{ alerta.Patron }}
                                                </span>
                                                <span v-else class="text-muted-foreground">—</span>
                                            </td>
                                            <td class="px-3 py-2 text-muted-foreground">
                                                <span class="block max-w-xs truncate sm:max-w-sm">{{ alerta.Descripcion }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-muted-foreground whitespace-nowrap">
                                                {{ new Date(alerta.created_at).toLocaleDateString('es-MX') }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <span
                                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ring-black/5 dark:ring-white/5"
                                                    :class="getEstatusColor(alerta.Estatus)">
                                                    {{ alerta.Estatus }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-if="!props.ultimasAlertas.length">
                                            <td colspan="6" class="px-3 py-4 text-center text-xs text-muted-foreground">
                                                No hay alertas recientes.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Columna derecha -->
                    <aside class="flex flex-col gap-4">

                        <!-- Base de clientes -->
                        <section class="group relative overflow-hidden rounded-xl border border-emerald-400/70 bg-card/95 p-4 shadow-sm backdrop-blur-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-emerald-500/70">
                            <div class="pointer-events-none absolute inset-y-3 right-3 w-10 rounded-full bg-emerald-500/10 opacity-80 transition-opacity group-hover:opacity-100" />
                            <div class="mb-3 flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-300">Base de clientes</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Composición y estado de la cartera.</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">Total</p>
                                    <p class="mt-0.5 text-xl font-semibold tracking-tight text-foreground">
                                        {{ props.cantClientes.toLocaleString() }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2.5 border-t border-emerald-500/30 pt-3">
                                <!-- Activos -->
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-muted-foreground">Clientes activos</span>
                                        <span class="font-semibold tabular-nums text-foreground">{{ props.cantClientesActivos.toLocaleString() }}</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                                            :style="{ width: props.cantClientes > 0 ? `${(props.cantClientesActivos / props.cantClientes) * 100}%` : '0%' }" />
                                    </div>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ props.cantClientes > 0 ? `${((props.cantClientesActivos / props.cantClientes) * 100).toFixed(1)}%` : '0%' }} del total
                                    </p>
                                </div>

                                <!-- PPE -->
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-muted-foreground">Con PPE activa</span>
                                        <span class="font-semibold tabular-nums text-foreground">{{ props.cantClientesPPE.toLocaleString() }}</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full bg-amber-400 transition-all duration-300"
                                            :style="{ width: props.cantClientes > 0 ? `${(props.cantClientesPPE / props.cantClientes) * 100}%` : '0%' }" />
                                    </div>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ props.cantClientes > 0 ? `${((props.cantClientesPPE / props.cantClientes) * 100).toFixed(2)}%` : '0%' }} del total
                                    </p>
                                </div>

                                <!-- En listas negras -->
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-muted-foreground">En listas negras</span>
                                        <span
                                            class="font-semibold tabular-nums"
                                            :class="props.cantClientesEnListaNegra > 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground'">
                                            {{ props.cantClientesEnListaNegra.toLocaleString() }}
                                        </span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full bg-red-500 transition-all duration-300"
                                            :style="{ width: props.cantClientes > 0 ? `${(props.cantClientesEnListaNegra / props.cantClientes) * 100}%` : '0%' }" />
                                    </div>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ props.cantClientes > 0 ? `${((props.cantClientesEnListaNegra / props.cantClientes) * 100).toFixed(2)}%` : '0%' }} del total
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- Perfiles de riesgo transaccional -->
                        <section class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                            <div class="mb-3">
                                <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">Perfil de riesgo transaccional</h2>
                                <p class="mt-0.5 text-xs text-muted-foreground">Distribución de clientes perfilados por nivel de riesgo.</p>
                            </div>

                            <div v-if="perfilData.length" class="space-y-2.5">
                                <div
                                    v-for="item in perfilData"
                                    :key="item.perfil"
                                    class="space-y-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ring-black/5 dark:ring-white/5"
                                            :class="getPerfilColor(item.perfil)">
                                            {{ item.perfil }}
                                        </span>
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="font-semibold tabular-nums text-foreground">{{ item.total.toLocaleString() }}</span>
                                            <span class="text-muted-foreground">
                                                {{ totalPerfiles > 0 ? `${((item.total / totalPerfiles) * 100).toFixed(1)}%` : '0%' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full transition-all duration-300"
                                            :class="getPerfilBarColor(item.perfil)"
                                            :style="{ width: totalPerfiles > 0 ? `${(item.total / totalPerfiles) * 100}%` : '0%' }" />
                                    </div>
                                </div>
                                <div class="flex items-center justify-between border-t border-sidebar-border/40 pt-2 text-xs text-muted-foreground">
                                    <span>Clientes perfilados</span>
                                    <span class="font-semibold tabular-nums text-foreground">{{ totalPerfiles.toLocaleString() }}</span>
                                </div>
                            </div>
                            <p v-else class="text-xs text-muted-foreground">Sin perfiles de riesgo calculados.</p>
                        </section>

                        <!-- Distribución por nacionalidad -->
                        <section class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                            <div class="mb-3">
                                <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">Distribución por nacionalidad</h2>
                                <p class="mt-0.5 text-xs text-muted-foreground">Nacionales vs. extranjeros en la cartera.</p>
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-sky-500" />
                                        <span class="text-foreground">Mexicanos</span>
                                    </div>
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="font-semibold tabular-nums text-foreground">{{ props.cantClientesNacionalidadMX.toLocaleString() }}</span>
                                        <span class="text-muted-foreground">
                                            {{ props.cantClientes > 0 ? `${((props.cantClientesNacionalidadMX / props.cantClientes) * 100).toFixed(1)}%` : '0%' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-amber-400" />
                                        <span class="text-foreground">Extranjeros</span>
                                    </div>
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="font-semibold tabular-nums text-foreground">{{ props.cantClientesExtranjeros.toLocaleString() }}</span>
                                        <span class="text-muted-foreground">
                                            {{ props.cantClientes > 0 ? `${((props.cantClientesExtranjeros / props.cantClientes) * 100).toFixed(1)}%` : '0%' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-1 h-2.5 w-full overflow-hidden rounded-full bg-muted" v-if="props.cantClientes > 0">
                                    <div class="flex h-full w-full">
                                        <div
                                            class="h-full bg-sky-500 transition-all duration-300"
                                            :style="{ width: `${(props.cantClientesNacionalidadMX / props.cantClientes) * 100}%` }" />
                                        <div
                                            class="h-full bg-amber-400 transition-all duration-300"
                                            :style="{ width: `${(props.cantClientesExtranjeros / props.cantClientes) * 100}%` }" />
                                    </div>
                                </div>
                            </div>
                        </section>
                    </aside>
                </section>
            </main>
        </FadeIn>
    </AppLayout>
</template>
