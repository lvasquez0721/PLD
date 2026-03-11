<script setup lang="ts">
import { ref, onMounted, watch, nextTick } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
import { Bell, Search, Calendar, Download } from 'lucide-vue-next';
import axios from 'axios';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes/index.js';
import Input from '@/components/forms/Input.vue';
import Select from '@/components/forms/Select.vue';
import Textarea from '@/components/forms/Textarea.vue';
import SelectFile from '@/components/forms/SelectFile.vue';
import { usePage, router as inertiaRouter } from '@inertiajs/vue3';
import Toast from '@/components/ui/alert/Toast.vue';
import InputError from '@/components/InputError.vue';

// Función simple para generar un color badge a partir del texto Patron, excepto "Nuevo"
function colorFromString(str: string) {
    const colors = [
        // [bg, text, border]
        ['bg-blue-50/70 dark:bg-blue-900/20', 'text-blue-700/90 dark:text-blue-300/90', 'border-blue-200/40 dark:border-blue-600/20'],
        ['bg-green-50/70 dark:bg-green-900/20', 'text-green-700/90 dark:text-green-300/90', 'border-green-200/40 dark:border-green-600/20'],
        ['bg-red-50/70 dark:bg-red-900/20', 'text-red-700/90 dark:text-red-300/90', 'border-red-200/40 dark:border-red-600/20'],
        ['bg-purple-50/80 dark:bg-purple-900/20', 'text-purple-700/90 dark:text-purple-300/90', 'border-purple-200/40 dark:border-purple-600/20'],
        ['bg-orange-50/80 dark:bg-orange-900/20', 'text-orange-700/90 dark:text-orange-300/90', 'border-orange-200/40 dark:border-orange-600/20'],
        ['bg-pink-50/80 dark:bg-pink-900/20', 'text-pink-700/90 dark:text-pink-300/90', 'border-pink-200/40 dark:border-pink-600/20'],
        ['bg-cyan-50/80 dark:bg-cyan-900/20', 'text-cyan-700/90 dark:text-cyan-300/90', 'border-cyan-200/40 dark:border-cyan-600/20'],
        ['bg-yellow-50/80 dark:bg-yellow-900/20', 'text-yellow-700/90 dark:text-yellow-300/90', 'border-yellow-200/40 dark:border-yellow-600/20'],
        ['bg-slate-50/80 dark:bg-neutral-800/70', 'text-slate-700/90 dark:text-neutral-100/90', 'border-slate-200/40 dark:border-neutral-600/20'],
        ['bg-teal-50/80 dark:bg-teal-900/25', 'text-teal-700/90 dark:text-teal-300/90', 'border-teal-200/40 dark:border-teal-600/20'],
        ['bg-lime-50/85 dark:bg-lime-900/20', 'text-lime-700/90 dark:text-lime-300/90', 'border-lime-200/40 dark:border-lime-600/20'],
    ];
    // Hash
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    const idx = Math.abs(hash) % colors.length;
    return `${colors[idx][0]} ${colors[idx][1]} border ${colors[idx][2]}`;
}

// Detect theme for custom date input styling
const isDark = ref(false);
onMounted(() => {
    // Function to check and set theme
    const checkTheme = () => {
        isDark.value = document.documentElement.classList.contains('dark');
    };
    checkTheme();
    const observer = new MutationObserver(checkTheme);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
const page = usePage();
interface EvidenciasFormateadas {
    totalPagado: string | null;
    totalPagos: number | null;
    operacionPagada: boolean | null;
    saldoPendiente: string | null;
    tieneDiferencias: boolean;
    raw: string;
}

interface Alerta {
    IDRegistroAlerta: number;
    Folio: string;
    Patron: string;
    IDCliente: number;
    Cliente: string;
    Poliza: string;
    FechaDeteccion: string;
    HoraDeteccion: string;
    FechaOperacion: string;
    HoraOperacion: string;
    MontoOperacion: number;
    InstrumentoMonetario: string;
    IDMoneda: number;
    RFCAgente: string;
    Agente: string;
    Estatus: string;
    Descripcion: string;
    Razones: string;
    Evidencias: string;
}

type EvidenciaArchivo = {
    path: string;
    original?: string;
    mime?: string;
    size?: number;
};

const fechaInicio = ref('');
const fechaFin = ref('');
const alertas = ref<Alerta[]>([]);
const isLoading = ref(false);
const focusedInput = ref<string | null>(null);

// Modal para agregar/editar alerta
const showNuevoAlertaModal = ref(false);
const selectedAlertaId = ref<number | null>(null);

// Inputs for modal
const patron = ref('Nuevo');
const monto = ref<number | ''>('');
const nombre = ref('');
const noCliente = ref('');
const agente = ref('');
const instrumento = ref('');
const moneda = ref('');
const estatus = ref('');
const descripcionOperacion = ref('');
const razones = ref('');
const evidencias = ref<File[]>([]);
const submitLoading = ref(false);
const toastVisible = ref(false);
const toastMessage = ref('');
const toastType = ref<'success' | 'warning' | 'error'>('success');
const errorGeneral = ref<string | null>(null);
const erroresValidacion = ref<Record<string, string[]>>({});

const opcionesNombre = ref<{ value: string; label: string }[]>([]);
const opcionesPoliza = ref<{ value: string; label: string }[]>([]);
const poliza = ref('');
const opcionesAgente = ref<{ value: string; label: string }[]>([]);
const opcionesInstrumento = ref<{ value: string; label: string }[]>([]);
const opcionesMoneda = ref<{ value: string; label: string }[]>([]);
const opcionesEstatus = ref<{ value: string; label: string }[]>([
    { value: 'Generado', label: 'Generado' },
    { value: 'Analizado', label: 'Analizado' },
    { value: 'Cerrado', label: 'Cerrado' },
    { value: 'Reportado', label: 'Reportado' },
    { value: 'Enviado', label: 'Enviado' }
]);

const openNuevoAlertaModal = async (alerta?: Alerta) => {
    if (alerta) {
        selectedAlertaId.value = alerta.IDRegistroAlerta;
        patron.value = alerta.Patron ?? '';
        const estatusEncontrado = opcionesEstatus.value.find(opt => opt.value === alerta.Estatus);
        estatus.value = estatusEncontrado ? estatusEncontrado.value : '';
        nombre.value = String(alerta.IDCliente ?? '');
        noCliente.value = String(alerta.IDCliente ?? '');
        poliza.value = alerta.Poliza ?? '';
        const agenteEncontrado = opcionesAgente.value.find(opt => opt.label === alerta.Agente);
        agente.value = agenteEncontrado ? agenteEncontrado.value : '';
        const instrumentoEncontrado = opcionesInstrumento.value.find(opt => opt.label === alerta.InstrumentoMonetario);
        instrumento.value = instrumentoEncontrado ? instrumentoEncontrado.value : '';
        moneda.value = String(alerta.IDMoneda ?? '');
        monto.value = alerta.MontoOperacion ?? '';
        descripcionOperacion.value = alerta.Descripcion ?? '';
        razones.value = alerta.Razones ?? '';
        evidencias.value = [];
        await cargarPolizasCliente(String(alerta.IDCliente ?? ''));
    } else {
        selectedAlertaId.value = null;
        patron.value = 'Nuevo';
        estatus.value = '';
        nombre.value = '';
        noCliente.value = '';
        poliza.value = '';
        agente.value = '';
        instrumento.value = '';
        moneda.value = '';
        monto.value = '';
        descripcionOperacion.value = '';
        razones.value = '';
        evidencias.value = [];
        opcionesPoliza.value = [];
    }
    errorGeneral.value = null;
    erroresValidacion.value = {};
    showNuevoAlertaModal.value = true;
    await nextTick();
};

const closeNuevoAlertaModal = () => {
    showNuevoAlertaModal.value = false;
    selectedAlertaId.value = null;
    errorGeneral.value = null;
    erroresValidacion.value = {};
};

onMounted(() => {
    const clientes = (page.props as any).clientes || [];
    opcionesNombre.value = clientes.map((c: any) => {
        const label = (c.RazonSocial && String(c.RazonSocial).trim().length > 0)
            ? c.RazonSocial
            : `${c.Nombre ?? ''} ${c.ApellidoPaterno ?? ''} ${c.ApellidoMaterno ?? ''}`.trim();
        return { value: String(c.IDCliente), label };
    });
    const agentes = (page.props as any).agentes || [];
    opcionesAgente.value = agentes.map((c: any) => {
        const label = (c.RazonSocial && String(c.RazonSocial).trim().length > 0)
            ? c.RazonSocial
            : `${c.Nombre ?? ''} ${c.ApellidoPaterno ?? ''} ${c.ApellidoMaterno ?? ''}`.trim();
        return { value: String(c.IDCliente), label };
    });
    const instrumentos = (page.props as any).instrumentos || [];
    opcionesInstrumento.value = instrumentos.map((i: any) => ({
        value: String(i.IDFormaPago),
        label: String(i.FormaPago),
    }));
    const monedas = (page.props as any).monedas || [];
    opcionesMoneda.value = monedas.map((m: any) => ({
        value: String(m.IDMoneda),
        label: String(m.Moneda),
    }));
});
watch(nombre, (nuevo) => {
    noCliente.value = nuevo ? String(nuevo) : '';
    poliza.value = '';
    opcionesPoliza.value = [];
    if (nuevo) {
        cargarPolizasCliente(String(nuevo));
    }
});

const cargarPolizasCliente = async (idCliente: string) => {
    try {
        const resp = await axios.get(`/clientes/${idCliente}/polizas`);
        const arr = resp.data?.polizas ?? [];
        opcionesPoliza.value = arr.map((p: any) => ({
            value: String(p.value ?? p),
            label: String(p.label ?? p),
        }));
    } catch {
        opcionesPoliza.value = [];
    }
};

const buscarAlertas = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/alertas/date-range', {
            params: {
                fechaInicio: fechaInicio.value,
                fechaFin: fechaFin.value,
            },
        });
        alertas.value = response.data;
    } catch (error) {
        console.error('Error al obtener alertas:', error);
    } finally {
        isLoading.value = false;
    }
};

const downloadCsv = async () => {
    try {
        const response = await axios.get('/alertas/download-csv', {
            params: {
                fechaInicio: fechaInicio.value,
                fechaFin: fechaFin.value,
            },
            responseType: 'blob',
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `alertas_${fechaInicio.value}_${fechaFin.value}.csv`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error al descargar el CSV:', error);
    }
};

const actualizarAlerta = async () => {
    try {
        submitLoading.value = true;
        errorGeneral.value = null;
        erroresValidacion.value = {};
        const formData = new FormData();
        const nombreLabel = opcionesNombre.value.find(o => o.value === nombre.value)?.label || '';
        const instrumentoLabel = opcionesInstrumento.value.find(o => o.value === instrumento.value)?.label || '';
        const montoNum = typeof monto.value === 'number' ? monto.value : parseFloat(String(monto.value).replace(/[^0-9.-]/g, ''));
        formData.append('patron', String(patron.value));
        formData.append('estatus', String(estatus.value));
        formData.append('nombre', nombreLabel);
        formData.append('noCliente', String(noCliente.value));
        formData.append('agente', String(agente.value));
        formData.append('instrumento', instrumentoLabel);
        formData.append('poliza', String(poliza.value));
        formData.append('monto', isNaN(montoNum) ? '0' : String(montoNum));
        formData.append('descripcionOperacion', String(descripcionOperacion.value));
        formData.append('razones', String(razones.value));
        if (selectedAlertaId.value !== null) {
            formData.append('idAlerta', String(selectedAlertaId.value));
            formData.append('_method', 'put');
        }
        formData.append('idMoneda', String(moneda.value ?? ''));
        formData.append('IDTipoOperacion', '');
        evidencias.value.forEach((file) => {
            formData.append('evidencias[]', file);
        });
        // Actualizamos USANDO LA RUTA QUE SI EXISTE en web.php: '/alertas/actualizar'
        // The below is the fix for "Property 'data' does not exist on type 'void'"
        // We run the post, the message comes only from the onSuccess callback (handled via toast)
        await inertiaRouter.post('/alertas/actualizar', formData, {
            forceFormData: true,
            onSuccess: (page) => {
                // Devolver la data del backend para mantener compatibilidad
                toastType.value = 'success';
                toastMessage.value = page.props?.message ||
                    'La alerta se actualizó correctamente';
                toastVisible.value = true;
                showNuevoAlertaModal.value = false;
                // Limpiar campos
                selectedAlertaId.value = null;
                estatus.value = '';
                nombre.value = '';
                noCliente.value = '';
                agente.value = '';
                instrumento.value = '';
                moneda.value = '';
                poliza.value = '';
                monto.value = '';
                descripcionOperacion.value = '';
                razones.value = '';
                evidencias.value = [];
                buscarAlertas();
            },
            onError: (errors) => {
                throw { response: { status: 422, data: { errors, message: 'Validación fallida. Corrige los campos.' } } };
            }
        });

        // La limpieza y el toast ahora está en onSuccess, no aquí
    } catch (error) {
        const anyErr: any = error;
        const status = anyErr?.response?.status;
        const resp = anyErr?.response?.data;
        if (status === 422) {
            erroresValidacion.value = resp?.errors || {};
            errorGeneral.value = resp?.message || 'Validación fallida. Corrige los campos.';
            toastType.value = 'error';
            toastMessage.value = 'Revisa los campos requeridos.';
            toastVisible.value = true;
        } else if (status === 404) {
            errorGeneral.value = resp?.message || 'Recurso no encontrado.';
            toastType.value = 'error';
            toastMessage.value = errorGeneral.value || '';
            toastVisible.value = true;
        } else if (status === 500) {
            const id = resp?.error_id ? ` (ID: ${resp.error_id})` : '';
            const dbg = resp?.debug;
            if (dbg?.message) {
                errorGeneral.value = `${dbg.message}${dbg.file && dbg.line ? ` — ${dbg.file}:${dbg.line}` : ''}${id}`;
                toastType.value = 'error';
                toastMessage.value = `Error: ${dbg.message}${id}`;
            } else {
                errorGeneral.value = 'Ocurrió un error al actualizar la alerta' + id;
                toastType.value = 'error';
                toastMessage.value = errorGeneral.value || '';
            }
            toastVisible.value = true;
        } else {
            errorGeneral.value = 'No se pudo actualizar la alerta. Intenta de nuevo.';
            toastType.value = 'error';
            toastMessage.value = errorGeneral.value || '';
            toastVisible.value = true;
        }
    } finally {
        submitLoading.value = false;
    }
};

const formatEvidencias = (evidencias: string): EvidenciasFormateadas => {
    if (!evidencias) {
        return {
            totalPagado: null,
            totalPagos: null,
            operacionPagada: null,
            saldoPendiente: null,
            tieneDiferencias: false,
            raw: evidencias,
        };
    }

    try {
        const data = typeof evidencias === 'string' ? JSON.parse(evidencias) : evidencias;

        const totalPagado = data.total_pagado || 0;
        const totalPagos = data.total_pagos || 0;
        const operacionPagada = data.operacion_pagada || false;
        const saldoPendiente = data.saldo_pendiente || 0;
        const tieneDiferencias = data.analisis_fraccionado?.some((item: any) => !item.dentro_tolerance) || false;

        const formatoMoneda = (monto: number) => {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN',
                minimumFractionDigits: 2,
            }).format(monto);
        };

        return {
            totalPagado: formatoMoneda(totalPagado),
            totalPagos,
            operacionPagada,
            saldoPendiente: formatoMoneda(saldoPendiente),
            tieneDiferencias,
            raw: evidencias,
        };
    } catch {
        return {
            totalPagado: null,
            totalPagos: null,
            operacionPagada: null,
            saldoPendiente: null,
            tieneDiferencias: false,
            raw: evidencias,
        };
    }
};

const tryParseEvidencias = (e: string): any | null => {
    if (!e) return null;
    try {
        return typeof e === 'string' ? JSON.parse(e) : e;
    } catch {
        return null;
    }
};

const isArchivoEvidencias = (parsed: any): parsed is EvidenciaArchivo[] => {
    return Array.isArray(parsed) && parsed.length > 0 && typeof parsed[0]?.path === 'string';
};

const obtenerArchivosEvidencia = (e: string): EvidenciaArchivo[] => {
    const parsed = tryParseEvidencias(e);
    if (isArchivoEvidencias(parsed)) return parsed as EvidenciaArchivo[];
    return [];
};

const urlArchivoPublico = (p: string): string => {
    const normalized = p.replace(/^\/+/, '');
    return `/storage/${normalized}`;
};

// Badge class segun patron (except "Nuevo" que es especial)
const getPatronBadgeClass = (patron: string) => {
    if (patron.trim().toLowerCase() === 'nuevo') return '';
    return colorFromString(patron);
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Módulo de Alertas',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
            <div class="relative">
        <!-- Toast global -->
        <Toast v-model="toastVisible" :message="toastMessage" :type="toastType" :duration="4000" />

        <!-- Contenedor principal similar a PerfilTransaccional -->
        <div
            class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-xl shadow-gray-200/50 dark:border-neutral-800/80 dark:bg-neutral-950 dark:shadow-neutral-900/50">
            <!-- Encabezado -->
            <div
                class="border-b border-gray-200/60 bg-gradient-to-r from-gray-50/95 to-gray-50/80 px-6 py-4.5 backdrop-blur-sm dark:border-neutral-800/60 dark:from-neutral-900/95 dark:to-neutral-900/80">
                <h2 class="text-base font-semibold tracking-tight text-gray-900 dark:text-neutral-100">
                    Consulta de alertas
                </h2>
            </div>

            <!-- Contenido -->
            <div class="p-6">
                <!-- Filtros (rango de fechas + acciones) -->
                <div
                    class="mb-6 flex flex-col gap-4 border-b border-gray-200/60 pb-6 dark:border-neutral-800/60 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-1 flex-col gap-4 sm:flex-row">
                        <div class="flex-1">
                            <label for="fechaInicio"
                                class="mb-2 block text-xs font-semibold uppercase tracking-[0.08em] text-gray-600 dark:text-neutral-300">
                                Fecha inicio
                            </label>
                            <div class="relative">
                                <input type="date" id="fechaInicio" v-model="fechaInicio"
                                    @focus="focusedInput = 'inicio'" @blur="focusedInput = null" :class="[
                                        'w-full rounded-xl border px-4 py-3 text-sm font-normal tracking-[0.01em] transition-all duration-200',
                                        isDark
                                            ? (focusedInput === 'inicio'
                                                ? 'custom-dark-date-focused'
                                                : 'custom-dark-date')
                                            : (focusedInput === 'inicio'
                                                ? 'custom-light-date-focused'
                                                : 'custom-light-date')
                                    ]" />
                            </div>
                        </div>

                        <div class="flex-1">
                            <label for="fechaFin"
                                class="mb-2 block text-xs font-semibold uppercase tracking-[0.08em] text-gray-600 dark:text-neutral-300">
                                Fecha fin
                            </label>
                            <div class="relative">
                                <input type="date" id="fechaFin" v-model="fechaFin" @focus="focusedInput = 'fin'"
                                    @blur="focusedInput = null" :class="[
                                        'w-full rounded-xl border px-4 py-3 text-sm font-normal tracking-[0.01em] transition-all duration-200',
                                        isDark
                                            ? (focusedInput === 'fin'
                                                ? 'custom-dark-date-focused'
                                                : 'custom-dark-date')
                                            : (focusedInput === 'fin'
                                                ? 'custom-light-date-focused'
                                                : 'custom-light-date')
                                    ]" />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button @click="buscarAlertas" :disabled="isLoading"
                            class="group inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-all duration-200 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl hover:shadow-blue-500/30 hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 dark:from-blue-500 dark:to-blue-600 dark:shadow-blue-500/20 dark:hover:from-blue-600 dark:hover:to-blue-700 dark:focus:ring-blue-400 dark:focus:ring-offset-neutral-950">
                            <template v-if="isLoading">
                                <span class="relative flex h-4 w-4 items-center justify-center">
                                    <span
                                        class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                                </span>
                                <span>Buscando...</span>
                            </template>
                            <template v-else>
                                <Search class="h-4 w-4 transition-transform group-hover:scale-110" />
                                <span>Buscar</span>
                            </template>
                        </button>

                        <button v-if="alertas.length > 0" @click="downloadCsv"
                            class="group inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300/80 bg-white px-6 py-3 text-sm font-semibold text-gray-700 shadow-md shadow-gray-200/50 transition-all duration-200 hover:border-gray-400/80 hover:bg-gradient-to-br hover:from-gray-50 hover:to-white hover:shadow-lg hover:shadow-gray-300/30 hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-neutral-700/80 dark:bg-neutral-950 dark:text-neutral-200 dark:shadow-neutral-900/50 dark:hover:border-neutral-600 dark:hover:from-neutral-900 dark:hover:to-neutral-950 dark:hover:shadow-neutral-800/30 dark:focus:ring-blue-400 dark:focus:ring-offset-neutral-950">
                            <Download class="h-4 w-4" />
                            <span>Descargar CSV</span>
                        </button>
                    </div>
                </div>

                <!-- Tabla de resultados -->
                <div v-if="isLoading || alertas.length > 0" class="overflow-hidden">
                    <div class="overflow-x-auto rounded-xl">
                        <table class="min-w-full divide-y divide-gray-200/60 dark:divide-neutral-800/60">
                            <thead
                                class="bg-gradient-to-r from-gray-50/95 to-gray-50/80 backdrop-blur-sm dark:from-neutral-900/95 dark:to-neutral-900/80">
                                <tr>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        ID Registro
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Folio
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Patrón
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Estatus
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        ID Cliente
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Cliente
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Póliza
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Fecha Detección
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Hora Detección
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Fecha Operación
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Hora Operación
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Monto
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Instrumento
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        RFC Agente
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Agente
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Descripción
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300">
                                        Razones
                                    </th>
                                    <th scope="col"
                                        class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-neutral-300 min-w-[220px]">
                                        Evidencias
                                    </th>
                                </tr>
                            </thead>

                            <Transition name="fade" mode="out-in">
                                <tbody v-if="isLoading && alertas.length === 0"
                                    class="divide-y divide-gray-200/60 bg-white dark:divide-neutral-800/60 dark:bg-neutral-950">
                                    <tr>
                                        <td colspan="18" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center gap-4">
                                                <div
                                                    class="h-10 w-10 rounded-full border-2 border-blue-200/60 border-t-blue-500/90 dark:border-neutral-800 dark:border-t-blue-400/80 animate-spin">
                                                </div>
                                                <p
                                                    class="text-sm font-medium text-gray-500 dark:text-neutral-400 tracking-[0.01em]">
                                                    Cargando alertas...
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>

                                <tbody v-else-if="alertas.length > 0"
                                    class="divide-y divide-gray-200/60 bg-white dark:divide-neutral-800/60 dark:bg-neutral-950">
                                    <tr v-for="alerta in alertas" :key="alerta.IDRegistroAlerta"
                                        class="transition-all duration-150 hover:bg-gradient-to-r hover:from-gray-50/80 hover:to-gray-50/50 dark:hover:from-neutral-900/50 dark:hover:to-neutral-900/30">
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-900 dark:text-neutral-100">
                                            {{ alerta.IDRegistroAlerta }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-neutral-300">
                                            {{ alerta.Folio }}
                                        </td>
                                        <!-- BOTON PARA EDITAR ALERTA, SIEMPRE -->
                                        <td class="whitespace-nowrap px-5 py-4 text-sm">
                                            <button
                                                class="uppercase px-4 py-2 rounded-full text-xs font-extrabold tracking-[0.14em] transition-all duration-200 focus:outline-none border"
                                                :class="alerta.Patron.trim().toLowerCase() === 'nuevo'
                                                    ? 'bg-gradient-to-br from-green-500 via-emerald-500 to-green-400 text-white border-none shadow-[0_1px_4px_rgba(34,197,94,0.22)] outline-none ring-2 ring-green-200/40 dark:ring-green-700/30 hover:bg-green-700/95 hover:from-green-600 hover:to-green-400 active:scale-95 focus:ring-4 focus:ring-emerald-300/60'
                                                    : getPatronBadgeClass(alerta.Patron) + ' bg-white dark:bg-neutral-950 hover:bg-gray-100 dark:hover:bg-neutral-900'
                                                "
                                                @click="openNuevoAlertaModal(alerta)"
                                            >
                                                {{ alerta.Patron.toUpperCase() }}
                                            </button>
                                        </td>
                                        <!-- Estatus mostrado junto a Patron -->
                                        <td class="whitespace-nowrap px-5 py-4">
                                            <span
                                                class="inline-flex items-center rounded-full bg-amber-50/80 px-3 py-1.5 text-xs font-semibold text-amber-800 shadow-sm dark:bg-amber-900/25 dark:text-amber-100 dark:shadow-none">
                                                {{ alerta.Estatus }}
                                            </span>
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-neutral-300">
                                            {{ alerta.IDCliente }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-900 dark:text-neutral-100">
                                            {{ alerta.Cliente }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-neutral-300">
                                            {{ alerta.Poliza }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-neutral-300">
                                            {{ alerta.FechaDeteccion }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm text-gray-500 dark:text-neutral-400">
                                            {{ alerta.HoraDeteccion }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-neutral-300">
                                            {{ alerta.FechaOperacion }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm text-gray-500 dark:text-neutral-400">
                                            {{ alerta.HoraOperacion }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-900 dark:text-neutral-100">
                                            {{ alerta.MontoOperacion }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-neutral-300">
                                            {{ alerta.InstrumentoMonetario }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm font-mono text-gray-500 dark:text-neutral-400">
                                            {{ alerta.RFCAgente }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-neutral-300">
                                            {{ alerta.Agente }}
                                        </td>
                                        <td
                                            class="max-w-xs truncate whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-neutral-300">
                                            {{ alerta.Descripcion }}
                                        </td>
                                        <td
                                            class="max-w-xs truncate whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-neutral-300">
                                            {{ alerta.Razones }}
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-700 dark:text-neutral-300">
                                            <template v-if="obtenerArchivosEvidencia(alerta.Evidencias).length">
                                                <div class="flex flex-col gap-1.5 min-w-[220px]">
                                                    <a v-for="(file, idx) in obtenerArchivosEvidencia(alerta.Evidencias)"
                                                        :key="idx"
                                                        class="text-sm font-medium text-blue-700 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 underline decoration-blue-300/60 underline-offset-2"
                                                        :href="urlArchivoPublico(file.path)" target="_blank"
                                                        rel="noopener noreferrer">
                                                        {{ file.original || (file.path.split('/').pop() ?? file.path) }}
                                                    </a>
                                                </div>
                                            </template>
                                            <template v-else-if="formatEvidencias(alerta.Evidencias).totalPagado !== null">
                                                <div class="flex flex-col gap-1.5 min-w-[200px]">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="text-xs font-medium text-gray-600 dark:text-neutral-300">Total:</span>
                                                        <span
                                                            class="text-sm font-semibold text-gray-900 dark:text-neutral-100">
                                                            {{ formatEvidencias(alerta.Evidencias).totalPagado }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-2 text-xs">
                                                        <span
                                                            class="text-gray-500 dark:text-neutral-400">Pagos:</span>
                                                        <span
                                                            class="font-medium text-gray-700 dark:text-neutral-300">
                                                            {{ formatEvidencias(alerta.Evidencias).totalPagos }}
                                                        </span>
                                                        <span
                                                            class="text-gray-400 dark:text-neutral-500">•</span>
                                                        <span :class="[
                                                            'px-2 py-0.5 rounded-full font-medium',
                                                            formatEvidencias(alerta.Evidencias).operacionPagada
                                                                ? 'bg-green-50/80 text-green-700 dark:bg-green-900/25 dark:text-green-300'
                                                                : 'bg-amber-50/80 text-amber-700 dark:bg-amber-900/25 dark:text-amber-300'
                                                        ]">
                                                            {{ formatEvidencias(alerta.Evidencias).operacionPagada ? 'Pagada' :
                                                                'Pendiente' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div
                                                    class="max-w-xs truncate text-xs italic text-gray-500 dark:text-neutral-400">
                                                    {{ alerta.Evidencias || 'Sin evidencias' }}
                                                </div>
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </Transition>
                        </table>
                    </div>
                </div>

                <!-- Estado vacío -->
                <Transition name="fade" mode="out-in">
                    <div v-if="!isLoading && alertas.length === 0" class="py-16 text-center">
                        <div class="mx-auto max-w-sm">
                            <div
                                class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-neutral-800">
                                <Bell class="h-6 w-6 text-gray-400 dark:text-neutral-500" />
                            </div>
                            <p class="text-sm font-medium text-gray-500 dark:text-neutral-400">
                                No hay alertas para mostrar.
                            </p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-neutral-500">
                                Seleccione un rango de fechas y presione buscar para ver resultados.
                            </p>
                        </div>
                    </div>
                </Transition>

                <!-- Resumen de resultados -->
                <div v-if="alertas.length > 0"
                    class="mt-6 flex flex-col items-center justify-between gap-2 text-xs text-gray-600 dark:text-neutral-400 sm:flex-row">
                    <p>
                        Mostrando
                        <span class="font-semibold text-gray-900 dark:text-neutral-100">{{ alertas.length }}</span>
                        {{ alertas.length === 1 ? 'alerta' : 'alertas' }}
                    </p>
                    <p class="text-[11px] text-gray-400 dark:text-neutral-500">
                        Última actualización: {{ new Date().toLocaleTimeString('es-MX') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Modal para el botón Nuevo -->
        <Transition name="fade">
            <div v-if="showNuevoAlertaModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
                <div
                    class="relative flex w-full max-w-3xl min-w-[600px] flex-col overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-2xl shadow-gray-900/20 dark:border-neutral-800/80 dark:bg-neutral-950 dark:shadow-neutral-950/40"
                    style="max-height: 90vh;">
                    <button @click="closeNuevoAlertaModal"
                        class="absolute right-4 top-4 text-xl text-neutral-400 transition-colors duration-150 hover:text-neutral-700 dark:hover:text-white">
                        &times;
                    </button>
                    <div class="overflow-y-auto px-8 py-8" style="max-height: 90vh;">
                        <h2 class="mb-6 text-xl font-semibold text-slate-700 dark:text-neutral-100">
                            Información de la alerta
                        </h2>

                        <div v-if="errorGeneral || Object.keys(erroresValidacion).length"
                            class="mb-4 rounded-xl border border-red-200/70 bg-red-50/60 p-3 text-red-800 dark:border-red-800/50 dark:bg-red-900/20 dark:text-red-200">
                            <p v-if="errorGeneral" class="mb-1 text-xs">
                                {{ errorGeneral }}
                            </p>
                            <ul class="ml-5 list-disc text-xs" v-if="Object.keys(erroresValidacion).length">
                                <li v-for="(msgs, field) in erroresValidacion" :key="field">
                                    {{ Array.isArray(msgs) ? msgs[0] : String(msgs) }}
                                </li>
                            </ul>
                        </div>

                        <div class="grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
                            <div>
                                <Input label="Patrón" type="text" id="patron" v-model="patron" disabled required />
                            </div>
                            <div>
                                <Select label="Estatus" :options="opcionesEstatus" v-model="estatus"
                                    placeholder="Seleccionar estatus" id="estatus" name="estatus" required />
                                <InputError :message="erroresValidacion.estatus?.[0]" />
                            </div>
                            <div>
                                <Select label="Nombre" :options="opcionesNombre" v-model="nombre"
                                    placeholder="Seleccionar nombre" id="nombre" name="nombre" required />
                                <InputError :message="erroresValidacion.nombre?.[0]" />
                            </div>
                            <div>
                                <Input label="No. cliente" type="text" id="noCliente" v-model="noCliente"
                                    placeholder="Ingresar No. de cliente" disabled required />
                                <InputError :message="erroresValidacion.noCliente?.[0]" />
                            </div>
                            <div>
                                <Select label="Folio póliza" :options="opcionesPoliza" v-model="poliza"
                                    placeholder="Seleccionar folio" id="poliza" name="poliza" required />
                                <InputError :message="erroresValidacion.poliza?.[0]" />
                            </div>
                            <div>
                                <Select label="Agente" :options="opcionesAgente" v-model="agente"
                                    placeholder="Seleccionar agente" id="agente" name="agente" required />
                                <InputError :message="erroresValidacion.agente?.[0]" />
                            </div>
                            <div>
                                <Input label="Monto" type="float" id="monto" v-model="monto" prefix="$"
                                    placeholder="Ingresar monto" required />
                                <InputError :message="erroresValidacion.monto?.[0]" />
                            </div>
                            <div>
                                <Select label="Moneda" :options="opcionesMoneda" v-model="moneda"
                                    placeholder="Seleccionar moneda" id="moneda" name="moneda" />
                                <InputError :message="erroresValidacion.IDMoneda?.[0]" />
                            </div>
                            <div>
                                <Select label="Instrumento" :options="opcionesInstrumento" v-model="instrumento"
                                    placeholder="Seleccionar instrumento" id="instrumento" name="instrumento" required />
                                <InputError :message="erroresValidacion.instrumento?.[0]" />
                            </div>
                            <div class="md:col-span-2">
                                <Textarea label="Descripción de la operación" id="descripcionOperacion"
                                    name="descripcionOperacion" placeholder="Agrega una descripción detallada"
                                    v-model="descripcionOperacion" :rows="4" required />
                                <InputError :message="erroresValidacion.descripcionOperacion?.[0]" />
                            </div>
                            <div class="md:col-span-2">
                                <Textarea label="Razones" id="razones" name="razones"
                                    placeholder="Agrega las razones" v-model="razones" :rows="4" required />
                                <InputError :message="erroresValidacion.razones?.[0]" />
                            </div>
                            <div class="md:col-span-2">
                                <SelectFile label="Evidencias" id="evidencias" name="evidencias"
                                    accept=".pdf,.jpg,.png,.doc,.docx" v-model="evidencias" required />
                                <InputError
                                    :message="(erroresValidacion['evidencias']?.[0] || erroresValidacion['evidencias.*']?.[0])" />
                            </div>
                            <div class="md:col-span-2 mt-6 flex justify-end gap-3">
                                <button @click="closeNuevoAlertaModal" type="button"
                                    class="group inline-flex items-center justify-center rounded-xl border border-gray-300/80 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-all duration-200 hover:border-gray-400/80 hover:bg-gradient-to-br hover:from-gray-50 hover:to-white hover:shadow-md hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 dark:border-neutral-700/80 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:border-neutral-600 dark:hover:from-neutral-900 dark:hover:to-neutral-950 dark:focus:ring-blue-400/50 dark:focus:ring-offset-neutral-950">
                                    Cancelar
                                </button>
                                <button @click="actualizarAlerta" :disabled="submitLoading"
                                    class="group inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-all duration-200 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl hover:shadow-blue-500/30 hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 dark:from-blue-500 dark:to-blue-600 dark:shadow-blue-500/20 dark:hover:from-blue-600 dark:hover:to-blue-700 dark:focus:ring-blue-400 dark:focus:ring-offset-neutral-950">
                                    <div class="flex items-center gap-2.5">
                                        <template v-if="submitLoading">
                                            <span class="relative flex h-4 w-4 items-center justify-center">
                                                <span
                                                    class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                                            </span>
                                            <span>Actualizando...</span>
                                        </template>
                                        <template v-else>
                                            <span>Actualizar alerta</span>
                                        </template>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
            </div>
        </FadeIn>
    </AppLayout>
</template>

<style scoped>
* {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

input[type="date"]:focus-visible {
    outline: 2px solid rgb(59 130 246 / 0.3);
    outline-offset: 2px;
}

.custom-light-date {
    background-color: rgba(255, 255, 255, 0.95) !important;
    color: #1e293b !important;
    border: 1px solid rgba(226, 232, 240, 0.7);
}

.custom-light-date:hover {
    border-color: rgba(226, 232, 240, 0.85);
    background-color: #fff;
}

.custom-light-date-focused {
    background-color: #fff !important;
    color: #0f172a !important;
    border: 1px solid rgba(147, 197, 253, 0.5);
    box-shadow: 0 3px 12px rgba(59, 130, 246, 0.07);
    transform: scale(1.005);
}

.custom-dark-date {
    background-color: rgba(31, 31, 31, 0.60) !important;
    color: #fafbfc !important;
    border: 1px solid rgba(38, 38, 38, 0.65);
}

.custom-dark-date:hover {
    border-color: rgba(163, 163, 163, 0.32);
    background-color: rgba(31, 31, 31, 0.85);
}

.custom-dark-date-focused {
    background-color: rgba(23, 23, 23, 0.95) !important;
    color: #fafbfc !important;
    border: 1px solid rgba(147, 197, 253, 0.5);
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.15);
    transform: scale(1.005);
}

input[type="date"]::-webkit-inner-spin-button,
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0);
}

.custom-dark-date::-webkit-calendar-picker-indicator,
.custom-dark-date-focused::-webkit-calendar-picker-indicator {
    filter: invert(1);
}

.custom-dark-date::-moz-calendar-picker-indicator,
.custom-dark-date-focused::-moz-calendar-picker-indicator {
    filter: invert(1);
}

tbody tr {
    will-change: background-color;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.7s cubic-bezier(0.25, 0.1, 0.25, 1);
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
