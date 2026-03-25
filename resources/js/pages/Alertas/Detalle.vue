<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
import { Head, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { computed, ref, onMounted, watch, toRefs } from 'vue';
import { ArrowLeft, Globe } from 'lucide-vue-next';

// 🚩 Usar window.route (Ziggy) si está disponible
const route = (window as any).route as (...args: any[]) => any;

import Select from '@/components/forms/Select.vue';
import SelectFile from '@/components/forms/SelectFile.vue';
import InputError from '@/components/InputError.vue';
import Textarea from '@/components/forms/Textarea.vue';

// ---- Toast Implementation ----
const toastVisible = ref(false);
const toastMessage = ref('');
const toastTimeout = ref<number | null>(null);
const pendingPageRefreshAfterToast = ref(false);

const toast = {
    success: (msg: string) => {
        toastMessage.value = msg;
        toastVisible.value = true;
        if (toastTimeout.value) clearTimeout(toastTimeout.value);
        if (pendingPageRefreshAfterToast.value) {
            pendingPageRefreshAfterToast.value = false;
        }
        toastTimeout.value = window.setTimeout(() => {
            toastVisible.value = false;
            toastMessage.value = '';
            toastTimeout.value = null;
            if (pendingPageRefreshAfterToast.value) {
                pendingPageRefreshAfterToast.value = false;
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }, 3000);
    },
    error: (msg: string) => window.alert(msg),
};
watch(toastVisible, (visible) => {
    if (!visible && pendingPageRefreshAfterToast.value) {
        pendingPageRefreshAfterToast.value = false;
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
});
// ---- end Toast ----

const MONEDAS = [
    { id: 'MXN', nombre: 'Peso mexicano', simbolo: '$', min: 0.01, max: 999999999.99, decimales: 2 },
    { id: 'USD', nombre: 'Dólar estadounidense', simbolo: 'US$', min: 0.01, max: 999999999.99, decimales: 2 },
];
function getMonedaInfo(id: string | null | undefined) {
    const m = MONEDAS.find(m => m.id === (id || '').toUpperCase());
    return m || MONEDAS[0];
}

const props = defineProps<{
    alerta: any,
    cliente?: any,
    operacion?: any,
    reportes?: any[]
}>();

// Usamos toRefs para mantener la reactividad en el script
const { alerta, cliente, operacion, reportes } = toRefs(props);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alertas', href: route ? route('alertas.index') : '/alertas' },
    { title: 'Detalle de Alerta', href: '' },
];

function statusBadgeColor(status: string): string {
    if (!status) return 'bg-gray-200 text-gray-600';
    switch (status.toLowerCase()) {
        case 'generado':
            return 'bg-yellow-100 text-yellow-800 border border-yellow-300';
        case 'analizada':
        case 'analizado':
            return 'bg-blue-100 text-blue-800 border border-blue-300';
        case 'cerrado':
        case 'cerrada':
            return 'bg-green-100 text-green-800 border border-green-300';
        case 'descartado':
            return 'bg-gray-200 text-gray-700 border border-gray-300';
        default:
            return 'bg-slate-100 text-slate-800 border border-slate-300';
    }
}
function patronBadgeClass(patron: string | undefined) {
    if (!patron) return 'bg-slate-200 text-slate-600 border border-slate-300';
    if (patron.toLowerCase() === 'fraccionado')
        return 'bg-purple-100 text-purple-800 border border-purple-200';
    return 'bg-slate-200 text-slate-700 border border-slate-300';
}

function personaTipoDisplay(id: number | null | undefined) {
    if (id === 1) return 'Física';
    if (id === 2) return 'Moral';
    return '';
}
function personaTipoNombreFromObj(obj: any) {
    if (!obj) return '-';
    if (typeof obj === 'object' && obj.TipoPersona) {
        if (typeof obj.TipoPersona === 'string') {
            return obj.TipoPersona.charAt(0).toUpperCase() + obj.TipoPersona.slice(1).toLowerCase();
        }
    }
    return '-';
}
function formatDate(date: string | null | undefined) {
    if (!date) return '-';
    try {
        const d = new Date(date);
        if (isNaN(d.getTime())) return date;
        return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: '2-digit' });
    } catch { return date; }
}

function numberFormat(n: any, moneda: string = 'MXN') {
    if (n == null || n === '') return '-';
    const info = getMonedaInfo(moneda);
    return `${info.simbolo}${new Intl.NumberFormat('es-MX', {
        style: 'decimal',
        minimumFractionDigits: info.decimales,
        maximumFractionDigits: info.decimales
    }).format(Number(n))}`;
}

function parseEvidencias(e: string | null | undefined): any {
    if (!e) return null;
    try { return typeof e === 'string' ? JSON.parse(e) : e; } catch { return null; }
}

const evidencias = computed(() => parseEvidencias(alerta.value?.Evidencias));

function getEvidenciaPublicUrl(ev: any): string | undefined {
    if (ev.url) {
        if (typeof ev.url === 'string' && ev.url.includes('/storage/')) {
            return ev.url;
        }
        if (
            typeof ev.url === 'string' &&
            (ev.url.startsWith('alertas/evidencias/') ||
                ev.url.startsWith('/alertas/evidencias/') ||
                ev.url.startsWith('public/alertas/evidencias/'))
        ) {
            let relativePath = ev.url.replace(/^public\//, '');
            if (!relativePath.startsWith('/')) relativePath = '/' + relativePath;
            return '/storage' + relativePath;
        }
        if (typeof ev.url === 'string' && /^https?:\/\//.test(ev.url)) {
            return ev.url;
        }
    }
    if (ev.path) {
        if (typeof ev.path === 'string') {
            let relativePath = ev.path.replace(/^public\//, '');
            if (!relativePath.startsWith('/')) relativePath = '/' + relativePath;
            return '/storage' + relativePath;
        }
    }
    return undefined;
}

const listaEvidencias = computed(() => {
    let evs = Array.isArray(evidencias.value?.archivos)
        ? evidencias.value.archivos
        : (Array.isArray(evidencias.value) ? evidencias.value : []);
    if (evs.length && typeof evs[0] === 'string') {
        return evs.map((e: string) => ({
            name: e.split('/').pop(),
            url: '/storage/' + e.replace(/^public\//, '').replace(/^\/?/, ''),
            path: e
        }));
    }
    return evs.map((e: any) => {
        let name =
            e.name || e.filename || e.Nombre || e.FileName || (e.path ? e.path.split('/').pop() : undefined) || 'Archivo';
        let id = e.id || e.ID || e.Id || e.FileName || e.path || e.url || undefined;
        let publicUrl = getEvidenciaPublicUrl(e);
        return {
            name,
            url: publicUrl,
            id,
            ...e,
        };
    });
});

const pagosOperacion = computed(() => {
    return (operacion.value?.pagos || []);
});
const detallePagos = computed(() => evidencias.value?.detalle_pagos ?? []);

const estatus = ref('');
const evidenciasFormulario = ref<File[] | undefined>(undefined);
const razones = ref('');
const descripcion = ref('');
const erroresValidacion = ref<any>({});

const opcionesEstatus = [
    { value: 'Generado', label: 'Generado' },
    { value: 'Analizado', label: 'Analizado' },
    { value: 'Cerrado', label: 'Cerrado' },
    { value: 'Enviado', label: 'Enviado' },
    { value: 'Reportado', label: 'Reportado' },
];

const alertaEstatus = computed(() => (alerta.value?.Estatus || '').toLowerCase());
const puedeEditar = computed(() => {
    const est = alertaEstatus.value;
    return est !== 'enviado' && est !== 'reportado' && est !== 'cerrado';
});
const puedeEliminarEvidencias = computed(() => {
    const est = alertaEstatus.value;
    return est !== 'enviado' && est !== 'reportado' && est !== 'cerrado';
});

// Mostrar información relevante del cliente si existe
const clienteInfo = computed(() => {
    if (!cliente.value) return null;

    const nombreCompleto = cliente.value.Nombre && cliente.value.ApellidoPaterno
        ? [
            cliente.value.Nombre,
            cliente.value.ApellidoPaterno,
            cliente.value.ApellidoMaterno || ''
          ].filter(Boolean).join(' ')
        : (
            cliente.value.Nombre ?? cliente.value.RazonSocial ?? cliente.value.nombre ?? cliente.value.razon_social ?? ''
        );

    const tipo = cliente.value.tipo_persona
        ? personaTipoNombreFromObj(cliente.value.tipo_persona)
        : (cliente.value.IDTipoPersona ? personaTipoDisplay(cliente.value.IDTipoPersona) : '-');

    // El domicilio: usar primer domicilio, si hay
    let domicilio = '-';
    if (Array.isArray(cliente.value.domicilios) && cliente.value.domicilios.length > 0) {
        const d = cliente.value.domicilios[0];
        domicilio = [
            d.Calle,
            d.NoExterior ? `No. ${d.NoExterior}` : '',
            d.NoInterior ? `Int. ${d.NoInterior}` : '',
            d.Colonia,
            d.CP ? `CP ${d.CP}` : '',
            d.Municipio,
            d.IDEstado ? `Estado ${d.IDEstado}` : '',
        ].filter(Boolean).join(', ');
    }

    return {
        nombre: nombreCompleto || '-',
        tipo,
        rfc: cliente.value.RFC || '-',
        curp: cliente.value.CURP || '-',
        fechaNacimiento: cliente.value.FechaNacimiento ? formatDate(cliente.value.FechaNacimiento) : '-',
        domicilio,
        idCliente: cliente.value.IDCliente,
        activo: cliente.value.Activo,
    };
});

// Función para ir a los detalles del cliente (mostrar SIEMPRE que haya un cliente)
const loadingActivar = ref(false);
const showActivarClienteModal = ref(false);

function abrirModalActivarCliente() {
    if (loadingActivar.value || (clienteInfo.value && clienteInfo.value.activo)) return;
    showActivarClienteModal.value = true;
}

function cerrarModalActivarCliente() {
    if (loadingActivar.value) return;
    showActivarClienteModal.value = false;
}

function confirmarActivarCliente() {
    if (loadingActivar.value) return;
    showActivarClienteModal.value = false;
    activarCliente();
}

function activarCliente() {
    if (!cliente.value || !cliente.value.IDCliente) return;

    const url = route
        ? route('clientes.activar', { id_cliente: cliente.value.IDCliente })
        : `/clientes/${cliente.value.IDCliente}/activar`;

    loadingActivar.value = true;
    router.post(url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Cliente activado correctamente.');
        },
        onError: (errs) => {
            console.error(errs);
            toast.error('Hubo un error al activar el cliente.');
        },
        onFinish: () => {
            loadingActivar.value = false;
        }
    });
}

function verDetallesCliente() {
    if (cliente.value && cliente.value.IDCliente) {
        // La ruta está registrada en routes/web.php y usa ClientesController@verDetallesCliente
        // Usar Ziggy si está disponible, sino fallback a la ruta estándar
        if (route) {
            router.visit(route('clientes.ver-detalles', cliente.value.IDCliente));
        } else {
            router.visit(`/clientes/ver-detalles/${cliente.value.IDCliente}`);
        }
    }
}

function volverPaginaAnterior() {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    if (route) {
        router.visit(route('alertas.index'));
        return;
    }
    router.visit('/alertas');
}

const esPreocupante = computed(() => {
    return (alerta.value.Patron && typeof alerta.value.Patron === 'string' && alerta.value.Patron.trim().toLowerCase() === 'preocupante')
});
const pagoPreocupante = computed(() => {
    return {
        monto: alerta.value.MontoOperacion,
        instrumento: alerta.value.InstrumentoMonetario,
        moneda: alerta.value.IDMoneda
    };
});

onMounted(() => {
    if (puedeEditar.value) {
        if (alerta.value.Estatus && estatus.value === '') {
            const found = opcionesEstatus.find(opt =>
                opt.value.toLowerCase() === alerta.value.Estatus.toLowerCase()
            );
            estatus.value = found ? found.value : alerta.value.Estatus;
        }
        if (alerta.value.Razones && razones.value === '') {
            razones.value = alerta.value.Razones;
        }
        if (alerta.value.Descripcion && descripcion.value === '') {
            descripcion.value = alerta.value.Descripcion;
        }
    }
});

const loadingEvidencias = ref<(string | number)[]>([]);

function getEvidenciaKey(ev: any, idx: any): string | number {
    return ev.id || ev.ID || ev.Id || ev.FileName || ev.path || ev.url || idx;
}

function eliminarEvidencia(ev: any, idx: any) {
    const est = alertaEstatus.value;
    if (est === 'cerrado' || est === 'enviado' || est === 'reportado') {
        return;
    }
    if (!confirm('¿Seguro que deseas eliminar este archivo de evidencia?')) return;
    const evidenciaKey = getEvidenciaKey(ev, idx);
    loadingEvidencias.value.push(evidenciaKey);

    router.delete('/alertas/evidencias', {
        data: {
            idAlerta: alerta.value.IDRegistroAlerta,
            path: ev.path,
        },
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['alerta'] });
        },
        onError: () => {
            loadingEvidencias.value = loadingEvidencias.value.filter((x: string | number) => x !== evidenciaKey);
        },
        onFinish: () => {
            loadingEvidencias.value = loadingEvidencias.value.filter((x: string | number) => x !== evidenciaKey);
        }
    });
}

const loadingSubmit = ref(false);

function submitEditarAlerta(e: Event) {
    e.preventDefault();
    erroresValidacion.value = {};

    const formData = new FormData();
    formData.append('estatus', estatus.value);
    formData.append('razones', razones.value);
    formData.append('descripcion', descripcion.value);

    if (evidenciasFormulario.value && evidenciasFormulario.value.length > 0) {
        for (let i = 0; i < evidenciasFormulario.value.length; i++) {
            formData.append('evidencias[]', evidenciasFormulario.value[i]);
        }
    }

    formData.append('idAlerta', alerta.value.IDRegistroAlerta);

    loadingSubmit.value = true;

    formData.append('_method', 'POST');

    router.post(
        route ? route('alertas.editar.dos') : '/alertas/editar-dos',
        formData,
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (errs) => {
                erroresValidacion.value = errs || {};
            },
            onSuccess: () => {
                toast.success('Los cambios se guardaron correctamente');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            },
            onFinish: () => {
                loadingSubmit.value = false;
                evidenciasFormulario.value = undefined;
            }
        }
    );
}
</script>

<template>
    <Head title="Detalle de alerta" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <FadeIn>
            <div>
                <!-- Toast -->
                <transition name="toast-fade">
                    <div v-if="toastVisible"
                        class="fixed top-6 left-1/2 z-50 -translate-x-1/2 bg-green-600 text-white px-6 py-3 rounded-full shadow-lg flex items-center gap-2 text-sm font-medium toast-shadow min-w-[250px] text-center"
                        style="pointer-events:auto;" role="alert">
                        <svg class="w-5 h-5 text-white opacity-90 shrink-0" fill="none" stroke="currentColor"
                            stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.2"
                                fill="currentColor" fill-opacity="0.15" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12l4 4 8-8" />
                        </svg>
                        <span class="flex-1">{{ toastMessage }}</span>
                        <button
                            class="ml-3 text-white/80 hover:text-white font-bold rounded-full focus:outline-none transition-all p-1"
                            type="button" @click="toastVisible = false" aria-label="Cerrar notificación">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                    d="M6 6l12 12M6 18L18 6" />
                            </svg>
                        </button>
                    </div>
                </transition>

                <div class="mx-auto max-w-6xl py-6 px-2 sm:px-4 md:px-6 space-y-7">
                    <div class="mb-4">
                        <button
                            @click="volverPaginaAnterior"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-600 shadow-sm transition hover:bg-gray-50 hover:text-blue-700 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:hover:text-blue-300"
                        >
                            <ArrowLeft class="h-4 w-4"/>
                            <span>Regresar</span>
                        </button>
                    </div>
                    <!-- INFORMACIÓN DEL CLIENTE (botón siempre visible si hay cliente) -->
                    <div v-if="clienteInfo" class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border">
                        <div class="flex justify-between items-center mb-3">
                            <div class="font-semibold text-lg text-gray-900 dark:text-neutral-100">Información del cliente</div>
                            <div class="flex gap-2">
                                <!-- Botón de Activar Cliente -->
                                <button
                                    v-if="cliente && cliente.IDCliente && !clienteInfo.activo"
                                    type="button"
                                    @click="abrirModalActivarCliente"
                                    :disabled="loadingActivar"
                                    class="inline-flex items-center px-3 py-1.5 rounded bg-green-600 text-white text-sm font-semibold hover:bg-green-700 transition disabled:opacity-50"
                                >
                                    <span v-if="loadingActivar" class="mr-2 animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                                    Activar cliente
                                </button>
                                <button
                                    v-if="cliente && cliente.IDCliente"
                                    type="button"
                                    @click="verDetallesCliente"
                                    class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition"
                                >
                                    Ver detalles del cliente
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-col md:flex-row gap-x-8 gap-y-2 flex-wrap">
                            <div>
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Estatus</span>
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-bold"
                                    :class="clienteInfo.activo ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'">
                                    {{ clienteInfo.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Nombre Completo</span>
                                <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">{{ clienteInfo.nombre }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Tipo de Persona</span>
                                <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">{{ clienteInfo.tipo }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">RFC</span>
                                <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">{{ clienteInfo.rfc }}</span>
                            </div>
                            <div v-if="clienteInfo.curp && clienteInfo.curp !== '-'">
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">CURP</span>
                                <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">{{ clienteInfo.curp }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Fecha de nacimiento</span>
                                <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">{{ clienteInfo.fechaNacimiento }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Domicilio</span>
                                <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">{{ clienteInfo.domicilio }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bloque de información de la alerta (Patrón y Estatus) -->
                    <div class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border flex flex-col md:flex-row items-start md:items-center gap-x-8 gap-y-2">
                        <div>
                            <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Patrón</span>
                            <span class="inline-block px-3 py-0.5 rounded-full text-sm font-semibold align-middle"
                                :class="patronBadgeClass(alerta.Patron)">
                                {{ alerta.Patron ?? '-' }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Estatus</span>
                            <span class="inline-block px-3 py-0.5 rounded-full text-sm font-semibold align-middle"
                                :class="statusBadgeColor(alerta.Estatus)">
                                {{ alerta.Estatus ?? '-' }}
                            </span>
                        </div>
                    </div>

                    <!-- 6. Evidencias del sistema -->
                    <div v-if="evidencias" class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border">
                        <div class="font-semibold text-lg text-gray-900 dark:text-neutral-100 mb-3">Análisis automático</div>
                        <div class="flex flex-wrap gap-x-8 gap-y-2 mb-4" v-if="!esPreocupante">
                            <div>
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Total de pagos</span>
                                <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">{{ evidencias.total_pagos ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Total pagado</span>
                                <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">
                                    {{ numberFormat(evidencias.total_pagado, String(alerta.IDMoneda ?? operacion?.IDMoneda ?? 'MXN')) }}
                                    <span class="ml-1 text-xs text-gray-600 dark:text-gray-300">
                                        {{ getMonedaInfo(String(alerta.IDMoneda ?? operacion?.IDMoneda ?? 'MXN')).nombre }}
                                    </span>
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Saldo pendiente</span>
                                <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">
                                    {{ numberFormat(evidencias.saldo_pendiente, String(alerta.IDMoneda ?? operacion?.IDMoneda ?? 'MXN')) }}
                                    <span class="ml-1 text-xs text-gray-600 dark:text-gray-300">
                                        {{ getMonedaInfo(String(alerta.IDMoneda ?? operacion?.IDMoneda ?? 'MXN')).nombre }}
                                    </span>
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">¿Liquidada?</span>
                                <span class="text-sm font-bold">
                                    <span v-if="evidencias.operacion_pagada === true" class="text-green-700 dark:text-green-300">Sí</span>
                                    <span v-else-if="evidencias.operacion_pagada === false" class="text-yellow-700 dark:text-yellow-200">No</span>
                                    <span v-else>-</span>
                                </span>
                            </div>
                        </div>

                        <!-- Evidencias adjuntas-->
                        <div v-if="listaEvidencias.length" class="mb-3">
                            <div class="text-[13px] font-medium mb-1 text-gray-700 dark:text-neutral-200">Archivos de evidencia actuales:</div>
                            <ul class="space-y-1">
                                <li v-for="(ev, idx) in listaEvidencias" :key="getEvidenciaKey(ev, idx)" class="flex items-center gap-3 text-[15px]">
                                    <a v-if="ev.url" class="hover:underline text-blue-700 dark:text-blue-300 break-all" :href="ev.url" target="_blank" rel="noopener">{{ ev.name }}</a>
                                    <span v-else>{{ ev.name }}</span>
                                    <!-- Botón de eliminar solo visible si puede eliminar evidencias -->
                                    <button v-if="puedeEliminarEvidencias"
                                        class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-red-100 text-red-800 hover:bg-red-200 ml-2 font-semibold transition disabled:opacity-50"
                                        @click="eliminarEvidencia(ev, idx)"
                                        :disabled="loadingEvidencias.includes(getEvidenciaKey(ev, idx))" type="button"
                                        title="Eliminar evidencia">
                                        <span v-if="!loadingEvidencias.includes(getEvidenciaKey(ev, idx))">Eliminar</span>
                                        <span v-else class="animate-spin w-4 h-4 inline-block border-2 border-current rounded-full border-t-transparent"></span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <!-- FIN evidencias -->

                        <div class="overflow-auto">
                            <template v-if="esPreocupante">
                                <table class="min-w-full text-xs md:text-sm table-auto border-collapse">
                                    <thead>
                                        <tr class="bg-gray-100 dark:bg-neutral-800/70">
                                            <th class="px-3 py-2 font-semibold">Monto operación</th>
                                            <th class="px-3 py-2 font-semibold">Instrumento monetario</th>
                                            <th class="px-3 py-2 font-semibold">Moneda</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                {{ numberFormat(pagoPreocupante.monto, String(pagoPreocupante.moneda ?? 'MXN')) }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                {{ pagoPreocupante.instrumento || '-' }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                {{ getMonedaInfo(String(pagoPreocupante.moneda ?? 'MXN')).nombre }}
                                                <span v-if="pagoPreocupante.moneda">
                                                    ({{ getMonedaInfo(String(pagoPreocupante.moneda ?? 'MXN')).simbolo }})
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </template>
                            <template v-else>
                                <table class="min-w-full text-xs md:text-sm table-auto border-collapse">
                                    <thead>
                                        <tr class="bg-gray-100 dark:bg-neutral-800/70">
                                            <th class="px-3 py-2 font-semibold">Fecha de pago</th>
                                            <th class="px-3 py-2 font-semibold">Forma de pago</th>
                                            <th class="px-3 py-2 font-semibold">Monto</th>
                                            <th class="px-3 py-2 font-semibold">Moneda</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(pago, i) in detallePagos.length ? detallePagos : pagosOperacion" :key="i">
                                            <td class="px-3 py-2 whitespace-nowrap">{{ pago.fecha_pago ?? pago.FechaPago ?? '-' }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap">{{ pago.forma_pago ?? '-' }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                {{ numberFormat(pago.monto ?? pago.Monto, String(pago.moneda ?? pago.IDMoneda ?? 'MXN')) }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                {{ getMonedaInfo(String(pago.moneda ?? pago.IDMoneda ?? 'MXN')).nombre }}
                                                <span v-if="pago.moneda || pago.IDMoneda">({{ getMonedaInfo(String(pago.moneda ?? pago.IDMoneda ?? 'MXN')).simbolo }})</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </template>
                        </div>
                    </div>

                    <!-- 7. Descripción de la alerta -->
                    <div class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border">
                        <div class="font-semibold text-lg text-gray-900 dark:text-neutral-100 mb-1">Descripción</div>
                        <div class="mb-2 text-sm text-gray-800 dark:text-neutral-200 whitespace-pre-line">{{ alerta.Descripcion || '-' }}</div>
                        <div v-if="alerta.Razones"
                            class="bg-yellow-50/80 dark:bg-yellow-900/15 border-l-4 border-yellow-400 py-2 px-4 rounded-md text-sm text-yellow-900 dark:text-yellow-200 mt-3">
                            <strong>Justificación:</strong>
                            <span class="pl-1">{{ alerta.Razones }}</span>
                        </div>
                    </div>

                    <!-- 8. Reportes relacionados -->
                    <div v-if="Array.isArray(reportes) && reportes.length"
                        class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border">
                        <div class="font-semibold text-lg text-gray-900 dark:text-neutral-100 mb-2 flex items-center gap-2">
                            Reportes relacionados
                        </div>
                        <div class="overflow-auto">
                            <table class="min-w-full text-xs md:text-sm table-auto border-collapse">
                                <thead>
                                    <tr class="bg-gray-100 dark:bg-neutral-800/80">
                                        <th class="px-3 py-2 font-semibold text-left">Tipo</th>
                                        <th class="px-3 py-2 font-semibold text-left">Periodo</th>
                                        <th class="px-3 py-2 font-semibold text-left">Monto</th>
                                        <th class="px-3 py-2 font-semibold text-left">Estatus</th>
                                        <th class="px-3 py-2 font-semibold text-left">Fecha de operación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(reporte, idx) in reportes" :key="idx"
                                        :class="reporte.Estatus?.toLowerCase() === 'reportado' ? 'bg-green-50/60 dark:bg-green-900/20' : ''">
                                        <td class="px-3 py-2">{{ reporte.TipoReporte || '-' }}</td>
                                        <td class="px-3 py-2">{{ reporte.PeriodoReporte || '-' }}</td>
                                        <td class="px-3 py-2">
                                            {{ numberFormat(reporte.Monto, reporte.IDMoneda ?? 'MXN') }}
                                            <span class="ml-1 text-xs text-gray-600 dark:text-gray-300">
                                                {{ getMonedaInfo(reporte.IDMoneda).nombre }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span v-if="reporte.Estatus?.toLowerCase() === 'reportado'"
                                                class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                                                Reportado
                                            </span>
                                            <span v-else
                                                class="inline-block px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700 border border-gray-200">{{ reporte.Estatus || '-' }}</span>
                                        </td>
                                        <td class="px-3 py-2">{{ formatDate(reporte.FechaOperacion) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="puedeEditar"
                        class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border mb-3">
                        <div class="font-semibold text-lg text-gray-900 dark:text-neutral-100 mb-3">
                            Editar Alerta
                        </div>
                        <form @submit="submitEditarAlerta">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                <div>
                                    <Select label="Estatus" :options="opcionesEstatus" v-model="estatus"
                                        placeholder="Seleccionar estatus" id="estatus" name="estatus" required />
                                    <InputError :message="erroresValidacion.estatus?.[0]" />
                                </div>
                                <div>
                                    <Textarea label="Descripción" id="descripcion" name="descripcion"
                                        placeholder="Agrega la descripción" v-model="descripcion" :rows="3" required />
                                    <InputError :message="erroresValidacion.descripcion?.[0]" />
                                </div>
                                <div class="md:col-span-2">
                                    <SelectFile label="Evidencias" id="evidencias" name="evidencias"
                                        accept=".pdf,.jpg,.png,.doc,.docx" v-model="evidenciasFormulario" />
                                    <InputError
                                        :message="(erroresValidacion['evidencias']?.[0] || erroresValidacion['evidencias.*']?.[0])" />
                                </div>
                                <div class="md:col-span-2">
                                    <Textarea label="Razones" id="razones" name="razones"
                                        placeholder="Agrega las razones" v-model="razones" :rows="4" required />
                                    <InputError :message="erroresValidacion.razones?.[0]" />
                                </div>
                                <div class="md:col-span-2 flex flex-row-reverse mt-3">
                                    <button
                                        class="inline-flex items-center px-4 py-2 bg-blue-700 text-white rounded font-semibold hover:bg-blue-800 disabled:opacity-70 transition"
                                        type="submit" :disabled="loadingSubmit">
                                        <span v-if="loadingSubmit"
                                            class="mr-2 animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                                        Guardar cambios
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </FadeIn>

        <!-- Modal de Confirmación de Activación de Cliente -->
        <div
            v-if="showActivarClienteModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        >
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-neutral-900">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100 flex items-center gap-2">
                    <Globe class="w-5 h-5 text-blue-600" />
                    Confirmar activación de cliente
                </h3>
                <p class="mt-3 text-sm text-gray-700 dark:text-neutral-300">
                    Antes de activar este cliente, se sugiere revisar primero sus coincidencias en listas de observación y sus alertas PLD.
                </p>
                <div class="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-xs text-yellow-800 dark:border-yellow-800/60 dark:bg-yellow-900/20 dark:text-yellow-200">
                    Estado actual:
                    <span class="font-semibold">{{ (cliente && cliente.CoincideEnListasNegras) ? 'Con coincidencias en listas' : 'Sin coincidencias en listas' }}</span>
                    |
                    <span class="font-semibold">{{ (reportes && reportes.length > 0) ? 'Con alertas PLD' : 'Sin alertas PLD' }}</span>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="cerrarModalActivarCliente"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="confirmarActivarCliente"
                        :disabled="loadingActivar"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-70"
                    >
                        <span
                            v-if="loadingActivar"
                            class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"
                        ></span>
                        Confirmar activación
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.toast-fade-enter-active,
.toast-fade-leave-active {
    transition: opacity .28s cubic-bezier(0.4, 0, 0.2, 1), transform .28s cubic-bezier(0.4, 0, 0.2, 1);
}

.toast-fade-enter-from,
.toast-fade-leave-to {
    opacity: 0;
    transform: translateY(-18px) scale(0.97);
}

.toast-fade-leave-from,
.toast-fade-enter-to {
    opacity: 1;
    transform: none;
}

.toast-shadow {
    box-shadow: 0 4px 24px rgba(16, 95, 42, 0.15), 0 1.5px 8px rgba(0, 0, 0, 0.10);
}
</style>
