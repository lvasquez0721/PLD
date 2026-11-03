<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarRange,
    FileSpreadsheet,
    Plus,
    ShieldCheck,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Ejercicio {
    id: number;
    anio: number;
    nombre: string;
    fecha_inicio: string;
    fecha_fin: string;
    estado: 'borrador' | 'calculado' | 'cerrado';
    totales: {
        inherente?: number;
        residual?: number;
        residual_final?: number;
        nivel?: string;
    } | null;
    polizas_count: number;
    operaciones_count: number;
    siniestros_count: number;
    alertas_count: number;
    importaciones_count: number;
    calculado_at: string | null;
}

const props = defineProps<{
    ejercicios: Ejercicio[];
    puedeEditar: boolean;
    anioSugerido: number;
    toast?: { type: 'success' | 'error' | 'warning'; message: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'EBR / Evaluación de Riesgos', href: '/ebr' },
];
const mostrarFormulario = ref(false);

const form = useForm({
    anio: props.anioSugerido,
    fecha_inicio: `${props.anioSugerido}-01-01`,
    fecha_fin: `${props.anioSugerido}-12-31`,
    observaciones: '',
});

function crear() {
    form.post('/ebr', {
        preserveScroll: true,
        onSuccess: () => {
            mostrarFormulario.value = false;
            form.reset('observaciones');
        },
    });
}

function eliminar(ejercicio: Ejercicio) {
    if (
        !confirm(
            `¿Eliminar el ejercicio EBR ${ejercicio.anio}? Se borrarán sus insumos y resultados.`,
        )
    )
        return;
    router.delete(`/ebr/${ejercicio.id}`, { preserveScroll: true });
}

function badge(estado: string) {
    if (estado === 'calculado')
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
    if (estado === 'cerrado')
        return 'bg-slate-200 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300';
    return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
}

function riesgoClase(nivel?: string) {
    const n = (nivel ?? '').toLowerCase();
    if (n.includes('muy alto'))
        return 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300';
    if (n.includes('alto'))
        return 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300';
    if (n.includes('medio'))
        return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
}
</script>

<template>
    <Head title="EBR / Evaluación de Riesgos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-sm font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        <ShieldCheck class="h-4 w-4" /> Cumplimiento PLD
                    </div>
                    <h1
                        class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        EBR / Evaluación Basada en Riesgos
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Ejercicios anuales, importación de insumos, cálculo por
                        elemento y reporte institucional.
                    </p>
                </div>
                <button
                    v-if="puedeEditar"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    @click="mostrarFormulario = !mostrarFormulario"
                >
                    <Plus class="h-4 w-4" /> Nuevo ejercicio
                </button>
            </div>

            <div
                v-if="toast"
                class="rounded-xl border px-4 py-3 text-sm"
                :class="
                    toast.type === 'error'
                        ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                "
            >
                {{ toast.message }}
            </div>

            <form
                v-if="mostrarFormulario && puedeEditar"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                @submit.prevent="crear"
            >
                <h2
                    class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                >
                    Crear ejercicio anual
                </h2>
                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <label class="text-sm">
                        <span
                            class="mb-1 block font-medium text-slate-700 dark:text-slate-300"
                            >Año</span
                        >
                        <input
                            v-model.number="form.anio"
                            type="number"
                            min="2000"
                            max="2100"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 dark:border-neutral-700 dark:bg-neutral-900"
                        />
                        <span
                            v-if="form.errors.anio"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.anio }}</span
                        >
                    </label>
                    <label class="text-sm">
                        <span
                            class="mb-1 block font-medium text-slate-700 dark:text-slate-300"
                            >Inicio</span
                        >
                        <input
                            v-model="form.fecha_inicio"
                            type="date"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 dark:border-neutral-700 dark:bg-neutral-900"
                        />
                        <span
                            v-if="form.errors.fecha_inicio"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.fecha_inicio }}</span
                        >
                    </label>
                    <label class="text-sm">
                        <span
                            class="mb-1 block font-medium text-slate-700 dark:text-slate-300"
                            >Fin</span
                        >
                        <input
                            v-model="form.fecha_fin"
                            type="date"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 dark:border-neutral-700 dark:bg-neutral-900"
                        />
                        <span
                            v-if="form.errors.fecha_fin"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.fecha_fin }}</span
                        >
                    </label>
                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing ? 'Creando…' : 'Crear ejercicio'
                            }}
                        </button>
                    </div>
                </div>
            </form>

            <div
                v-if="ejercicios.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-10 text-center dark:border-neutral-700 dark:bg-neutral-950/40"
            >
                <FileSpreadsheet class="mx-auto h-10 w-10 text-slate-400" />
                <h2
                    class="mt-3 text-lg font-semibold text-slate-800 dark:text-slate-200"
                >
                    Aún no hay ejercicios EBR
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Crea el primer ejercicio anual para importar
                    clientes/pólizas, siniestros y alertas.
                </p>
            </div>

            <div
                v-else
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-slate-200 text-sm dark:divide-neutral-800"
                    >
                        <thead class="bg-slate-50 dark:bg-neutral-900/70">
                            <tr
                                class="text-left text-xs tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                <th class="px-4 py-3">Ejercicio</th>
                                <th class="px-4 py-3">Periodo</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Insumos</th>
                                <th class="px-4 py-3">Riesgo final</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-neutral-900"
                        >
                            <tr
                                v-for="e in ejercicios"
                                :key="e.id"
                                class="align-top hover:bg-slate-50/70 dark:hover:bg-neutral-900/40"
                            >
                                <td class="px-4 py-4">
                                    <div
                                        class="font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        EBR {{ e.anio }}
                                    </div>
                                    <div
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ e.nombre }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-4 text-slate-600 dark:text-slate-300"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <CalendarRange class="h-3.5 w-3.5" />
                                        {{ e.fecha_inicio }} al
                                        {{ e.fecha_fin }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                        :class="badge(e.estado)"
                                        >{{ e.estado }}</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-4 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    <div>
                                        {{ e.polizas_count }} pólizas ·
                                        {{ e.operaciones_count }} operaciones ·
                                        {{ e.siniestros_count }} siniestros ·
                                        {{ e.alertas_count }} alertas
                                    </div>
                                    <div class="text-slate-400">
                                        {{ e.importaciones_count }} carga(s)
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <template v-if="e.totales">
                                        <div
                                            class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                Number(
                                                    e.totales.residual_final ??
                                                        0,
                                                ).toFixed(2)
                                            }}
                                        </div>
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                riesgoClase(e.totales.nivel)
                                            "
                                            >{{ e.totales.nivel }}</span
                                        >
                                    </template>
                                    <span v-else class="text-xs text-slate-400"
                                        >Sin calcular</span
                                    >
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div
                                        class="flex flex-wrap justify-end gap-2"
                                    >
                                        <Link
                                            :href="`/ebr/${e.id}`"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-neutral-700 dark:text-slate-200 dark:hover:bg-neutral-800"
                                            >Abrir</Link
                                        >
                                        <button
                                            v-if="
                                                puedeEditar &&
                                                e.estado !== 'cerrado'
                                            "
                                            class="rounded-lg border border-red-200 p-1.5 text-red-600 transition hover:bg-red-50 dark:border-red-500/30 dark:hover:bg-red-500/10"
                                            title="Eliminar"
                                            @click="eliminar(e)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
