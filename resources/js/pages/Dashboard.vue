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
    Descripcion: string;
    created_at: string;
    Estatus: string;
};

type DashboardProps = {
    totalAlertas: number;
    alertasHoy: number;
    alertasPorEstatus: Record<string, number>;
    alertasAbiertas: number;
    ultimasAlertas: UltimaAlerta[];
    cantClientes: number;
    cantClientesActivos: number;
    cantClientesNacionalidadMX: number;
    cantClientesExtranjeros: number;
    cantClientesPPE: number;
};

const props = defineProps<DashboardProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const estatusData = computed(() =>
    Object.entries(props.alertasPorEstatus ?? {}).map(([estatus, total]) => ({
        estatus,
        total,
    })),
);

const totalAlertasEstatus = computed(() =>
    estatusData.value.reduce((acc, item) => acc + item.total, 0),
);

function getEstatusColor(estatus: string) {
    switch (estatus) {
        case 'Generado':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-200';
        case 'Analizado':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200';
        case 'Cerrado':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200';
        case 'Reportado':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-200';
        case 'Enviado':
            return 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
            <main
                class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-2xl border border-sidebar-border/60 bg-gradient-to-b from-muted/40 via-transparent to-transparent p-6 shadow-sm">
                <!-- Encabezado de página -->
                <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1.5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                            Vista general operativa
                        </p>
                        <h1 class="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                            Estado de alertas y salud de clientes
                        </h1>
                        <p class="max-w-2xl text-xs text-muted-foreground sm:text-sm">
                            Entiende en segundos el volumen de alertas, su nivel de riesgo actual y cómo se
                            distribuyen sobre tu base de clientes.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 self-stretch sm:self-auto sm:items-end sm:justify-end">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-dashed border-sidebar-border/70 bg-background/80 px-3 py-1 text-[11px] text-muted-foreground shadow-sm backdrop-blur-sm">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.25)]" />
                            <span>Datos actualizados en tiempo real</span>
                        </div>
                    </div>
                </header>

                <!-- Layout principal: columna de alertas + columna de clientes -->
                <section
                    class="grid gap-6 xl:grid-cols-[minmax(0,2.1fr)_minmax(0,1.2fr)]">
                    <!-- Columna izquierda: foco en alertas -->
                    <div class="flex flex-col gap-4">
                        <!-- Indicadores principales de alertas -->
                        <section
                            aria-label="Resumen de alertas"
                            class="grid gap-4 md:grid-cols-3">
                            <article
                                class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-sidebar-border/70 bg-card/95 p-4 shadow-sm backdrop-blur-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-sidebar-border">
                                <div
                                    class="pointer-events-none absolute inset-y-3 right-3 w-10 rounded-full bg-amber-500/10 opacity-80 transition-opacity duration-200 group-hover:opacity-100" />
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                                    Alertas generadas hoy
                                </p>
                                <p class="mt-3 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
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

                            <article
                                class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-rose-400/70 bg-card/95 p-4 shadow-sm backdrop-blur-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-rose-500/70">
                                <div
                                    class="pointer-events-none absolute inset-y-3 right-3 w-10 rounded-full bg-rose-500/10 opacity-80 transition-opacity duration-200 group-hover:opacity-100" />
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-600 dark:text-rose-300">
                                    Alertas abiertas
                                </p>
                                <p class="mt-3 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
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

                            <article
                                class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-sidebar-border/70 bg-card/95 p-4 shadow-sm backdrop-blur-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-sidebar-border">
                                <div
                                    class="pointer-events-none absolute inset-y-3 right-3 w-10 rounded-full bg-primary/5 opacity-80 transition-opacity duration-200 group-hover:opacity-100" />
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                                    Total de alertas
                                </p>
                                <p class="mt-3 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                                    {{ props.totalAlertas.toLocaleString() }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Distribuidas actualmente en múltiples estatus
                                </p>
                            </article>
                        </section>

                        <!-- Profundización en alertas: estatus + últimas -->
                        <section
                            aria-label="Detalle de alertas"
                            class="space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-sidebar-border">
                            <!-- Alertas por estatus -->
                            <article class="space-y-3 border-b border-sidebar-border/40 pb-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                                            Alertas por estatus
                                        </h2>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            Visualiza dónde se concentra el flujo de trabajo a lo largo del ciclo de vida.
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end gap-1 text-right">
                                        <span class="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                                            Total en estatus
                                        </span>
                                        <span class="text-sm font-semibold text-foreground tabular-nums">
                                            {{ totalAlertasEstatus.toLocaleString() }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    v-if="estatusData.length"
                                    class="space-y-3">
                                    <div
                                        v-for="item in estatusData"
                                        :key="item.estatus"
                                        class="space-y-1 rounded-lg bg-muted/40 px-3 py-2 transition-colors duration-150 hover:bg-muted focus-within:ring-1 focus-within:ring-primary/40">
                                        <div class="flex items-center justify-between text-sm">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ring-black/5 dark:ring-white/5"
                                                    :class="getEstatusColor(item.estatus)">
                                                    {{ item.estatus }}
                                                </span>
                                            </div>
                                            <div class="flex items-baseline gap-2">
                                                <span class="text-sm font-semibold text-foreground tabular-nums">
                                                    {{ item.total.toLocaleString() }}
                                                </span>
                                                <span class="text-xs text-muted-foreground">
                                                    {{
                                                        totalAlertasEstatus > 0
                                                            ? `${((item.total / totalAlertasEstatus) * 100).toFixed(1)}%`
                                                            : '0.0%'
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                            <div
                                                class="h-full rounded-full bg-primary/80 transition-all duration-300 ease-out"
                                                :style="{
                                                    width:
                                                        totalAlertasEstatus > 0
                                                            ? `${(item.total / totalAlertasEstatus) * 100}%`
                                                            : '0%',
                                                }" />
                                        </div>
                                    </div>
                                </div>
                                <p
                                    v-else
                                    class="text-sm text-muted-foreground">
                                    No hay información de estatus de alertas.
                                </p>
                            </article>

                            <!-- Últimas alertas dentro del mismo contexto -->
                            <article aria-label="Últimas alertas generadas" class="space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                                            Últimas alertas generadas
                                        </h2>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            Registros más recientes para priorizar la atención inmediata.
                                        </p>
                                    </div>
                                    <span class="text-xs text-muted-foreground">
                                        Mostrando {{ props.ultimasAlertas.length }} registros recientes
                                    </span>
                                </div>

                                <div class="-mx-4 overflow-x-auto rounded-lg border border-sidebar-border/60 bg-background/40">
                                    <table class="min-w-full text-left text-sm">
                                        <thead
                                            class="border-b border-sidebar-border/70 bg-muted/60 text-xs uppercase text-muted-foreground">
                                            <tr>
                                                <th class="px-3 py-2 font-medium">ID</th>
                                                <th class="px-3 py-2 font-medium">Cliente</th>
                                                <th class="px-3 py-2 font-medium">Descripción</th>
                                                <th class="px-3 py-2 font-medium">Fecha creación</th>
                                                <th class="px-3 py-2 font-medium">Estatus</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="alerta in props.ultimasAlertas"
                                                :key="alerta.IDRegistroAlerta"
                                                class="border-b border-sidebar-border/40 text-xs last:border-0 odd:bg-muted/20 hover:bg-muted/40 focus-within:bg-muted/40 transition-colors">
                                                <td class="px-3 py-2 font-mono text-[11px] text-muted-foreground tabular-nums">
                                                    {{ alerta.IDRegistroAlerta }}
                                                </td>
                                                <td class="px-3 py-2 text-foreground">
                                                    {{ alerta.Cliente }}
                                                </td>
                                                <td class="px-3 py-2 text-muted-foreground">
                                                    <span class="block max-w-xs truncate sm:max-w-sm md:max-w-md">
                                                        {{ alerta.Descripcion }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-muted-foreground">
                                                    {{ new Date(alerta.created_at).toLocaleString() }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    <span
                                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                                        :class="getEstatusColor(alerta.Estatus)">
                                                        {{ alerta.Estatus }}
                                                    </span>
                                                </td>
                                            </tr>

                                            <tr v-if="!props.ultimasAlertas.length">
                                                <td
                                                    colspan="5"
                                                    class="px-3 py-4 text-center text-xs text-muted-foreground">
                                                    No hay alertas recientes para mostrar.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        </section>
                    </div>

                    <!-- Columna derecha: foco en clientes -->
                    <aside class="flex flex-col gap-4">
                        <!-- Resumen de clientes -->
                        <section
                            aria-label="Resumen de clientes"
                            class="group relative overflow-hidden rounded-xl border border-emerald-400/70 bg-card/95 p-4 shadow-sm backdrop-blur-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-emerald-500/70">
                            <div
                                class="pointer-events-none absolute inset-y-3 right-3 w-10 rounded-full bg-emerald-500/10 opacity-80 transition-opacity duration-200 group-hover:opacity-100" />
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-300">
                                        Base de clientes
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Tamaño total de la base y nivel de relación activa.
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                                        Total de clientes
                                    </p>
                                    <p class="mt-1 text-xl font-semibold tracking-tight text-foreground">
                                        {{ props.cantClientes.toLocaleString() }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-2 grid gap-3 border-t border-emerald-500/30 pt-3 text-xs sm:grid-cols-2">
                                <div class="space-y-1">
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-muted-foreground">Clientes activos</span>
                                        <span class="text-sm font-semibold tabular-nums">
                                            {{ props.cantClientesActivos.toLocaleString() }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{
                                            props.cantClientes > 0
                                                ? `${((props.cantClientesActivos / props.cantClientes) * 100).toFixed(1)}% del total de clientes`
                                                : 'Sin clientes registrados'
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-muted-foreground">Clientes con PPE activa</span>
                                        <span class="text-sm font-semibold tabular-nums">
                                            {{ props.cantClientesPPE.toLocaleString() }}
                                        </span>
                                    </div>
                                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full bg-emerald-500 transition-all duration-300 ease-out"
                                            :style="{
                                                width:
                                                    props.cantClientes > 0
                                                        ? `${(props.cantClientesPPE / props.cantClientes) * 100}%`
                                                        : '0%',
                                            }" />
                                    </div>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ props.cantClientes > 0
                                            ? ((props.cantClientesPPE / props.cantClientes) * 100).toFixed(2)
                                            : '0.00' }}% de la base de clientes
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- Perfil de clientes: distribución por nacionalidad -->
                        <section
                            aria-label="Perfil de clientes"
                            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-sidebar-border">
                            <div class="mb-3">
                                <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                                    Distribución por nacionalidad
                                </h2>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Cómo se compone la base de clientes entre nacionales y extranjeros.
                                </p>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-foreground">Mexicanos</span>
                                    <span class="font-medium tabular-nums">
                                        {{ props.cantClientesNacionalidadMX.toLocaleString() }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-foreground">Extranjeros</span>
                                    <span class="font-medium tabular-nums">
                                        {{ props.cantClientesExtranjeros.toLocaleString() }}
                                    </span>
                                </div>
                                <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="flex h-full w-full"
                                        v-if="props.cantClientes > 0">
                                        <div
                                            class="h-full bg-sky-500 transition-all duration-300 ease-out"
                                            :style="{
                                                width: `${(props.cantClientesNacionalidadMX / props.cantClientes) * 100}%`,
                                            }" />
                                        <div
                                            class="h-full bg-amber-500 transition-all duration-300 ease-out"
                                            :style="{
                                                width: `${(props.cantClientesExtranjeros / props.cantClientes) * 100}%`,
                                            }" />
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
