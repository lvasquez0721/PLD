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
            <div
                class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-2xl border border-sidebar-border/60 bg-gradient-to-b from-muted/40 via-transparent to-transparent p-6 shadow-sm">
                <!-- Tarjetas resumen principales -->
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div
                        class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-sky-50 to-sky-100 p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg dark:from-sky-900/40 dark:to-sky-800/40 dark:border-sidebar-border">
                        <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-white/10" />
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-sky-700/90 dark:text-sky-200/90">
                            Total de alertas
                        </p>
                        <p class="mt-3 text-3xl font-bold text-sky-900 dark:text-sky-50">
                            {{ props.totalAlertas.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-sky-700/80 dark:text-sky-200/80">
                            Incluye todos los estatus registrados
                        </p>
                    </div>

                    <div
                        class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-amber-50 to-amber-100 p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg dark:from-amber-900/40 dark:to-amber-800/40 dark:border-sidebar-border">
                        <div class="pointer-events-none absolute -right-6 -top-8 h-24 w-24 rounded-full bg-white/10" />
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-amber-700/90 dark:text-amber-200/90">
                            Alertas generadas hoy
                        </p>
                        <p class="mt-3 text-3xl font-bold text-amber-900 dark:text-amber-50">
                            {{ props.alertasHoy.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-amber-700/80 dark:text-amber-200/80">
                            Comparado con el total histórico
                        </p>
                    </div>

                    <div
                        class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-rose-50 to-rose-100 p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg dark:from-rose-900/40 dark:to-rose-800/40 dark:border-sidebar-border">
                        <div class="pointer-events-none absolute -right-8 -top-6 h-24 w-24 rounded-full bg-white/10" />
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-rose-700/90 dark:text-rose-200/90">
                            Alertas abiertas
                        </p>
                        <p class="mt-3 text-3xl font-bold text-rose-900 dark:text-rose-50">
                            {{ props.alertasAbiertas.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-rose-700/80 dark:text-rose-200/80">
                            No incluyen alertas con estatus "Cerrado"
                        </p>
                    </div>

                    <div
                        class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-emerald-50 to-emerald-100 p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg dark:from-emerald-900/40 dark:to-emerald-800/40 dark:border-sidebar-border">
                        <div class="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full bg-white/10" />
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.08em] text-emerald-700/90 dark:text-emerald-200/90">
                            Total de clientes
                        </p>
                        <p class="mt-3 text-3xl font-bold text-emerald-900 dark:text-emerald-50">
                            {{ props.cantClientes.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-200/80">
                            Incluye activos e inactivos
                        </p>
                    </div>
                </div>

                <!-- Información de clientes -->
                <div class="grid gap-4 md:grid-cols-3">
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-sidebar-border">
                        <h2 class="mb-3 text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                            Situación de clientes
                        </h2>
                        <div class="space-y-3">
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm text-foreground">Clientes activos</span>
                                <span class="text-lg font-semibold tabular-nums">
                                    {{ props.cantClientesActivos.toLocaleString() }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm text-muted-foreground">
                                <span>Clientes mexicanos</span>
                                <span class="font-medium text-foreground tabular-nums">
                                    {{ props.cantClientesNacionalidadMX.toLocaleString() }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm text-muted-foreground">
                                <span>Clientes extranjeros</span>
                                <span class="font-medium text-foreground tabular-nums">
                                    {{ props.cantClientesExtranjeros.toLocaleString() }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-sidebar-border">
                        <h2 class="mb-3 text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                            Clientes PPE
                        </h2>
                        <div class="space-y-2">
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm text-foreground">Clientes con PPE activa</span>
                                <span class="text-lg font-semibold tabular-nums">
                                    {{ props.cantClientesPPE.toLocaleString() }}
                                </span>
                            </div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-emerald-500"
                                    :style="{
                                        width:
                                            props.cantClientes > 0
                                                ? `${(props.cantClientesPPE / props.cantClientes) * 100}%`
                                                : '0%',
                                    }" />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ props.cantClientes > 0
                                    ? ((props.cantClientesPPE / props.cantClientes) * 100).toFixed(2)
                                    : '0.00' }}% de la base de clientes
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-sidebar-border">
                        <h2 class="mb-3 text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                            Distribución de nacionalidad
                        </h2>
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
                                        class="h-full bg-sky-500"
                                        :style="{
                                            width: `${(props.cantClientesNacionalidadMX / props.cantClientes) * 100}%`,
                                        }" />
                                    <div
                                        class="h-full bg-amber-500"
                                        :style="{
                                            width: `${(props.cantClientesExtranjeros / props.cantClientes) * 100}%`,
                                        }" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alertas por estatus y últimas alertas -->
                <div class="grid gap-4 lg:grid-cols-3">
                    <!-- Alertas por estatus -->
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-sidebar-border">
                        <div class="mb-4 flex items-center justify-between gap-2">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                                Alertas por estatus
                            </h2>
                            <span class="text-xs text-muted-foreground">
                                Total en estatus: {{ totalAlertasEstatus.toLocaleString() }}
                            </span>
                        </div>

                        <div
                            v-if="estatusData.length"
                            class="space-y-3">
                            <div
                                v-for="item in estatusData"
                                :key="item.estatus"
                                class="space-y-1 rounded-lg bg-muted/40 px-3 py-2">
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
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
                                        class="h-full rounded-full bg-primary/80"
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
                    </div>

                    <!-- Últimas alertas -->
                    <div
                        class="lg:col-span-2 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-sidebar-border">
                        <div class="mb-4 flex items-center justify-between gap-2">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                                Últimas alertas generadas
                            </h2>
                            <span class="text-xs text-muted-foreground">
                                Mostrando {{ props.ultimasAlertas.length }} registros recientes
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead
                                    class="border-b border-sidebar-border/70 bg-muted/40 text-xs uppercase text-muted-foreground">
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
                                        class="border-b border-sidebar-border/40 text-xs last:border-0 odd:bg-muted/20 hover:bg-muted/40">
                                        <td class="px-3 py-2 font-mono text-[11px] text-muted-foreground tabular-nums">
                                            {{ alerta.IDRegistroAlerta }}
                                        </td>
                                        <td class="px-3 py-2 text-foreground">
                                            {{ alerta.Cliente }}
                                        </td>
                                        <td class="px-3 py-2 text-muted-foreground">
                                            {{ alerta.Descripcion }}
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
                    </div>
                </div>
            </div>
        </FadeIn>
    </AppLayout>
</template>
