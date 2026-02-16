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

        <Head title="Dashboard" />

        <div
            class="min-h-[calc(100vh-6rem)] bg-gradient-to-br from-slate-50/40 via-slate-50/60 to-blue-50/25 dark:from-neutral-950 dark:via-neutral-900/95 dark:to-neutral-900/90 transition-colors duration-700">
            <div class="max-w-[1800px] mx-auto px-6 sm:px-8 py-6">

                <!-- Tabs -->
                <div
                    class="flex gap-1 mb-8 p-1 bg-slate-100/60 dark:bg-neutral-900/80 backdrop-blur-sm rounded-[14px] border border-slate-100/50 dark:border-neutral-800/70 shadow-[0_2px_8px_rgba(15,23,42,0.04)] dark:shadow-[0_2px_8px_rgba(0,0,0,0.15)] w-fit transition-all duration-700">
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

                <!-- Contenido: Tabla Buzón -->
                <Transition name="tab" mode="out-in">
                    <form v-if="activeTab === 'altaListas'" @submit.prevent="pasarAlertas" class="space-y-6">
                        <div
                            class="bg-[#f8fafc]/70 dark:bg-neutral-900/90 backdrop-blur-xl rounded-[20px] shadow-[0_3px_20px_rgba(15,23,42,0.035)] dark:shadow-[0_3px_20px_rgba(0,0,0,0.24)] border border-slate-100/50 dark:border-neutral-800/70 overflow-hidden transition-all duration-700 hover:shadow-[0_7px_28px_rgba(15,23,42,0.05)] dark:hover:shadow-[0_7px_28px_rgba(0,0,0,0.28)]">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr
                                            class="bg-gradient-to-r from-slate-50/50 via-slate-50/40 to-slate-50/50 dark:from-neutral-800/80 dark:via-neutral-900/50 dark:to-neutral-800/80 border-b border-slate-100/50 dark:border-neutral-800/70">
                                            <th
                                                class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                                Seleccionar
                                            </th>
                                            <th
                                                class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                                ID
                                            </th>
                                            <th
                                                class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                                Fecha
                                            </th>
                                            <th
                                                class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                                Descripción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100/60 dark:divide-neutral-800/70">
                                        <tr v-for="item in buzon" :key="item.idBuzonPreocupantes"
                                            class="hover:bg-blue-50/20 dark:hover:bg-neutral-800/60 transition-all duration-300 cubic-bezier(0.25,0.1,0.25,1) group">
                                            <td class="px-6 py-4">
                                                <input type="checkbox" :value="item.IDReporteOP"
                                                    @change="toggleSeleccion(item.IDReporteOP)"
                                                    :checked="seleccionados.includes(item.IDReporteOP)"
                                                    class="checkbox-custom focus:ring-2 focus:ring-blue-400/30 dark:focus:ring-blue-500/40 focus:ring-offset-2 dark:focus:ring-offset-neutral-900 rounded" />
                                            </td>
                                            <td
                                                class="px-6 py-4 text-[13px] text-slate-700/85 dark:text-neutral-100/90 font-medium tracking-[0.003em]">
                                                {{ item.idBuzonPreocupantes }}
                                            </td>
                                            <td
                                                class="px-6 py-4 text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                                                {{ new Intl.DateTimeFormat('es-MX', {
                                                    day: '2-digit', month: '2-digit',
                                                    year: 'numeric' }).format(new Date(item.Fecha)) }}
                                            </td>
                                            <td
                                                class="px-6 py-4 text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                                                {{ item.Descripcion }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="seleccionados.length === 0"
                                class="px-7 py-3.5 bg-gradient-to-br from-blue-400/90 to-blue-500/90 text-white/95 text-[14px] font-medium tracking-[0.008em] rounded-[14px]
                  shadow-[0_3px_12px_rgba(59,130,246,0.13)] hover:shadow-[0_5px_18px_rgba(59,130,246,0.18)]
                  hover:from-blue-500/90 hover:to-blue-600/90
                  disabled:from-slate-300/80 disabled:to-slate-400/80 dark:disabled:from-neutral-800/80 dark:disabled:to-neutral-700/70 disabled:shadow-none disabled:cursor-not-allowed
                  transition-all duration-300 cubic-bezier(0.25,0.1,0.25,1) transform hover:scale-[1.015] hover:-translate-y-0.5 active:translate-y-0 active:scale-100
                  focus:outline-none focus:ring-2 focus:ring-blue-400/25 dark:focus:ring-blue-500/30 focus:ring-offset-2 focus:ring-offset-[#f8fafc]/50 dark:focus:ring-offset-neutral-900/50">
                                Pasar alerta
                            </button>
                        </div>
                    </form>

                    <!-- Formulario Registrar Reporte -->
                    <form v-else-if="activeTab === 'consulta'" @submit.prevent="guardar" class="space-y-6">
                        <div
                            class="bg-[#f8fafc]/70 dark:bg-neutral-900/90 backdrop-blur-xl rounded-[20px] shadow-[0_3px_20px_rgba(15,23,42,0.035)] dark:shadow-[0_3px_20px_rgba(0,0,0,0.24)] border border-slate-100/50 dark:border-neutral-800/70 p-8 transition-all duration-700 hover:shadow-[0_7px_28px_rgba(15,23,42,0.05)] dark:hover:shadow-[0_7px_28px_rgba(0,0,0,0.28)]">
                            <div class="space-y-4">
                                <Label for="buscar"
                                    class="block text-[11px] font-medium text-slate-600/85 dark:text-neutral-300/85 uppercase tracking-[0.05em] mb-3">
                                    Reporte
                                </Label>
                                <input v-model="buscar" id="buscar" type="text" placeholder="Ingrese su reporte" class="w-full px-4 py-3.5 rounded-[14px] text-[14px] font-light tracking-[0.003em] transition-all duration-300
                    border border-slate-200/80 dark:border-neutral-700/80
                    bg-white/80 dark:bg-neutral-800/80
                    text-slate-800 dark:text-slate-100
                    placeholder:text-slate-400 dark:placeholder:text-neutral-500
                    focus:outline-none focus:ring-2 focus:ring-blue-400/25 dark:focus:ring-blue-500/30 focus:border-blue-300/50 dark:focus:border-blue-500/50
                    shadow-[0_1px_3px_rgba(15,23,42,0.04)]" />
                            </div>
                            <div class="mt-6 flex justify-end">
                                <button type="submit" :disabled="loading || !buscar.trim()"
                                    class="px-7 py-3.5 bg-gradient-to-br from-blue-400/90 to-blue-500/90 text-white/95 text-[14px] font-medium tracking-[0.008em] rounded-[14px]
                    shadow-[0_3px_12px_rgba(59,130,246,0.13)] hover:shadow-[0_5px_18px_rgba(59,130,246,0.18)]
                    hover:from-blue-500/90 hover:to-blue-600/90
                    disabled:from-slate-300/80 disabled:to-slate-400/80 dark:disabled:from-neutral-800/80 dark:disabled:to-neutral-700/70 disabled:shadow-none disabled:cursor-not-allowed
                    transition-all duration-300 cubic-bezier(0.25,0.1,0.25,1) transform hover:scale-[1.015] hover:-translate-y-0.5 active:translate-y-0 active:scale-100
                    focus:outline-none focus:ring-2 focus:ring-blue-400/25 dark:focus:ring-blue-500/30 focus:ring-offset-2 focus:ring-offset-[#f8fafc]/50 dark:focus:ring-offset-neutral-900/50">
                                    <span v-if="loading">Guardando...</span>
                                    <span v-else>Guardar</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </Transition>
            </div>
        </div>

        <Toast v-model="showToast" :message="toastMessage" :type="toastType" :duration="3000" />

        <Transition name="fade">
            <div v-if="loading" class="loader-overlay">
                <div class="spinner"></div>
            </div>
        </Transition>
    </AppLayout>
</template>
