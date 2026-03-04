<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { FileText } from 'lucide-vue-next';
import { Label } from '@/components/ui/label';
import { activeTab, setTab } from "../../../scripts/setTab.js";
import axios from 'axios';
import '@vuepic/vue-datepicker/dist/main.css';
import { router } from '@inertiajs/vue3'
import Toast from '@/components/ui/alert/Toast.vue'
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes/index.js';


// Tipado del buzon
interface BuzonItem {
    idBuzonPreocupantes: number
    IDReporteOP: string
    Fecha: string
    Descripcion: string
    Usuario: string
    Estatus: string
}

defineProps<{
    buzon: BuzonItem[]
    toast?: string
}>()

// IDs seleccionados
const seleccionados = ref<string[]>([]);
// Función para marcar/desmarcar
const toggleSeleccion = (id: string) => {
    if (seleccionados.value.includes(id)) {
        seleccionados.value = seleccionados.value.filter(item => item !== id);
    } else {
        seleccionados.value.push(id);
    }
};

// Función para enviar al backend
const pasarAlertas = async () => {
    if (seleccionados.value.length === 0) {
        alert('Selecciona al menos un reporte.');
        return;
    }

    try {
        await axios.post('/buzon-preocupantes/pasar-alertas', {
            ids: seleccionados.value
        });

        toastMessage.value = 'Alertas generadas correctamente.'
        toastType.value = 'success'
        showToast.value = true
        seleccionados.value = [];
        router.reload()

    } catch (error: any) {
        console.error(error);
        toastMessage.value = 'Ocurrió un error al generar las alertas. Verifica los datos ingresados.'
        toastType.value = 'error'
        showToast.value = true
    }
};

// Variable reactiva
const buscar = ref('')
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref<'success' | 'warning' | 'error'>('success')
const loading = ref(false)

const guardar = async () => {
    try {
        loading.value = true
        await axios.post('/buzon-preocupantes/guardar', {
            Descripcion: buscar.value,
        });

        toastMessage.value = 'Reporte registrado correctamente.'
        toastType.value = 'success'
        showToast.value = true

        buscar.value = ''

        setTimeout(() => {
            router.reload({ only: ['buzon'] })
            loading.value = false
        }, 800)

        setTab('altaListas')
    } catch (error) {
        toastMessage.value = 'Error al registrar reporte. Verifica los datos ingresados.'
        toastType.value = 'error'
        showToast.value = true
        loading.value = false
        console.error(error)
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Buzon de operaciones preocupantes',
        href: dashboard().url,
    },
];

</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s cubic-bezier(0.25, 0.1, 0.25, 1);
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.tab-enter-active,
.tab-leave-active {
    transition: opacity 0.25s cubic-bezier(0.25, 0.1, 0.25, 1),
        transform 0.25s cubic-bezier(0.25, 0.1, 0.25, 1);
}

.tab-enter-from,
.tab-leave-to {
    opacity: 0;
    transform: translateY(4px);
}

.loader-overlay {
    position: fixed;
    inset: 0;
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    transition: background-color 0.35s cubic-bezier(0.25, 0.1, 0.25, 1);
}

.loader-overlay {
    background-color: rgba(248, 250, 252, 0.75);
}

:deep(html.dark) .loader-overlay,
:deep(body.dark) .loader-overlay {
    background-color: rgba(10, 10, 10, 0.65);
}

.spinner {
    width: 48px;
    height: 48px;
    border: 3px solid rgba(59, 130, 246, 0.15);
    border-top-color: rgb(59, 130, 246);
    border-radius: 50%;
    animation: spin 0.85s cubic-bezier(0.5, 0, 0.5, 1) infinite;
}

:deep(html.dark) .spinner,
:deep(body.dark) .spinner {
    border-color: rgba(148, 163, 184, 0.2);
    border-top-color: rgb(96, 165, 250);
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Checkbox personalizado - estados visibles */
.checkbox-custom {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.25, 0.1, 0.25, 1);
    accent-color: rgb(59, 130, 246);
}

:deep(.dark) .checkbox-custom {
    accent-color: rgb(96, 165, 250);
}

/* ===== Tabs blue highlight for active ==== */
.tab-active {
    /* background gradient blue & white for light, blue & gray for dark */
    background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
    color: #fff !important;
    /* for dark mode override */
    /* Optionally: border-color: #3b82f6; */
    /* Optionally: font-weight: 500; */
    /* Extra shadow for pop effect */
    box-shadow: 0 2px 12px 0 rgba(59,130,246,0.12);
    border: 1.5px solid #3b82f6;
}
.tab-active:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}
:deep(.dark) .tab-active {
    background: linear-gradient(90deg, #1e40af 0%, #3b82f6 100%);
    color: #fff !important;
    border-color: #2563eb;
}
/* if you want to keep the same rounded corners etc as existing, don't touch them */
</style>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Buzón de operaciones preocupantes" />

        <div class="max-w-[1800px] mx-auto px-6 sm:px-8 py-6 space-y-6">
            <!-- Tabs -->
            <div
                class="flex gap-1 w-fit p-1 mb-4 bg-slate-100/60 dark:bg-neutral-900/80 backdrop-blur-sm rounded-[14px] border border-slate-100/50 dark:border-neutral-800/70 shadow-[0_2px_8px_rgba(15,23,42,0.04)] dark:shadow-[0_2px_8px_rgba(0,0,0,0.15)] transition-all duration-700">
                <button @click="setTab('altaListas')" :class="[
                    'py-2.5 px-6 font-medium text-[13px] tracking-[0.02em] rounded-[10px] transition-all duration-300 cubic-bezier(0.25,0.1,0.25,1) cursor-pointer',
                    activeTab === 'altaListas'
                        ? 'tab-active'
                        : 'text-slate-600 dark:text-neutral-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-white/50 dark:hover:bg-neutral-800/50'
                ]">
                    Buzón
                </button>
                <button @click="setTab('consulta')" :class="[
                    'py-2.5 px-6 font-medium text-[13px] tracking-[0.02em] rounded-[10px] transition-all duration-300 cubic-bezier(0.25,0.1,0.25,1) cursor-pointer',
                    activeTab === 'consulta'
                        ? 'tab-active'
                        : 'text-slate-600 dark:text-neutral-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-white/50 dark:hover:bg-neutral-800/50'
                ]">
                    Registrar Reporte
                </button>
            </div>

            <!-- Contenido -->
            <Transition name="tab" mode="out-in">
                <!-- Lista de buzón -->
                <form v-if="activeTab === 'altaListas'" @submit.prevent="pasarAlertas" class="space-y-4">
                    <div
                        class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-xl shadow-gray-200/50 dark:border-neutral-800/80 dark:bg-neutral-950 dark:shadow-neutral-900/50">
                        <!-- Header -->
                        <div
                            class="border-b border-gray-200/60 bg-gradient-to-r from-gray-50/95 to-gray-50/80 px-6 py-4.5 backdrop-blur-sm dark:border-neutral-800/60 dark:from-neutral-900/95 dark:to-neutral-900/80">
                            <h2 class="text-base font-semibold tracking-tight text-gray-900 dark:text-neutral-100">
                                Buzón de operaciones preocupantes
                            </h2>
                        </div>

                        <!-- Tabla -->
                        <div class="p-6">
                            <div class="overflow-x-auto rounded-xl">
                                <table class="min-w-full divide-y divide-gray-200/60 dark:divide-neutral-800/60">
                                    <thead
                                        class="bg-gradient-to-r from-gray-50/95 to-gray-50/80 backdrop-blur-sm dark:from-neutral-900/95 dark:to-neutral-900/80">
                                        <tr>
                                            <th scope="col"
                                                class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300 whitespace-nowrap">
                                                Seleccionar
                                            </th>
                                            <th scope="col"
                                                class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300 whitespace-nowrap">
                                                ID
                                            </th>
                                            <th scope="col"
                                                class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300 whitespace-nowrap">
                                                Fecha
                                            </th>
                                            <th scope="col"
                                                class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300 whitespace-nowrap">
                                                Descripción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-gray-200/60 bg-white dark:divide-neutral-800/60 dark:bg-neutral-950">
                                        <tr v-for="item in buzon" :key="item.idBuzonPreocupantes"
                                            class="transition-all duration-150 hover:bg-gradient-to-r hover:from-gray-50/80 hover:to-gray-50/50 hover:shadow-sm dark:hover:from-neutral-900/50 dark:hover:to-neutral-900/30">
                                            <td class="px-5 py-4">
                                                <input type="checkbox" :value="item.IDReporteOP"
                                                    @change="toggleSeleccion(item.IDReporteOP)"
                                                    :checked="seleccionados.includes(item.IDReporteOP)"
                                                    class="checkbox-custom focus:ring-2 focus:ring-blue-400/30 dark:focus:ring-blue-500/40 focus:ring-offset-2 dark:focus:ring-offset-neutral-900 rounded" />
                                            </td>
                                            <td
                                                class="px-5 py-4 text-sm font-medium text-gray-900 dark:text-neutral-100 whitespace-nowrap">
                                                {{ item.idBuzonPreocupantes }}
                                            </td>
                                            <td
                                                class="px-5 py-4 text-sm font-medium text-gray-700 dark:text-neutral-300 whitespace-nowrap">
                                                {{ new Intl.DateTimeFormat('es-MX', {
                                                    day: '2-digit', month: '2-digit',
                                                    year: 'numeric'
                                                }).format(new Date(item.Fecha)) }}
                                            </td>
                                            <td
                                                class="px-5 py-4 text-sm font-normal text-gray-700 dark:text-neutral-300">
                                                {{ item.Descripcion }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" :disabled="seleccionados.length === 0"
                            class="px-7 py-3.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-[14px] font-semibold tracking-[0.01em] rounded-[14px]
                                shadow-[0_3px_12px_rgba(59,130,246,0.18)] hover:shadow-[0_5px_18px_rgba(59,130,246,0.26)]
                                hover:from-blue-700 hover:to-blue-800
                                disabled:from-slate-300/80 disabled:to-slate-400/80 dark:disabled:from-neutral-800/80 dark:disabled:to-neutral-700/70 disabled:shadow-none disabled:cursor-not-allowed
                                transition-all duration-300 cubic-bezier(0.25,0.1,0.25,1) transform hover:scale-[1.015] hover:-translate-y-0.5 active:translate-y-0 active:scale-100
                                focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-neutral-950">
                            Pasar alerta
                        </button>
                    </div>
                </form>

                <!-- Formulario Registrar Reporte -->
                <form v-else-if="activeTab === 'consulta'" @submit.prevent="guardar" class="space-y-4">
                    <div
                        class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-xl shadow-gray-200/50 dark:border-neutral-800/80 dark:bg-neutral-950 dark:shadow-neutral-900/50">
                        <!-- Header -->
                        <div
                            class="border-b border-gray-200/60 bg-gradient-to-r from-gray-50/95 to-gray-50/80 px-6 py-4.5 backdrop-blur-sm dark:border-neutral-800/60 dark:from-neutral-900/95 dark:to-neutral-900/80">
                            <h2 class="text-base font-semibold tracking-tight text-gray-900 dark:text-neutral-100">
                                Registrar reporte preocupante
                            </h2>
                        </div>

                        <!-- Body -->
                        <div class="px-6 py-6">
                            <div class="space-y-4">
                                <Label for="buscar"
                                    class="block text-[11px] font-medium text-slate-600/85 dark:text-neutral-300/85 uppercase tracking-[0.05em] mb-3">
                                    Reporte
                                </Label>
                                <input v-model="buscar" id="buscar" type="text" placeholder="Ingrese su reporte"
                                    class="w-full rounded-xl border border-gray-300/80 bg-white px-4 py-3.5 text-[14px] font-light tracking-[0.003em] text-slate-800 shadow-sm transition-all duration-200 placeholder:text-slate-400 hover:border-gray-400/80 hover:shadow-md focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 dark:border-neutral-700/80 dark:bg-neutral-900 dark:text-slate-100 dark:placeholder:text-neutral-500 dark:hover:border-neutral-600 dark:focus:border-blue-400 dark:focus:ring-blue-400/50" />
                            </div>
                        </div>

                        <!-- Footer -->
                        <div
                            class="border-t border-gray-200/60 bg-gradient-to-r from-gray-50/95 to-gray-50/80 px-6 py-4 backdrop-blur-sm dark:border-neutral-800/60 dark:from-neutral-900/95 dark:to-neutral-900/80">
                            <div class="flex justify-end">
                                <button type="submit" :disabled="loading || !buscar.trim()"
                                    class="px-7 py-3.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-[14px] font-semibold tracking-[0.01em] rounded-[14px]
                                        shadow-[0_3px_12px_rgba(59,130,246,0.18)] hover:shadow-[0_5px_18px_rgba(59,130,246,0.26)]
                                        hover:from-blue-700 hover:to-blue-800
                                        disabled:from-slate-300/80 disabled:to-slate-400/80 dark:disabled:from-neutral-800/80 dark:disabled:to-neutral-700/70 disabled:shadow-none disabled:cursor-not-allowed
                                        transition-all duration-300 cubic-bezier(0.25,0.1,0.25,1) transform hover:scale-[1.015] hover:-translate-y-0.5 active:translate-y-0 active:scale-100
                                        focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-neutral-950">
                                    <span v-if="loading">Guardando...</span>
                                    <span v-else>Guardar</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </Transition>

            <Toast v-model="showToast" :message="toastMessage" :type="toastType" :duration="3000" />

            <Transition name="fade">
                <div v-if="loading" class="loader-overlay">
                    <div class="spinner"></div>
                </div>
            </Transition>
        </div>
    </AppLayout>
</template>
