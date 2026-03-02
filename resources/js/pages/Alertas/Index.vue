<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Bell, Search, Calendar, Download } from 'lucide-vue-next';
import axios from 'axios';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes/index.js';
import Input from '@/components/forms/Input.vue';
import Select from '@/components/forms/Select.vue';
import Textarea from '@/components/forms/Textarea.vue';
import SelectFile from '@/components/forms/SelectFile.vue';
import FormLabelIcon from '@/components/forms/FormLabelIcon.vue';
import DateInput from '@/components/forms/DateInput.vue';
import PrimaryButton from '@/components/forms/PrimaryButton.vue';
import { usePage } from '@inertiajs/vue3';
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

// Modal "Nuevo" para agregar alerta
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

const openNuevoAlertaModal = (id?: number) => {
    showNuevoAlertaModal.value = true;
    selectedAlertaId.value = typeof id === 'number' ? id : null;
};
const closeNuevoAlertaModal = () => {
    showNuevoAlertaModal.value = false;
    selectedAlertaId.value = null;
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

const emitirReporte = async () => {
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
        }
        formData.append('IDMoneda', String(moneda.value ?? ''));
        formData.append('IDTipoOperacion', '');
        evidencias.value.forEach((file) => {
            formData.append('evidencias[]', file);
        });
        const { data } = await axios.post('/alertas/emitir-reporte', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        showNuevoAlertaModal.value = false;
        toastType.value = 'success';
        toastMessage.value = data?.message || 'Reporte emitido correctamente';
        toastVisible.value = true;
        await buscarAlertas();
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
                errorGeneral.value = 'Ocurrió un error al emitir el reporte' + id;
                toastType.value = 'error';
                toastMessage.value = errorGeneral.value || '';
            }
            toastVisible.value = true;
        } else {
            errorGeneral.value = 'No se pudo emitir el reporte. Intenta de nuevo.';
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
        <div
            class="min-h-screen bg-gradient-to-br from-slate-50/40 via-slate-50/60 to-blue-50/25 dark:bg-gradient-to-br dark:from-black/90 dark:via-neutral-900/95 dark:to-neutral-900/90">
            <Toast v-model="toastVisible" :message="toastMessage" :type="toastType" :duration="4000" />
            <div class="max-w-[1800px] mx-auto px-8">
                <div
                    class="bg-[#f8fafc]/70 dark:bg-neutral-900/90 backdrop-blur-xl rounded-[20px] shadow-[0_3px_20px_rgba(15,23,42,0.035)] dark:shadow-[0_3px_20px_rgba(0,0,0,0.24)] border border-slate-100/50 dark:border-neutral-800/70 p-8 mb-10 transition-all duration-700 hover:shadow-[0_7px_28px_rgba(15,23,42,0.05)] hover:bg-[#f8fafc]/80 dark:hover:bg-black">
                    <div class="flex items-end gap-6">
                        <div class="flex-1 relative group">
                            <FormLabelIcon :for-id="'fechaInicio'" :icon="Calendar" label="Fecha inicio" />
                            <DateInput id="fechaInicio" v-model="fechaInicio" />
                        </div>

                        <div class="flex-1 relative group">
                            <FormLabelIcon :for-id="'fechaFin'" :icon="Calendar" label="Fecha fin" />
                            <DateInput id="fechaFin" v-model="fechaFin" />
                        </div>

                        <PrimaryButton
                          :icon="Search"
                          :loading="isLoading"
                          loading-label="Buscando..."
                          label="Buscar"
                          @click="buscarAlertas"
                        />

                        <button v-if="alertas.length > 0" @click="downloadCsv"
                            class="px-6 py-3.5 bg-[#f8fafc]/90 dark:bg-neutral-900/90 border border-slate-100/60 dark:border-neutral-800/70 text-slate-700/85 dark:text-neutral-200/90 text-[14px] font-medium tracking-[0.008em] rounded-[14px]
                shadow-[0_2px_7px_rgba(15,23,42,0.025)] dark:shadow-[0_2px_7px_rgba(0,0,0,0.20)] hover:shadow-[0_3px_12px_rgba(15,23,42,0.05)] hover:border-slate-200/60 dark:hover:border-neutral-600/60 hover:bg-[#f8fafc] dark:hover:bg-black
                transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1) transform hover:scale-[1.015] hover:-translate-y-[0.5px]
                focus:outline-none focus:ring-2 focus:ring-slate-200/35 dark:focus:ring-neutral-700/50 focus:ring-offset-2 focus:ring-offset-[#f8fafc]/50 dark:focus:ring-offset-black/30">
                            <Download :size="15" :stroke-width="2" transition-all duration-500 />
                        </button>
                    </div>
                </div>

                <div v-if="isLoading || alertas.length > 0"
                    class="bg-[#f8fafc]/70 dark:bg-neutral-900/90 backdrop-blur-xl rounded-[20px] shadow-[0_3px_20px_rgba(15,23,42,0.035)] dark:shadow-[0_3px_20px_rgba(0,0,0,0.24)] border border-slate-100/50 dark:border-neutral-800/70 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr
                                    class="bg-gradient-to-r from-slate-50/50 via-slate-50/40 to-slate-50/50 dark:from-neutral-800/80 dark:via-black dark:to-neutral-900/95 border-b border-slate-100/50 dark:border-neutral-800/70">
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap transition-colors duration-500">
                                        ID Registro</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Folio</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Patrón</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        ID Cliente</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Cliente</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Póliza</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Fecha Detección</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Hora Detección</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Fecha Operación</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Hora Operación</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Monto</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Instrumento</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        RFC Agente</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Agente</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Estatus</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Descripción</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap">
                                        Razones</th>
                                    <th
                                        class="px-6 py-4 text-left text-[11px] font-semibold text-slate-600/85 dark:text-neutral-200/95 uppercase tracking-[0.07em] whitespace-nowrap min-w-[220px]">
                                        Evidencias</th>
                                </tr>
                            </thead>
                            <Transition name="fade" mode="out-in">
                                <tbody v-if="isLoading && alertas.length === 0"
                                    class="divide-y divide-slate-100/60 dark:divide-neutral-800/70">
                                    <tr>
                                        <td colspan="18" class="px-6 py-20 text-center">
                                            <div class="flex flex-col items-center gap-5">
                                                <div
                                                    class="w-11 h-11 border-[2.5px] border-blue-200/40 dark:border-neutral-800 border-t-blue-400/80 dark:border-t-neutral-300 rounded-full animate-spin">
                                                </div>
                                                <p
                                                    class="text-slate-500/75 dark:text-neutral-400/85 text-[14px] font-light tracking-[0.008em] transition-colors duration-500">
                                                    Cargando alertas...</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody v-else-if="alertas.length > 0"
                                    class="divide-y divide-slate-100/60 dark:divide-neutral-800/70">
                                    <tr v-for="alerta in alertas" :key="alerta.IDRegistroAlerta"
                                        class="hover:bg-blue-50/15 dark:hover:bg-neutral-800/70 transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1) group transform hover:scale-[1.002]">
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-700/85 dark:text-neutral-100/90 font-medium tracking-[0.003em] transition-colors duration-500">
                                            {{ alerta.IDRegistroAlerta }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                                            {{ alerta.Folio }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] font-light tracking-[0.003em]">
                                            <template v-if="alerta.Patron.trim().toLowerCase() === 'nuevo'">
                                                <button class="uppercase px-4 py-2
                                                        bg-gradient-to-br from-green-500 via-emerald-500 to-green-400
                                                        text-white font-extrabold tracking-[0.1em] shadow-[0_2px_7px_rgba(34,197,94,0.11)] border-none
                                                        rounded-full outline-none ring-2 ring-green-200/40 dark:ring-green-700/30
                                                        hover:bg-green-700/95 hover:from-green-600 hover:to-green-400
                                                        active:scale-95 focus:ring-4 focus:ring-emerald-300/60
                                                        transition-all duration-300 cursor-pointer"
                                                    @click="openNuevoAlertaModal(alerta.IDRegistroAlerta)">
                                                    {{ alerta.Patron.toUpperCase() }}
                                                </button>
                                            </template>
                                            <template v-else>
                                                <span
                                                    class="px-3 py-1.5 text-[11.5px] font-medium tracking-[0.02em] rounded-full border"
                                                    :class="getPatronBadgeClass(alerta.Patron)">
                                                    {{ alerta.Patron }}
                                                </span>
                                            </template>
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                                            {{ alerta.IDCliente }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-700/85 dark:text-neutral-100/90 font-medium tracking-[0.003em]">
                                            {{ alerta.Cliente }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                                            {{ alerta.Poliza }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                                            {{ alerta.FechaDeteccion }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-500/80 dark:text-neutral-400/85 font-light tracking-[0.003em]">
                                            {{ alerta.HoraDeteccion }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                                            {{ alerta.FechaOperacion }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-500/80 dark:text-neutral-400/85 font-light tracking-[0.003em]">
                                            {{ alerta.HoraOperacion }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-700/85 dark:text-neutral-200/90 font-semibold tracking-[0.003em]">
                                            {{ alerta.MontoOperacion }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 font-light tracking-[0.003em]">
                                            {{ alerta.InstrumentoMonetario }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-500/80 dark:text-neutral-400/85 font-mono font-light tracking-[0.003em]">
                                            {{ alerta.RFCAgente }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-700/85 dark:text-neutral-100/90 font-light tracking-[0.003em]">
                                            {{ alerta.Agente }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="px-3 py-1.5 text-[11.5px] font-medium tracking-[0.02em] rounded-full bg-amber-50/70 dark:bg-neutral-800/70 text-amber-700/90 dark:text-amber-100/90 border border-amber-200/40 dark:border-neutral-600/20 shadow-[0_1px_3px_rgba(217,119,6,0.04)] dark:shadow-[0_1px_3px_rgba(0,0,0,0.12)]">
                                                {{ alerta.Estatus }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 max-w-xs truncate font-light tracking-[0.003em]">
                                            {{ alerta.Descripcion }}</td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-[13px] text-slate-600/85 dark:text-neutral-300/90 max-w-xs truncate font-light tracking-[0.003em]">
                                            {{ alerta.Razones }}</td>
                                        <td class="px-6 py-4 text-[13px] font-light tracking-[0.003em]">
                                            <template v-if="obtenerArchivosEvidencia(alerta.Evidencias).length">
                                                <div class="flex flex-col gap-1.5 min-w-[220px]">
                                                    <a
                                                        v-for="(file, idx) in obtenerArchivosEvidencia(alerta.Evidencias)"
                                                        :key="idx"
                                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline decoration-blue-300/60 underline-offset-2"
                                                        :href="urlArchivoPublico(file.path)"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        {{ file.original || (file.path.split('/').pop() ?? file.path) }}
                                                    </a>
                                                </div>
                                            </template>
                                            <template v-else-if="formatEvidencias(alerta.Evidencias).totalPagado !== null">
                                                <div class="flex flex-col gap-1.5 min-w-[200px]">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="text-slate-600/85 dark:text-neutral-300/90 font-medium">Total:</span>
                                                        <span
                                                            class="text-slate-700/90 dark:text-neutral-100/90 font-semibold">
                                                            {{ formatEvidencias(alerta.Evidencias).totalPagado }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="text-slate-500/75 dark:text-neutral-400/80 text-[12px]">Pagos:</span>
                                                        <span
                                                            class="text-slate-600/85 dark:text-neutral-300/90 text-[12px] font-medium">
                                                            {{ formatEvidencias(alerta.Evidencias).totalPagos }}
                                                        </span>
                                                        <span
                                                            class="text-slate-500/75 dark:text-neutral-400/80 text-[12px]">•</span>
                                                        <span :class="[
                                                            'text-[12px] font-medium px-2 py-0.5 rounded-full',
                                                            formatEvidencias(alerta.Evidencias).operacionPagada
                                                                ? 'bg-green-50/70 dark:bg-green-900/20 text-green-700/90 dark:text-green-300/90'
                                                                : 'bg-amber-50/70 dark:bg-amber-900/20 text-amber-700/90 dark:text-amber-300/90'
                                                        ]">
                                                            {{ formatEvidencias(alerta.Evidencias).operacionPagada ?
                                                                'Pagada' : 'Pendiente' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div
                                                    class="text-slate-500/70 dark:text-neutral-400/70 text-[12px] italic max-w-xs truncate">
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

                <!-- Modal para el botón Nuevo -->
                <Transition name="fade">
                    <div v-if="showNuevoAlertaModal"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                        <div
                            class="bg-white dark:bg-neutral-900 rounded-xl shadow-lg px-0 py-0 min-w-[600px] w-full max-w-3xl relative flex flex-col"
                            style="max-height: 90vh;">
                            <button @click="closeNuevoAlertaModal"
                                class="absolute top-3 right-3 text-neutral-400 hover:text-neutral-700 dark:hover:text-white text-xl z-10">&times;</button>
                            <div class="overflow-y-auto px-10 py-12"
                                style="max-height: 90vh;">
                                <h2 class="text-2xl font-bold mb-6 text-slate-700 dark:text-neutral-100">
                                    Información de la alerta
                                </h2>
                                <div v-if="errorGeneral || Object.keys(erroresValidacion).length"
                                     class="mb-4 p-3 rounded-lg border border-red-200/70 bg-red-50/60 text-red-800 dark:bg-red-900/20 dark:text-red-200 dark:border-red-800/50">
                                    <p v-if="errorGeneral" class="text-[13px] mb-1">{{ errorGeneral }}</p>
                                    <ul class="list-disc ml-5 text-[12.5px]" v-if="Object.keys(erroresValidacion).length">
                                        <li v-for="(msgs, field) in erroresValidacion" :key="field">
                                            {{ Array.isArray(msgs) ? msgs[0] : String(msgs) }}
                                        </li>
                                    </ul>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2">
                                    <div>
                                        <Input
                                            label="Patrón"
                                            type="text"
                                            id="patron"
                                            v-model="patron"
                                            disabled
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Select
                                            label="Estatus"
                                            :options="opcionesEstatus"
                                            v-model="estatus"
                                            placeholder="Seleccionar estatus"
                                            id="estatus"
                                            name="estatus"
                                            required
                                        />
                                        <InputError :message="erroresValidacion.estatus?.[0]" />
                                    </div>
                                    <div>
                                        <Select
                                            label="Nombre"
                                            :options="opcionesNombre"
                                            v-model="nombre"
                                            placeholder="Seleccionar nombre"
                                            id="nombre"
                                            name="nombre"
                                            required
                                        />
                                        <InputError :message="erroresValidacion.nombre?.[0]" />
                                    </div>
                                    <div>
                                        <Input
                                            label="No. cliente"
                                            type="text"
                                            id="noCliente"
                                            v-model="noCliente"
                                            placeholder="Ingresar No. de cliente"
                                            disabled
                                            required
                                        />
                                        <InputError :message="erroresValidacion.noCliente?.[0]" />
                                    </div>
                                    <div>
                                        <Select
                                            label="Folio poliza"
                                            :options="opcionesPoliza"
                                            v-model="poliza"
                                            placeholder="Seleccionar folio"
                                            id="poliza"
                                            name="poliza"
                                            required
                                        />
                                        <InputError :message="erroresValidacion.poliza?.[0]" />
                                    </div>
                                    <div>
                                        <Select
                                            label="Agente"
                                            :options="opcionesAgente"
                                            v-model="agente"
                                            placeholder="Seleccionar agente"
                                            id="agente"
                                            name="agente"
                                            required
                                        />
                                        <InputError :message="erroresValidacion.agente?.[0]" />
                                    </div>
                                    <div>
                                        <Input
                                            label="Monto"
                                            type="float"
                                            id="monto"
                                            v-model="monto"
                                            prefix="$"
                                            placeholder="Ingresar monto"
                                            required
                                        />
                                        <InputError :message="erroresValidacion.monto?.[0]" />
                                    </div>
                                    <div>
                                        <Select
                                            label="Moneda"
                                            :options="opcionesMoneda"
                                            v-model="moneda"
                                            placeholder="Seleccionar moneda"
                                            id="moneda"
                                            name="moneda"
                                        />
                                        <InputError :message="erroresValidacion.IDMoneda?.[0]" />
                                    </div>
                                <div>
                                    <Select
                                        label="Instrumento"
                                        :options="opcionesInstrumento"
                                        v-model="instrumento"
                                        placeholder="Seleccionar instrumento"
                                        id="instrumento"
                                        name="instrumento"
                                        required
                                    />
                                    <InputError :message="erroresValidacion.instrumento?.[0]" />
                                </div>
                                    <div class="md:col-span-2">
                                        <Textarea
                                            label="Descripción de la operación"
                                            id="descripcionOperacion"
                                            name="descripcionOperacion"
                                            placeholder="Agrega una descripción detallada"
                                            v-model="descripcionOperacion"
                                            :rows="4"
                                            required
                                        />
                                        <InputError :message="erroresValidacion.descripcionOperacion?.[0]" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <Textarea
                                            label="Razones"
                                            id="razones"
                                            name="razones"
                                            placeholder="Agrega las razones"
                                            v-model="razones"
                                            :rows="4"
                                            required
                                        />
                                        <InputError :message="erroresValidacion.razones?.[0]" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <SelectFile
                                            label="Evidencias"
                                            id="evidencias"
                                            name="evidencias"
                                            accept=".pdf,.jpg,.png,.doc,.docx"
                                            v-model="evidencias"
                                            required
                                        />
                                        <InputError :message="(erroresValidacion['evidencias']?.[0] || erroresValidacion['evidencias.*']?.[0])" />
                                    </div>
                                    <div class="md:col-span-2 mt-6 flex justify-end gap-3">
                                        <button @click="closeNuevoAlertaModal"
                                            class="px-6 py-3.5 bg-[#f8fafc]/90 dark:bg-neutral-900/90 border border-slate-100/60 dark:border-neutral-800/70 text-slate-700/85 dark:text-neutral-200/90 text-[14px] font-medium tracking-[0.008em] rounded-[14px]
                                            shadow-[0_2px_7px_rgba(15,23,42,0.025)] dark:shadow-[0_2px_7px_rgba(0,0,0,0.20)] hover:shadow-[0_3px_12px_rgba(15,23,42,0.05)] hover:border-slate-200/60 dark:hover:border-neutral-600/60 hover:bg-[#f8fafc] dark:hover:bg-black
                                            transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1)">
                                            Cancelar
                                        </button>
                                        <button @click="emitirReporte" :disabled="submitLoading"
                                            class="px-7 py-3.5 bg-gradient-to-br from-blue-400/90 to-blue-500/90 text-white/95 text-[14px] font-medium tracking-[0.008em] rounded-[14px]
                                            shadow-[0_3px_12px_rgba(59,130,246,0.13)] hover:shadow-[0_5px_18px_rgba(59,130,246,0.18)]
                                            hover:from-blue-500/90 hover:to-blue-600/90
                                            disabled:from-slate-300/80 disabled:to-slate-400/80 dark:disabled:from-neutral-800/80 dark:disabled:to-neutral-800/70 disabled:shadow-none disabled:cursor-not-allowed
                                            transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1) transform hover:scale-[1.015] hover:-translate-y-[0.5px] active:translate-y-0 active:scale-100
                                            focus:outline-none focus:ring-2 focus:ring-blue-400/25 dark:focus:ring-neutral-600/50 focus:ring-offset-2 focus:ring-offset-[#f8fafc]/50 dark:focus:ring-offset-black/30">
                                            <div class="flex items-center gap-2.5">
                                                <template v-if="submitLoading">
                                                    <div class="w-4 h-4 border-[2px] border-white/25 border-t-white/95 rounded-full animate-spin"></div>
                                                    <span>Enviando...</span>
                                                </template>
                                                <template v-else>
                                                    <span>Emitir reporte</span>
                                                </template>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>

                <Transition name="fade" mode="out-in">
                    <div v-if="!isLoading && alertas.length === 0"
                        class="bg-[#f8fafc]/70 dark:bg-neutral-900/90 backdrop-blur-xl rounded-[20px] shadow-[0_3px_20px_rgba(15,23,42,0.035)] dark:shadow-[0_3px_20px_rgba(0,0,0,0.24)] border border-slate-100/50 dark:border-neutral-800/70 p-20 text-center">
                        <div class="flex flex-col items-center gap-4">
                            <div
                                class="w-16 h-16 bg-slate-100/50 dark:bg-neutral-800/95 rounded-full flex items-center justify-center shadow-[0_2px_7px_rgba(15,23,42,0.025)] dark:shadow-[0_2px_7px_rgba(0,0,0,0.17)] transition-all duration-500 hover:bg-slate-100/60 dark:hover:bg-neutral-700/80">
                                <Bell :size="30" :stroke-width="1.5"
                                    class="text-slate-400/65 dark:text-neutral-200/65 transition-colors duration-500" />
                            </div>
                            <p
                                class="text-slate-500/75 dark:text-neutral-400/85 text-[14px] font-medium tracking-[0.008em] transition-colors duration-500">
                                No
                                hay
                                alertas para mostrar</p>
                            <p
                                class="text-slate-400/65 dark:text-neutral-500/80 text-[13px] font-light tracking-[0.008em] transition-colors duration-500">
                                Selecciona
                                un rango de fechas y
                                presiona buscar</p>
                        </div>
                    </div>
                </Transition>

                <div v-if="alertas.length > 0"
                    class="mt-8 flex justify-between items-center text-[13px] text-slate-500/70 dark:text-neutral-400/70 px-2">
                    <p class="font-light tracking-[0.008em] transition-colors duration-500">
                        Mostrando <span class="font-medium text-slate-600/80 dark:text-neutral-200/90">{{ alertas.length
                            }}</span>
                        {{ alertas.length === 1 ? 'alerta' : 'alertas' }}
                    </p>
                    <p
                        class="text-[12px] text-slate-400/65 dark:text-neutral-500/70 font-light tracking-[0.008em] transition-colors duration-500">
                        Última actualización: {{ new Date().toLocaleTimeString('es-MX') }}
                    </p>
                </div>
            </div>
        </div>
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
