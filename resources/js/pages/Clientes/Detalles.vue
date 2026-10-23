<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { fechaEnRango, formatFechaLarga } from '@/utils/fechas'
import { Head, Link, router } from '@inertiajs/vue3'
import { type BreadcrumbItem } from '@/types'
import { computed, ref, watchEffect } from 'vue'
import {
    User,
    Home,
    Briefcase,
    ShieldAlert,
    BookX,
    ClipboardList,
    CheckCircle,
    FileText,
    Building,
    UserCircle,
    AlertTriangle,
    BadgeCheck,
    Paperclip,
    ArrowLeft,
    AlertCircle,
    Gauge,
    Download,
    Filter,
    X
} from 'lucide-vue-next'

// --- PROPS & BREADCRUMBS ---
const props = defineProps<{
    cliente: any
    domicilios: any[]
    operaciones: any[]
    alertas: any[]
    listasNegras: any[]
    perfilTransaccional: any
    listasUIF: any[]
    sistemasDelCliente: any[]
    esAltoRiesgo?: boolean
    factoresRiesgo?: string[]
    indicadores?: any
    catalogos?: any
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clientes', href: '/clientes' },
    { title: 'Detalles del cliente', href: '#' },
]

// --- STATE MANAGEMENT ---
const activeTab = ref('resumen')
const activandoCliente = ref(false)
const showActivarClienteModal = ref(false)

const navItems = [
    { id: 'resumen', label: 'Resumen', icon: FileText },
    { id: 'info', label: 'Datos Generales', icon: User },
    { id: 'dom', label: 'Domicilios', icon: Home },
    { id: 'op', label: 'Operaciones', icon: Briefcase },
    { id: 'detalles', label: 'Estadísticas cliente', icon: Gauge },
    { id: 'alertas', label: 'Alertas PLD', icon: ShieldAlert },
    { id: 'listas', label: 'Listas de Observación', icon: BookX },
    { id: 'perfil', label: 'Perfil Transaccional', icon: ClipboardList },
]

// --- BACK BUTTON HANDLER ---
function goBack() {
    window.history.back()
}

function activarCliente() {
    if (activandoCliente.value || isClienteActivo.value) return

    activandoCliente.value = true
    router.post(`/clientes/${props.cliente.IDCliente}/activar`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['cliente'] })
        },
        onFinish: () => {
            activandoCliente.value = false
        }
    })
}

function abrirModalActivarCliente() {
    if (activandoCliente.value || isClienteActivo.value) return
    showActivarClienteModal.value = true
}

function cerrarModalActivarCliente() {
    if (activandoCliente.value) return
    showActivarClienteModal.value = false
}

function confirmarActivarCliente() {
    if (activandoCliente.value) return
    showActivarClienteModal.value = false
    activarCliente()
}

// --- COMPUTED PROPERTIES FOR SUMMARY ---
const fullName = computed(() => {
    if (props.cliente.RazonSocial) return props.cliente.RazonSocial
    return [props.cliente.Nombre, props.cliente.ApellidoPaterno, props.cliente.ApellidoMaterno].filter(Boolean).join(' ')
})

const isPpe = computed(() => props.cliente.EsPPEActivo)
const onBlacklist = computed(() => props.listasNegras.length > 0 || props.listasUIF.length > 0)
const hasAlerts = computed(() => props.alertas.length > 0)
const isClienteActivo = computed(() => {
    const activo = props.cliente?.Activo
    return activo === true || activo === 1 || activo === '1'
})
const tieneCoincidenciaListas = computed(() => {
    const c = props.cliente?.CoincideEnListasNegras
    return c === true || c === 1 || c === '1'
})
const clienteEstatusLabel = computed(() => {
    if (tieneCoincidenciaListas.value) return 'Activo con coincidencia'
    return isClienteActivo.value ? 'Activo' : 'Bloqueado'
})
const clienteEstatusClass = computed(() => {
    if (tieneCoincidenciaListas.value)
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'
    return isClienteActivo.value
        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
        : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'
})

// --- Cliente de alto riesgo ---
const esAltoRiesgo = computed(() => {
    if (props.esAltoRiesgo === true) return true
    const flag = props.cliente?.EsAltoRiesgo
    return flag === true || flag === 1 || flag === '1'
})
const factoresRiesgo = computed(() => props.factoresRiesgo ?? [])

const personaTipo = computed(() => Number(props.cliente.IDTipoPersona) === 1 ? 'Persona Física' : 'Persona Moral')
const personaIcon = computed(() => Number(props.cliente.IDTipoPersona) === 1 ? UserCircle : Building)

const hasRFC = computed(() => !!props.cliente.RFC && String(props.cliente.RFC).trim() !== '')

const isExtranjero = computed(() => {
    return props.cliente.IDNacionalidad && props.cliente.IDNacionalidad !== 'MX'
})

// --- Categoria/Rechazo -- Nuevo calculo de razones fuera de categoría ---
const outCategoryReasons = computed(() => {
    const reasons = []
    if (!hasRFC.value) reasons.push('No cuenta con RFC')
    if (isExtranjero.value) reasons.push('Nacionalidad diferente a MX')
    return reasons
})
const isOutCategory = computed(() => outCategoryReasons.value.length > 0)

// --- FORMATTING & HELPER UTILS ---
type EvidenceFile = {
    path: string;
    original?: string;
};
type EvidencePayment = {
    Monto: number | string;
    IDMoneda: string;
    FechaPago: string;
    IDFormaPago?: number | string;
};

// Detectar cualquier JSON con la estructura de pagos fraccionados/acumulados
function getPagosFraccionadosVisual(evidenceStr: string | null | undefined): any | null {
    if (!evidenceStr) return null;
    try {
        const parsed = JSON.parse(evidenceStr);
        if (
            typeof parsed === 'object' &&
            parsed !== null &&
            typeof parsed.total_pagos === 'number' &&
            typeof parsed.total_pagado !== 'undefined' &&
            Array.isArray(parsed.detalle_pagos) &&
            Array.isArray(parsed.analisis_fraccionado)
        ) {
            return parsed;
        }
    } catch (e) {
        return null;
    }
    return null;
}

function getEvidenceFiles(evidenceStr: string | null | undefined): EvidenceFile[] | null {
    if (!evidenceStr) return null;
    try {
        const parsed = JSON.parse(evidenceStr);
        if (Array.isArray(parsed) && parsed.length > 0 && typeof parsed[0]?.path === 'string') {
            return parsed as EvidenceFile[];
        }
    } catch (e) {
        return null;
    }
    return null;
}

function getEvidencePayments(evidenceStr: string | null | undefined): EvidencePayment[] | null {
    if (!evidenceStr) return null;
    try {
        const parsed = JSON.parse(evidenceStr);
        // Case 1: { "pagos": [...] }
        if (typeof parsed === 'object' && parsed !== null && Array.isArray(parsed.pagos) && parsed.pagos.length > 0) {
            return parsed.pagos as EvidencePayment[];
        }
        // Case 2: It's directly an array of payments
        if (Array.isArray(parsed) && parsed.length > 0 && parsed[0].Monto !== undefined && parsed[0].FechaPago !== undefined) {
            return parsed as EvidencePayment[];
        }
    } catch (e) {
        return null;
    }
    return null;
}

function getPublicUrl(path: string): string {
    return `/storage/${path}`;
}

function formatEvidenceText(evidenceStr: string): string {
    try {
        return JSON.stringify(JSON.parse(evidenceStr), null, 2);
    } catch (e) {
        return evidenceStr;
    }
}

function formatDate(dateStr?: string | null): string {
    if (!dateStr) return 'N/A'
    const f = formatFechaLarga(dateStr)
    return f === '—' ? 'N/A' : f
}

function formatCurrency(value?: number | string | null): string {
    const numberValue = Number(value)
    if (value === null || value === undefined || isNaN(numberValue)) return '$0.00'
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(numberValue)
}

const CLASES_CANCELACION = 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'
const CLASES_AUMENTO = 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
const CLASES_DISMINUCION = 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'
const CLASES_SIN_CAMBIO = 'bg-gray-100 text-gray-700 dark:bg-neutral-800 dark:text-neutral-200'

/**
 * Clasifica la operación en una categoría excluyente:
 * emision | aumento | disminucion | cancelacion | sin_cambio
 */
function clasificarOperacion(op: any): string {
    if (esCancelacionEndoso(op)) return 'cancelacion'
    if (!op.FolioEndoso || String(op.FolioEndoso).trim() === '') return 'emision'
    const prima = parseFloat(op.PrimaTotal) || 0
    if (prima > 0) return 'aumento'
    if (prima < 0) return 'disminucion'
    return 'sin_cambio'
}

function tipoOperacionLabel(op: any, isEndoso: boolean = false): { label: string; class: string } | null {
    const tipo = clasificarOperacion(op)

    if (tipo === 'cancelacion') {
        return { label: 'Cancelación', class: CLASES_CANCELACION }
    }
    if (tipo === 'emision') {
        // Las emisiones no llevan etiqueta, salvo que se pidan como endoso.
        return isEndoso ? { label: 'Sin cambio', class: CLASES_SIN_CAMBIO } : null
    }
    if (tipo === 'aumento') {
        return { label: 'Aumento', class: CLASES_AUMENTO }
    }
    if (tipo === 'disminucion') {
        return { label: 'Disminución', class: CLASES_DISMINUCION }
    }
    return { label: 'Sin cambio', class: CLASES_SIN_CAMBIO }
}

function esCancelacionEndoso(op: any): boolean {
    return op.cancelaPoliza === 1 || op.cancelaPoliza === true
        || op.operacionCancelada === 1 || op.operacionCancelada === true
        || op.EsEndosoCancelacion === 1 || op.EsEndosoCancelacion === true
}

function riesgoNombre(n: number): string {
    switch (n) {
        case 1: return 'Bajo'
        case 2: return 'Medio'
        case 3: return 'Alto'
        default: return 'Desconocido'
    }
}

// ============================================================================
// FILTROS DE OPERACIONES (se aplican en memoria sobre las operaciones cargadas)
// ============================================================================
const filtroBusqueda = ref('')
const filtroFechaDesde = ref('')
const filtroFechaHasta = ref('')
const filtroTipoOperacion = ref('')
const filtroMoneda = ref('')
const filtroFormaPago = ref('')
const filtroTipoPago = ref('')
const filtroEstado = ref('')
const filtroPrimaMin = ref('')
const filtroPrimaMax = ref('')
const filtroPoliza = ref('')
const filtroSoloConPagos = ref(false)

function normalizarTexto(valor: any): string {
    return String(valor ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
}

const operacionesBase = computed<any[]>(() => props.operaciones ?? [])

const foliosDisponibles = computed<string[]>(() => {
    const folios = new Set<string>()
    for (const op of operacionesBase.value) {
        const folio = String(op.FolioPoliza ?? '').trim()
        if (folio !== '') folios.add(folio)
    }
    return Array.from(folios).sort((a, b) => a.localeCompare(b, 'es', { numeric: true }))
})

const operacionesFiltradas = computed<any[]>(() => {
    const tokens = normalizarTexto(filtroBusqueda.value).trim().split(/\s+/).filter(Boolean)
    const primaMin = filtroPrimaMin.value === '' ? NaN : parseFloat(filtroPrimaMin.value)
    const primaMax = filtroPrimaMax.value === '' ? NaN : parseFloat(filtroPrimaMax.value)

    return operacionesBase.value.filter((op) => {
        if (tokens.length) {
            const texto = normalizarTexto([
                op.FolioPoliza,
                op.FolioEndoso,
                op.NombreAgente,
                op.APaternoAgente,
                op.AMaternoAgente,
                op.RazonSocialAgente,
                op.RFCAgente,
                op.CURPAgente,
                op.IDMoneda,
            ].join(' '))
            if (!tokens.every((token) => texto.includes(token))) return false
        }

        if (!fechaEnRango(op.FechaEmision, filtroFechaDesde.value, filtroFechaHasta.value)) return false

        if (filtroTipoOperacion.value && clasificarOperacion(op) !== filtroTipoOperacion.value) return false

        if (filtroMoneda.value && String(op.IDMoneda ?? '') !== filtroMoneda.value) return false

        if (filtroFormaPago.value) {
            const formaOperacion = String(op.IDFormaPago ?? '')
            const formasPagos = (op.pagos ?? []).map((p: any) => String(p.IDFormaPago ?? ''))
            if (formaOperacion !== filtroFormaPago.value && !formasPagos.includes(filtroFormaPago.value)) return false
        }

        if (filtroTipoPago.value && String(op.IDTipoPago ?? '') !== filtroTipoPago.value) return false

        if (filtroEstado.value === 'canceladas' && !esCancelacionEndoso(op)) return false
        if (filtroEstado.value === 'vigentes' && esCancelacionEndoso(op)) return false

        const prima = parseFloat(op.PrimaTotal) || 0
        if (!isNaN(primaMin) && prima < primaMin) return false
        if (!isNaN(primaMax) && prima > primaMax) return false

        if (filtroPoliza.value && String(op.FolioPoliza ?? '') !== filtroPoliza.value) return false

        if (filtroSoloConPagos.value && !(op.pagos && op.pagos.length)) return false

        return true
    })
})

/** Re-agrupa las operaciones filtradas por póliza (misma estructura que el backend). */
const polizasFiltradas = computed<any[]>(() => {
    const grupos = new Map<string, any>()

    for (const op of operacionesFiltradas.value) {
        const folio = String(op.FolioPoliza ?? '')
        if (!grupos.has(folio)) {
            grupos.set(folio, {
                folio_poliza: folio,
                operaciones: [] as any[],
                endosos: [] as any[],
                todas_operaciones: [] as any[],
                balance_prima_total: 0,
            })
        }
        const grupo = grupos.get(folio)
        grupo.todas_operaciones.push(op)
        grupo.balance_prima_total += parseFloat(op.PrimaTotal) || 0
        if (!op.FolioEndoso || String(op.FolioEndoso).trim() === '') {
            grupo.operaciones.push(op)
        } else {
            grupo.endosos.push(op)
        }
    }

    return Array.from(grupos.values()).map((grupo) => ({
        ...grupo,
        balance_prima_total: Math.round(grupo.balance_prima_total * 100) / 100,
    }))
})

const hasFiltrosActivos = computed<boolean>(() => (
    filtroBusqueda.value.trim() !== ''
    || filtroFechaDesde.value !== ''
    || filtroFechaHasta.value !== ''
    || filtroTipoOperacion.value !== ''
    || filtroMoneda.value !== ''
    || filtroFormaPago.value !== ''
    || filtroTipoPago.value !== ''
    || filtroEstado.value !== ''
    || filtroPrimaMin.value !== ''
    || filtroPrimaMax.value !== ''
    || filtroPoliza.value !== ''
    || filtroSoloConPagos.value
))

function limpiarFiltros() {
    filtroBusqueda.value = ''
    filtroFechaDesde.value = ''
    filtroFechaHasta.value = ''
    filtroTipoOperacion.value = ''
    filtroMoneda.value = ''
    filtroFormaPago.value = ''
    filtroTipoPago.value = ''
    filtroEstado.value = ''
    filtroPrimaMin.value = ''
    filtroPrimaMax.value = ''
    filtroPoliza.value = ''
    filtroSoloConPagos.value = false
}

/** Exporta TODAS las operaciones del cliente (no solo las filtradas). */
function exportarOperacionesCsv() {
    window.location.href = `/clientes/${props.cliente.IDCliente}/operaciones/exportar`
}

/** Exporta las estadísticas del cliente, con una fila por moneda. */
function exportarEstadisticasCsv() {
    window.location.href = `/clientes/${props.cliente.IDCliente}/estadisticas/exportar`
}

// ============================================================================
// INDICADORES DEL CLIENTE (promedios y sumas de primas)
// ============================================================================
const indicadores = computed<any>(() => props.indicadores ?? {
    total_operaciones: 0,
    moneda_principal: null,
    por_moneda: {},
})

const catalogos = computed<any>(() => props.catalogos ?? { monedas: [], formasPago: [], tiposPago: [] })

const monedasIndicadores = computed<any[]>(() => Object.values(indicadores.value.por_moneda ?? {}))

const monedaSeleccionada = ref('')

watchEffect(() => {
    if (!monedaSeleccionada.value && indicadores.value.moneda_principal) {
        monedaSeleccionada.value = indicadores.value.moneda_principal
    }
})

const indicadoresMoneda = computed<any>(() => indicadores.value.por_moneda?.[monedaSeleccionada.value] ?? null)

function formatMoneda(value?: number | string | null, moneda: string = 'MXN'): string {
    const numero = Number(value)
    if (value === null || value === undefined || isNaN(numero)) return '—'
    if (!moneda || moneda === 'SIN MONEDA') {
        return new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2 }).format(numero)
    }
    try {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: moneda,
        }).format(numero)
    } catch {
        return new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2 }).format(numero)
    }
}

/** Tarjetas de primas del apartado "Detalles cliente". */
const tarjetasPrimas = computed(() => {
    const ind = indicadoresMoneda.value
    if (!ind) return []

    const verde = 'text-emerald-700 dark:text-emerald-300'
    const rojo = 'text-red-700 dark:text-red-300'
    const neutro = 'text-gray-900 dark:text-neutral-100'

    return [
        {
            titulo: 'Prima total acumulada (neta)',
            valor: formatMoneda(ind.suma_prima_total, ind.moneda),
            ayuda: 'Suma de las emisiones y de todos los endosos (aumento, disminución y cancelación).',
            color: ind.suma_prima_total < 0 ? rojo : neutro,
        },
        {
            titulo: 'Prima promedio por operación (neta)',
            valor: formatMoneda(ind.promedio_prima_total, ind.moneda),
            ayuda: 'Prima total acumulada dividida entre el número de operaciones del cliente.',
            color: ind.promedio_prima_total < 0 ? rojo : neutro,
        },
        {
            titulo: 'Prima total modal',
            valor: ind.moda_prima_total === null || ind.moda_prima_total === undefined
                ? 'Sin moda'
                : formatMoneda(ind.moda_prima_total, ind.moneda),
            ayuda: ind.moda_prima_total === null || ind.moda_prima_total === undefined
                ? 'Ningún importe de prima se repite entre las operaciones del cliente.'
                : `${ind.moda_prima_total_frecuencia} operación(es) comparten esta prima. En caso de empate se muestra el importe menor.`,
            color: neutro,
        },
        {
            titulo: 'Prima promedio de emisiones',
            valor: formatMoneda(ind.promedio_prima_emisiones, ind.moneda),
            ayuda: `Promedio de las ${ind.total_emisiones} póliza(s) emitida(s).`,
            color: neutro,
        },
        {
            titulo: 'Prima promedio de endosos de aumento',
            valor: formatMoneda(ind.promedio_aumentos, ind.moneda),
            ayuda: `Promedio de los ${ind.total_aumentos} endoso(s) de aumento (prima positiva).`,
            color: ind.total_aumentos ? verde : neutro,
        },
        {
            titulo: 'Prima promedio de endosos de disminución',
            valor: formatMoneda(ind.promedio_disminuciones, ind.moneda),
            ayuda: `Promedio de los ${ind.total_disminuciones} endoso(s) de disminución (prima negativa).`,
            color: ind.total_disminuciones ? rojo : neutro,
        },
        {
            titulo: 'Balance promedio por póliza',
            valor: formatMoneda(ind.promedio_balance_por_poliza, ind.moneda),
            ayuda: `Balance neto (emisión + endosos) de las ${ind.num_polizas} póliza(s) del cliente.`,
            color: ind.promedio_balance_por_poliza < 0 ? rojo : neutro,
        },
    ]
})

/** Tarjetas de sumas y pagos. */
const tarjetasSumas = computed(() => {
    const ind = indicadoresMoneda.value
    if (!ind) return []

    return [
        {
            titulo: 'Suma de aumentos',
            valor: formatMoneda(ind.suma_aumentos, ind.moneda),
            ayuda: `${ind.total_aumentos} endoso(s) de aumento.`,
            color: 'text-emerald-700 dark:text-emerald-300',
        },
        {
            titulo: 'Suma de disminuciones',
            valor: formatMoneda(ind.suma_disminuciones, ind.moneda),
            ayuda: `${ind.total_disminuciones} endoso(s) de disminución.`,
            color: 'text-red-700 dark:text-red-300',
        },
        {
            titulo: 'Prima de cancelaciones (espejo)',
            valor: formatMoneda(ind.suma_prima_cancelaciones, ind.moneda),
            ayuda: 'Prima negativa de los endosos de cancelación: deja el balance de la póliza en cero.',
            color: 'text-red-700 dark:text-red-300',
        },
        {
            titulo: 'Gastos de emisión acumulados',
            valor: formatMoneda(ind.suma_gastos_emision, ind.moneda),
            ayuda: 'Suma de los gastos de emisión de las operaciones.',
            color: 'text-gray-900 dark:text-neutral-100',
        },
        {
            titulo: 'Total pagado',
            valor: formatMoneda(ind.total_pagado, ind.moneda),
            ayuda: `${ind.num_pagos} pago(s) registrado(s).`,
            color: 'text-gray-900 dark:text-neutral-100',
        },
        {
            titulo: 'Saldo pendiente',
            valor: formatMoneda(ind.saldo_pendiente, ind.moneda),
            ayuda: 'Prima total acumulada menos el total pagado.',
            color: ind.saldo_pendiente > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-900 dark:text-neutral-100',
        },
    ]
})

/** Resumen de volumen de operaciones. */
const tarjetasVolumen = computed(() => {
    const ind = indicadoresMoneda.value
    if (!ind) return []

    return [
        { titulo: 'Operaciones', valor: ind.total_operaciones },
        { titulo: 'Emisiones', valor: ind.total_emisiones },
        { titulo: 'Endosos de aumento', valor: ind.total_aumentos },
        { titulo: 'Endosos de disminución', valor: ind.total_disminuciones },
        { titulo: 'Endosos de cancelación', valor: ind.total_cancelaciones },
        { titulo: 'Pólizas', valor: ind.num_polizas },
        { titulo: 'Pagos', valor: ind.num_pagos },
        { titulo: 'Operaciones canceladas', valor: ind.total_operaciones_canceladas },
    ]
})

// --- Opciones de los filtros a partir de los catálogos y del propio cliente ---
const opcionesMoneda = computed(() => {
    const nombres: Record<string, string> = {}
    for (const m of catalogos.value.monedas ?? []) nombres[String(m.IDMoneda)] = m.Moneda

    const usadas = Array.from(new Set(
        operacionesBase.value.map((op) => String(op.IDMoneda ?? '').trim()).filter(Boolean)
    ))

    const prioridad = (id: string) => (id === 'MXN' ? 0 : id === 'USD' ? 1 : 2)
    usadas.sort((a, b) => prioridad(a) - prioridad(b) || a.localeCompare(b))

    return usadas.map((id) => ({ value: id, label: nombres[id] ? `${id} — ${nombres[id]}` : id }))
})

const opcionesFormaPago = computed(() => {
    const nombres: Record<string, string> = {}
    for (const f of catalogos.value.formasPago ?? []) nombres[String(f.IDFormaPago)] = f.FormaPago

    const usadas = new Set<string>()
    for (const op of operacionesBase.value) {
        const id = String(op.IDFormaPago ?? '').trim()
        if (id !== '') usadas.add(id)
        for (const p of op.pagos ?? []) {
            const pid = String(p.IDFormaPago ?? '').trim()
            if (pid !== '') usadas.add(pid)
        }
    }

    return Array.from(usadas)
        .map((id) => ({ value: id, label: nombres[id] ?? `Forma de pago ${id}` }))
        .sort((a, b) => a.label.localeCompare(b.label, 'es'))
})

const opcionesTipoPago = computed(() => {
    const nombres: Record<string, string> = {}
    for (const t of catalogos.value.tiposPago ?? []) nombres[String(t.IDTipoPago)] = t.TipoPago

    const usadas = new Set<string>()
    for (const op of operacionesBase.value) {
        const id = String(op.IDTipoPago ?? '').trim()
        if (id !== '') usadas.add(id)
    }

    return Array.from(usadas)
        .map((id) => ({ value: id, label: nombres[id] ?? `Tipo de pago ${id}` }))
        .sort((a, b) => a.label.localeCompare(b.label, 'es'))
})
</script>

<template>

    <Head :title="`Detalle de ${fullName}`" />
    <AppLayout :breadcrumbs="breadcrumbs" container-size="full">
        <Transition appear name="fade-in">
            <div class="w-full">
                <!-- Botón de Back -->
                <div class="mb-4">
                  <button
                    @click="goBack"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-600 shadow-sm transition hover:bg-gray-50 hover:text-blue-700 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:hover:text-blue-300"
                  >
                    <ArrowLeft class="h-4 w-4"/>
                    <span>Regresar</span>
                  </button>
                </div>
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">
            <!-- Columna Izquierda: Navegación y Resumen -->
            <aside class="lg:col-span-1 lg:sticky lg:top-24 h-fit">
                <!-- Tarjeta de Héroe del Cliente -->
                <div
                    class="rounded-xl border border-gray-200/80 bg-white/70 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 ring-1 ring-gray-200 dark:bg-neutral-800 dark:ring-neutral-700">
                            <component :is="personaIcon" class="h-6 w-6 text-gray-600 dark:text-neutral-300" />
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ fullName }}</h1>
                            <p class="text-xs text-gray-500 dark:text-neutral-400">{{ personaTipo }}</p>

                        </div>
                    </div>
                    <div class="mt-4 space-y-1 text-xs">
                        <div class="flex items-center gap-2 text-gray-600 dark:text-neutral-300">
                            <span class="w-15 font-semibold">ID Cliente </span>
                            <span class="font-mono">{{ props.cliente.IDCliente }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-gray-600 dark:text-neutral-300">
                            <span class="w-15 font-semibold">Estatus</span>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 font-semibold"
                                :class="clienteEstatusClass"
                            >
                                {{ clienteEstatusLabel }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-gray-600 dark:text-neutral-300">
                            <span class="w-15 font-semibold">RFC</span>
                            <span v-if="hasRFC" class="font-mono">{{ props.cliente.RFC }}</span>
                            <span v-else class="inline-flex items-center gap-1 rounded bg-yellow-100 text-yellow-800 px-2 py-0.5 font-semibold dark:bg-yellow-900/40 dark:text-yellow-200">No cuenta con RFC</span>
                        </div>
                        <div class="flex items-start gap-2 text-gray-600 dark:text-neutral-300">
                            <span class="w-15 font-semibold shrink-0 pt-0.5">Sistemas</span>
                            <div class="flex flex-wrap gap-1">
                                <span v-if="sistemasDelCliente.length === 0"
                                    class="inline-flex items-center rounded-full bg-indigo-100 text-indigo-800 px-2 py-0.5 font-semibold dark:bg-indigo-900/40 dark:text-indigo-200">
                                    PLD (Sistema Local)
                                </span>
                                <span v-for="sis in sistemasDelCliente" :key="sis.IDOrigenSistema"
                                    class="inline-flex items-center rounded-full bg-indigo-100 text-indigo-800 px-2 py-0.5 font-semibold dark:bg-indigo-900/40 dark:text-indigo-200">
                                    {{ sis.sistema?.Sistema ?? 'ID ' + sis.IDSistema }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <!-- Botón Activar cliente oculto: el cliente siempre permanece activo -->
                    <div v-if="false" class="mt-3">
                        <button
                            type="button"
                            @click="abrirModalActivarCliente"
                            :disabled="activandoCliente"
                            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400"
                        >
                            <span
                                v-if="activandoCliente"
                                class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"
                            ></span>
                            {{ activandoCliente ? 'Activando...' : 'Activar cliente' }}
                        </button>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
                            :class="isPpe ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200' : 'bg-gray-100 text-gray-700 dark:bg-neutral-800 dark:text-neutral-200'">
                            <BadgeCheck v-if="!isPpe" class="h-3.5 w-3.5" />
                            <AlertTriangle v-else class="h-3.5 w-3.5" />
                            {{ isPpe ? 'Es PPE' : 'No es PPE' }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
                            :class="onBlacklist ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200' : 'bg-gray-100 text-gray-700 dark:bg-neutral-800 dark:text-neutral-200'">
                            <BookX v-if="onBlacklist" class="h-3.5 w-3.5" />
                            <BadgeCheck v-else class="h-3.5 w-3.5" />
                            {{ onBlacklist ? 'En Listas' : 'Sin coincidencias' }}
                        </span>
                        <span
                            v-if="esAltoRiesgo"
                            class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-1 font-semibold text-red-800 dark:bg-red-900/40 dark:text-red-200"
                        >
                            <ShieldAlert class="h-3.5 w-3.5" />
                            Alto riesgo
                        </span>
                        <span
                          v-if="isExtranjero"
                          class="inline-flex items-center gap-1.5 rounded-full bg-pink-100 text-pink-800 px-2.5 py-1 font-medium dark:bg-pink-900/40 dark:text-pink-200"
                        >
                          <GlobeIcon class="h-3.5 w-3.5" />
                          Extranjero
                        </span>
                    </div>
                </div>

                <!-- Navegación Vertical -->
                <nav class="mt-6 space-y-1">
                    <button v-for="item in navItems" :key="item.id" @click="activeTab = item.id"
                        class="group flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-all duration-200 ease-out"
                        :class="activeTab === item.id
                                ? 'bg-blue-50 text-blue-700 shadow-sm shadow-blue-500/10 dark:bg-blue-500/10 dark:text-blue-300'
                                : 'text-gray-600 hover:bg-gray-100/80 hover:text-gray-800 dark:text-neutral-400 dark:hover:bg-neutral-800/60 dark:hover:text-neutral-200'
                            ">
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                        <span>{{ item.label }}</span>
                        <span v-if="item.id === 'alertas' && hasAlerts"
                            class="ml-auto h-2.5 w-2.5 rounded-full bg-orange-500"></span>
                    </button>
                </nav>
            </aside>

            <!-- Columna Derecha: Contenido Detallado -->
            <main class="lg:col-span-3">
                <Transition name="fade" mode="out-in">
                    <div :key="activeTab">
                        <!-- RESUMEN -->
                        <section v-if="activeTab === 'resumen'" class="space-y-6">
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Resumen del Cliente</h2>

                            <!-- Nueva Sección: Cliente fuera de categoría -->
                            <div v-if="isOutCategory" class="rounded-xl border-2 border-dashed border-red-400 bg-red-50/60 p-6 text-center flex flex-col items-center gap-2 dark:border-red-700 dark:bg-red-900/30">
                              <AlertCircle class="w-8 h-8 text-red-500 mb-1" />
                              <h3 class="font-bold text-red-800 dark:text-red-200 text-lg mb-1">Cliente fuera de categoría</h3>
                              <div class="text-sm text-red-700 dark:text-red-200">
                                <span>Este cliente está fuera de categoría por la/s razón/es:</span>
                                <ul class="mt-2 list-disc list-inside text-left mx-auto w-fit">
                                  <li v-for="reason in outCategoryReasons" :key="reason">{{ reason }}</li>
                                </ul>
                              </div>
                            </div>
                            <!-- /Nueva Sección -->

                            <!-- Cliente de alto riesgo -->
                            <div v-if="esAltoRiesgo" class="rounded-xl border-2 border-dashed border-red-400 bg-red-50/60 p-6 text-center flex flex-col items-center gap-2 dark:border-red-700 dark:bg-red-900/30">
                              <ShieldAlert class="w-8 h-8 text-red-500 mb-1" />
                              <h3 class="font-bold text-red-800 dark:text-red-200 text-lg mb-1">Cliente de alto riesgo</h3>
                              <div class="text-sm text-red-700 dark:text-red-200">
                                <span>Se detectó un perfil de alto riesgo por:</span>
                                <ul v-if="factoresRiesgo.length" class="mt-2 list-disc list-inside text-left mx-auto w-fit">
                                  <li v-for="factor in factoresRiesgo" :key="factor">{{ factor }}</li>
                                </ul>
                                <p v-else class="mt-2">Perfil de alto riesgo detectado al momento de su registro.</p>
                              </div>
                            </div>
                            <!-- /Cliente de alto riesgo -->

                             <!-- Alertas Activas -->
                            <div v-if="hasAlerts">
                                <h3 class="mb-3 text-lg font-semibold text-orange-800 dark:text-orange-300">Alertas Recientes</h3>
                                <div class="space-y-4">
                                    <div v-for="alerta in props.alertas.slice(0, 3)" :key="alerta.IDRegistroAlerta" class="rounded-lg border border-orange-200 bg-orange-50/80 p-4 dark:border-orange-500/20 dark:bg-orange-900/20">
                                         <p class="font-semibold text-orange-800 dark:text-orange-200">{{ alerta.Patron }}</p>
                                         <p class="mt-1 text-sm text-gray-600 dark:text-neutral-300">{{ alerta.Descripcion }}</p>
                                         <p class="mt-2 text-xs text-gray-500 dark:text-neutral-400">Detectado: {{ formatDate(alerta.FechaDeteccion) }}</p>
                                         <!-- Botón "Ver detalles" para cada alerta -->
                                         <Link
                                            :href="`/alertas/${alerta.IDRegistroAlerta}/detalles`"
                                            class="mt-3 inline-flex items-center gap-2 rounded bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                            Ver detalles
                                         </Link>
                                    </div>
                                    <Link v-if="props.alertas.length > 3" href="#" @click.prevent="activeTab = 'alertas'" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                                        Ver todas las {{ props.alertas.length }} alertas &rarr;
                                    </Link>
                                </div>
                            </div>

                             <!-- Coincidencias en Listas -->
                             <div v-if="onBlacklist">
                                <h3 class="mb-3 text-lg font-semibold text-rose-800 dark:text-rose-300">Coincidencias en Listas</h3>
                                <div class="space-y-3">
                                    <div v-for="l in props.listasNegras" :key="l.IDRegistroListaCNSF" class="rounded-lg border border-rose-200 bg-rose-50/80 p-3 text-sm dark:border-rose-500/20 dark:bg-rose-900/20">
                                        <p class="font-semibold text-rose-800 dark:text-rose-300">CNSF: {{ l.Nombre }} ({{ l.RFC }})</p>
                                    </div>
                                    <div v-for="l in props.listasUIF" :key="l.IDRegistroListaUIF" class="rounded-lg border border-rose-200 bg-rose-50/80 p-3 text-sm dark:border-rose-500/20 dark:bg-rose-900/20">
                                         <p class="font-semibold text-rose-800 dark:text-rose-300">UIF: {{ l.Nombre }} ({{ l.RFC }})</p>
                                    </div>
                                </div>
                             </div>

                             <div v-if="!hasAlerts && !onBlacklist" class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/80 p-12 text-center dark:border-neutral-700 dark:bg-neutral-900/50">
                                 <CheckCircle class="h-12 w-12 text-green-500" />
                                 <h3 class="mt-4 text-lg font-semibold text-gray-800 dark:text-white">Todo en Orden</h3>
                                 <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">Este cliente no presenta alertas activas ni coincidencias en listas de observación.</p>
                             </div>
                        </section>

                        <!-- DATOS GENERALES -->
                        <section v-if="activeTab === 'info'" class="space-y-6">
                            <div class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-white">Identificación</h3>
                                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                    <div v-if="Number(props.cliente.IDTipoPersona) === 2"><dt class="font-medium text-gray-500 dark:text-neutral-400">Razón Social</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ props.cliente.RazonSocial }}</dd></div>
                                    <div v-if="Number(props.cliente.IDTipoPersona) === 1"><dt class="font-medium text-gray-500 dark:text-neutral-400">Nombre Completo</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ fullName }}</dd></div>
                                    <div>
                                        <dt class="font-medium text-gray-500 dark:text-neutral-400">RFC</dt>
                                        <dd class="mt-1 font-mono text-gray-900 dark:text-neutral-100">
                                            <span v-if="hasRFC">{{ props.cliente.RFC }}</span>
                                            <span v-else class="inline-flex items-center gap-1 rounded bg-yellow-100 text-yellow-800 px-2 py-0.5 font-semibold dark:bg-yellow-900/40 dark:text-yellow-200">No cuenta con RFC</span>
                                        </dd>
                                    </div>
                                    <div v-if="props.cliente.CURP"><dt class="font-medium text-gray-500 dark:text-neutral-400">CURP</dt><dd class="mt-1 font-mono text-gray-900 dark:text-neutral-100">{{ props.cliente.CURP }}</dd></div>
                                    <div v-if="props.cliente.FolioMercantil"><dt class="font-medium text-gray-500 dark:text-neutral-400">Folio Mercantil</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ props.cliente.FolioMercantil }}</dd></div>
                                    <div>
                                        <dt class="font-medium text-gray-500 dark:text-neutral-400">Fecha de Nacimiento/Constitución</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ formatDate(props.cliente.FechaNacimiento || props.cliente.FechaConstitucion) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-500 dark:text-neutral-400">Nacionalidad</dt>
                                        <dd class="mt-1 text-gray-900 dark:text-neutral-100">
                                            {{ props.cliente.IDNacionalidad }}
                                            <span v-if="isExtranjero" class="inline-flex items-center gap-1.5 rounded-full bg-pink-100 text-pink-800 px-2.5 py-1 font-medium text-xs ml-2 dark:bg-pink-900/40 dark:text-pink-200">
                                              <GlobeIcon class="h-3.5 w-3.5" /> Extranjero
                                            </span>
                                        </dd>
                                    </div>
                                    <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Estado de Nacimiento</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ props.cliente.IDEstadoNacimiento }}</dd></div>
                                </dl>
                            </div>
                             <div class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-white">Clasificación PLD</h3>
                                 <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                      <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Ocupación / Giro</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ props.cliente.IDOcupacionGiro }}</dd></div>
                                      <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Ingresos Estimados</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ formatCurrency(props.cliente.IngresosEstimados) }}</dd></div>
                                     <div>
                                         <dt class="font-medium text-gray-500 dark:text-neutral-400">Coincide en Listas de Observación</dt>
                                         <dd class="mt-1 font-semibold" :class="onBlacklist ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400'">
                                             {{ onBlacklist ? 'Sí' : 'No' }}
                                         </dd>
                                     </div>
                                     <div>
                                         <dt class="font-medium text-gray-500 dark:text-neutral-400">Es Persona Políticamente Expuesta</dt>
                                         <dd class="mt-1 font-semibold" :class="props.cliente.EsPPEActivo ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400'">{{ props.cliente.EsPPEActivo ? 'Sí' : 'No' }}</dd>
                                     </div>
                                     <div>
                                         <dt class="font-medium text-gray-500 dark:text-neutral-400">Estatus del cliente</dt>
                                         <dd class="mt-1">
                                             <span
                                                 class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                                 :class="clienteEstatusClass"
                                             >
                                                 {{ clienteEstatusLabel }}
                                             </span>
                                         </dd>
                                     </div>
                                     <div>
                                         <dt class="font-medium text-gray-500 dark:text-neutral-400">Perfil de Riesgo</dt>
                                         <dd class="mt-1 font-semibold" :class="esAltoRiesgo ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400'">
                                             {{ esAltoRiesgo ? 'Alto riesgo' : 'Sin alto riesgo' }}
                                         </dd>
                                     </div>
                                     <div v-if="props.cliente.Preguntas" class="sm:col-span-2 lg:col-span-3"><dt class="font-medium text-gray-500 dark:text-neutral-400">Notas / Perfil PLD</dt><dd class="mt-1 text-gray-900 dark:text-neutral-100">{{ props.cliente.Preguntas }}</dd></div>
                                 </dl>
                            </div>
                        </section>

                        <!-- DOMICILIOS -->
                        <section v-if="activeTab === 'dom'" class="space-y-4">
                             <div v-if="props.domicilios.length" v-for="(d, i) in props.domicilios" :key="d.IDDomicilio"
                                class="rounded-xl border border-gray-200/80 bg-white/70 p-5 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                <h3 class="font-semibold text-gray-800 dark:text-white">Domicilio {{ i + 1 }}</h3>
                                <address class="mt-2 text-sm not-italic text-gray-700 dark:text-neutral-300">
                                    {{ d.Calle }}, No. {{ d.NoExterior }} {{ d.NoInterior ? 'Int. ' + d.NoInterior : '' }}<br>
                                    Col. {{ d.Colonia }}, C.P. {{ d.CP }}<br>
                                    {{ d.IDEstado }}
                                </address>
                                <div v-if="d.Telefono" class="mt-2 text-sm text-gray-600 dark:text-neutral-400">
                                    <span class="font-medium">Tel:</span> {{ d.Telefono }}
                                </div>
                             </div>
                            <div v-else class="text-center text-gray-500 dark:text-neutral-400 italic py-10">Sin domicilios registrados.</div>
                        </section>

                        <!-- OPERACIONES -->
                        <section v-if="activeTab === 'op'" class="space-y-6">
                            <!-- Encabezado + exportar CSV -->
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Operaciones</h2>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                                        <template v-if="hasFiltrosActivos">
                                            Mostrando <span class="font-semibold text-gray-700 dark:text-neutral-200">{{ operacionesFiltradas.length }}</span>
                                            de {{ operacionesBase.length }} operación(es) del cliente.
                                        </template>
                                        <template v-else>
                                            {{ operacionesBase.length }} operación(es) registrada(s) en {{ polizasFiltradas.length }} póliza(s).
                                        </template>
                                    </p>
                                </div>
                                <button @click="exportarOperacionesCsv" type="button"
                                    :disabled="operacionesBase.length === 0"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm shadow-emerald-600/20 transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-emerald-700 hover:shadow-md hover:shadow-emerald-600/30 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100 dark:border-emerald-500 dark:bg-emerald-500 dark:shadow-black/20 dark:hover:bg-emerald-600 dark:hover:shadow-black/30 dark:ring-offset-neutral-950"
                                    :title="operacionesBase.length === 0 ? 'El cliente no tiene operaciones para exportar' : 'Exporta todas las operaciones del cliente a CSV'">
                                    <Download class="h-4 w-4" />
                                    Exportar CSV
                                </button>
                            </div>

                            <!-- Filtros -->
                            <div class="rounded-2xl border border-gray-200/70 bg-white/60 p-4 shadow-lg shadow-gray-200/40 backdrop-blur-lg transition-all duration-300 ease-out focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-500/10 dark:border-neutral-800/80 dark:bg-neutral-950/60 dark:shadow-2xl dark:shadow-black/20 dark:focus-within:border-blue-500/80 dark:focus-within:ring-blue-400/10">
                                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-500">
                                        <Filter class="h-3.5 w-3.5" />
                                        Filtros
                                    </p>
                                    <button v-if="hasFiltrosActivos" @click="limpiarFiltros" type="button"
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold text-gray-500 underline-offset-2 hover:text-gray-800 hover:underline dark:text-neutral-400 dark:hover:text-white">
                                        <X class="h-3 w-3" />
                                        Limpiar filtros
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                                    <!-- Búsqueda -->
                                    <div class="relative w-full lg:col-span-2 xl:col-span-2">
                                        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-gray-400 dark:text-neutral-500">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.5 15.5 20 20m-3-9a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                                            </svg>
                                        </span>
                                        <input v-model="filtroBusqueda" type="search" aria-label="Buscar operaciones"
                                            class="w-full rounded-lg border border-gray-300/80 bg-gray-50/50 py-2.5 pl-10 pr-3 text-sm text-gray-900 placeholder-gray-400 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:placeholder-neutral-500 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70"
                                            placeholder="Buscar por folio, endoso o agente…" />
                                    </div>

                                    <!-- Fecha desde -->
                                    <div class="flex flex-col gap-1.5">
                                        <label for="filtro-fecha-desde" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Emisión desde</label>
                                        <input id="filtro-fecha-desde" v-model="filtroFechaDesde" type="date"
                                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:shadow-none dark:hover:border-neutral-600 dark:focus:border-blue-400/80 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70" />
                                    </div>

                                    <!-- Fecha hasta -->
                                    <div class="flex flex-col gap-1.5">
                                        <label for="filtro-fecha-hasta" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Emisión hasta</label>
                                        <input id="filtro-fecha-hasta" v-model="filtroFechaHasta" type="date"
                                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:shadow-none dark:hover:border-neutral-600 dark:focus:border-blue-400/80 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70" />
                                    </div>

                                    <!-- Tipo de operación -->
                                    <div class="flex flex-col gap-1.5">
                                        <label for="filtro-tipo-operacion" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Tipo de operación</label>
                                        <select id="filtro-tipo-operacion" v-model="filtroTipoOperacion"
                                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:shadow-none dark:hover:border-neutral-600 dark:focus:border-blue-400/80 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="">Todos los tipos</option>
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="emision">Emisión</option>
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="aumento">Endoso de aumento</option>
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="disminucion">Endoso de disminución</option>
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="cancelacion">Cancelación</option>
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="sin_cambio">Endoso sin cambio</option>
                                        </select>
                                    </div>

                                    <!-- Moneda -->
                                    <div class="flex flex-col gap-1.5">
                                        <label for="filtro-moneda" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Moneda</label>
                                        <select id="filtro-moneda" v-model="filtroMoneda"
                                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:shadow-none dark:hover:border-neutral-600 dark:focus:border-blue-400/80 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="">Todas las monedas</option>
                                            <option v-for="opcion in opcionesMoneda" :key="opcion.value" class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Forma de pago -->
                                    <div class="flex flex-col gap-1.5">
                                        <label for="filtro-forma-pago" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Forma de pago</label>
                                        <select id="filtro-forma-pago" v-model="filtroFormaPago"
                                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:shadow-none dark:hover:border-neutral-600 dark:focus:border-blue-400/80 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="">Todas las formas</option>
                                            <option v-for="opcion in opcionesFormaPago" :key="opcion.value" class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Tipo de pago -->
                                    <div class="flex flex-col gap-1.5">
                                        <label for="filtro-tipo-pago" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Tipo de pago</label>
                                        <select id="filtro-tipo-pago" v-model="filtroTipoPago"
                                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:shadow-none dark:hover:border-neutral-600 dark:focus:border-blue-400/80 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="">Todos los tipos</option>
                                            <option v-for="opcion in opcionesTipoPago" :key="opcion.value" class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Estado -->
                                    <div class="flex flex-col gap-1.5">
                                        <label for="filtro-estado" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Estado</label>
                                        <select id="filtro-estado" v-model="filtroEstado"
                                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:shadow-none dark:hover:border-neutral-600 dark:focus:border-blue-400/80 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="">Todos los estados</option>
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="vigentes">Vigentes</option>
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="canceladas">Canceladas</option>
                                        </select>
                                    </div>

                                    <!-- Prima mínima -->
                                    <div class="flex flex-col gap-1.5">
                                        <label for="filtro-prima-min" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Prima mínima</label>
                                        <input id="filtro-prima-min" v-model="filtroPrimaMin" type="number" step="0.01" placeholder="Sin mínimo"
                                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:shadow-none dark:hover:border-neutral-600 dark:focus:border-blue-400/80 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70" />
                                    </div>

                                    <!-- Prima máxima -->
                                    <div class="flex flex-col gap-1.5">
                                        <label for="filtro-prima-max" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Prima máxima</label>
                                        <input id="filtro-prima-max" v-model="filtroPrimaMax" type="number" step="0.01" placeholder="Sin máximo"
                                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:shadow-none dark:hover:border-neutral-600 dark:focus:border-blue-400/80 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70" />
                                    </div>

                                    <!-- Póliza -->
                                    <div class="flex flex-col gap-1.5">
                                        <label for="filtro-poliza" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Póliza</label>
                                        <select id="filtro-poliza" v-model="filtroPoliza"
                                            class="rounded-lg border border-gray-300/80 bg-gray-50/50 px-3 py-2.5 text-xs text-gray-900 shadow-inner outline-none ring-blue-500/50 transition-all duration-150 hover:border-gray-400/90 focus:border-blue-500 focus:bg-white focus:ring-2 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:shadow-none dark:hover:border-neutral-600 dark:focus:border-blue-400/80 dark:focus:bg-neutral-900 dark:focus:ring-blue-500/70">
                                            <option class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" value="">Todas las pólizas</option>
                                            <option v-for="folio in foliosDisponibles" :key="folio" class="bg-white text-gray-900 dark:bg-neutral-900 dark:text-white" :value="folio">
                                                {{ folio }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Solo con pagos -->
                                    <div class="flex items-center gap-2 pt-6 lg:pt-0">
                                        <label for="filtro-solo-con-pagos" class="flex cursor-pointer items-center gap-2 text-xs text-gray-600 dark:text-neutral-300">
                                            <input id="filtro-solo-con-pagos" v-model="filtroSoloConPagos" type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-neutral-700 dark:bg-neutral-900" />
                                            Solo operaciones con pagos
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div v-if="polizasFiltradas.length" v-for="(poliza, pidx) in polizasFiltradas" :key="pidx"
                                class="rounded-xl border-2 border-blue-200/80 bg-white/70 shadow-lg shadow-blue-200/40 backdrop-blur-lg dark:border-blue-500/20 dark:bg-neutral-950/70 dark:shadow-black/20 overflow-hidden">

                                <!-- Cabecera de la Póliza -->
                                <div class="bg-gradient-to-r from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/10 px-5 py-4 border-b border-blue-200/60 dark:border-blue-500/20">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-lg font-bold text-blue-900 dark:text-blue-200">Póliza: {{ poliza.folio_poliza }}</h3>
                                            <p class="text-xs text-blue-600/70 dark:text-blue-300/70 mt-0.5">
                                                {{ poliza.todas_operaciones.length }} operación(es) registrada(s)
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs font-medium text-blue-600/70 dark:text-blue-300/70">Balance Prima Total</span>
                                            <p class="text-xl font-bold" :class="(poliza.balance_prima_total || 0) >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                                                {{ formatCurrency(poliza.balance_prima_total) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-5 space-y-4">

                                    <!-- Operaciones principales (sin endoso) -->
                                    <div v-if="poliza.operaciones.length">
                                        <h4 class="text-sm font-semibold text-gray-600 dark:text-neutral-400 uppercase tracking-wider mb-3">Operaciones</h4>
                                        <div v-for="op in poliza.operaciones" :key="op.IDOperacion"
                                            class="rounded-lg border border-gray-200/60 bg-gray-50/50 dark:border-neutral-700/60 dark:bg-neutral-900/30 p-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-mono text-gray-400 dark:text-neutral-500">#{{ op.IDOperacion }}</span>
                                                    <span v-if="tipoOperacionLabel(op, false)" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="tipoOperacionLabel(op, false)!.class">
                                                        {{ tipoOperacionLabel(op, false)!.label }}
                                                    </span>
                                                </div>
                                                <span class="text-xs text-gray-400 dark:text-neutral-500">{{ formatDate(op.FechaEmision) }}</span>
                                            </div>
                                            <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2">
                                                <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Prima Total</dt><dd class="mt-0.5 font-semibold" :class="(op.PrimaTotal || 0) >= 0 ? 'text-gray-900 dark:text-neutral-100' : 'text-red-600 dark:text-red-300'">{{ formatCurrency(op.PrimaTotal) }} {{ op.IDMoneda ? '(' + op.IDMoneda + ')' : '' }}</dd></div>
                                                <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Gastos Emisión</dt><dd class="mt-0.5 text-gray-700 dark:text-neutral-300">{{ formatCurrency(op.GastosEmision) }}</dd></div>
                                                <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Vigencia</dt><dd class="mt-0.5 text-gray-700 dark:text-neutral-300">{{ formatDate(op.FechaInicioVigencia) }} - {{ formatDate(op.FechaFinVigencia) }}</dd></div>
                                                <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Agente</dt><dd class="mt-0.5 text-gray-700 dark:text-neutral-300">{{ op.NombreAgente }} {{ op.APaternoAgente }}</dd></div>
                                            </dl>
                                            <!-- Pagos de esta operación -->
                                            <div v-if="op.pagos && op.pagos.length" class="mt-3 border-t border-gray-200/50 dark:border-neutral-700/50 pt-3">
                                                <h5 class="text-xs font-semibold text-gray-600 dark:text-neutral-400 mb-1">Pagos</h5>
                                                <ul class="space-y-1">
                                                    <li v-for="p in op.pagos" :key="p.IDOperacionPago" class="flex flex-wrap items-center justify-between text-xs bg-white/70 dark:bg-neutral-800/50 rounded px-2 py-1">
                                                        <span class="font-mono font-semibold text-gray-800 dark:text-neutral-200">{{ formatCurrency(p.Monto) }} {{ p.IDMoneda }}</span>
                                                        <span class="text-gray-500 dark:text-neutral-400">{{ formatDate(p.FechaPago) }} <span v-if="p.IDFormaPago" class="text-gray-400 dark:text-neutral-500">| FP: {{ p.IDFormaPago }}</span></span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div v-else class="mt-2 text-xs text-gray-400 dark:text-neutral-500 italic">Sin pagos registrados</div>
                                        </div>
                                    </div>

                                    <!-- Endosos -->
                                    <div v-if="poliza.endosos.length">
                                        <h4 class="text-sm font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                            Endosos
                                            <span class="text-xs font-normal normal-case text-amber-500">({{ poliza.endosos.length }})</span>
                                        </h4>
                                        <div class="space-y-3">
                                            <div v-for="endoso in poliza.endosos" :key="endoso.IDOperacion"
                                                class="rounded-lg border border-amber-200/60 bg-amber-50/40 dark:border-amber-500/20 dark:bg-amber-900/10 p-4">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xs font-mono text-gray-400 dark:text-neutral-500">#{{ endoso.IDOperacion }}</span>
                                                        <span class="text-sm font-bold text-amber-800 dark:text-amber-200">No. Endoso: {{ endoso.FolioEndoso }}</span>
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="tipoOperacionLabel(endoso, true)!.class">
                                                            {{ tipoOperacionLabel(endoso, true)!.label }}
                                                        </span>
                                                    </div>
                                                    <span class="text-xs text-gray-400 dark:text-neutral-500">{{ formatDate(endoso.FechaEmision) }}</span>
                                                </div>
                                                <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2">
                                                    <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Prima Total</dt><dd class="mt-0.5 font-semibold" :class="(endoso.PrimaTotal || 0) >= 0 ? 'text-gray-900 dark:text-neutral-100' : 'text-red-600 dark:text-red-300'">{{ formatCurrency(endoso.PrimaTotal) }} {{ endoso.IDMoneda ? '(' + endoso.IDMoneda + ')' : '' }}</dd></div>
                                                    <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Gastos Emisión</dt><dd class="mt-0.5 text-gray-700 dark:text-neutral-300">{{ formatCurrency((parseFloat(endoso.PrimaTotal) || 0) < 0 ? -(Math.abs(parseFloat(endoso.GastosEmision) || 0)) : endoso.GastosEmision) }}</dd></div>
                                                    <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Vigencia</dt><dd class="mt-0.5 text-gray-700 dark:text-neutral-300">{{ formatDate(endoso.FechaInicioVigencia) }} - {{ formatDate(endoso.FechaFinVigencia) }}</dd></div>
                                                    <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Agente</dt><dd class="mt-0.5 text-gray-700 dark:text-neutral-300">{{ endoso.NombreAgente }} {{ endoso.APaternoAgente }}</dd></div>
                                                </dl>
                                                <!-- Pagos de este endoso (solo si no es cancelación) -->
                                                <template v-if="!esCancelacionEndoso(endoso)">
                                                    <div v-if="endoso.pagos && endoso.pagos.length" class="mt-3 border-t border-amber-200/50 dark:border-amber-500/20 pt-3">
                                                        <h5 class="text-xs font-semibold text-amber-700 dark:text-amber-400 mb-1">Pagos</h5>
                                                        <ul class="space-y-1">
                                                            <li v-for="p in endoso.pagos" :key="p.IDOperacionPago" class="flex flex-wrap items-center justify-between text-xs bg-white/70 dark:bg-neutral-800/50 rounded px-2 py-1">
                                                                <span class="font-mono font-semibold text-gray-800 dark:text-neutral-200">{{ formatCurrency(p.Monto) }} {{ p.IDMoneda }}</span>
                                                                <span class="text-gray-500 dark:text-neutral-400">{{ formatDate(p.FechaPago) }} <span v-if="p.IDFormaPago" class="text-gray-400 dark:text-neutral-500">| FP: {{ p.IDFormaPago }}</span></span>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <div v-else class="mt-2 text-xs text-gray-400 dark:text-neutral-500 italic">Sin pagos registrados</div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sin operaciones principales ni endosos (caso raro) -->
                                    <div v-if="!poliza.operaciones.length && !poliza.endosos.length" class="text-center text-gray-400 dark:text-neutral-500 italic py-4">
                                        Sin operaciones en esta póliza.
                                    </div>
                                </div>
                             </div>
                             <div v-else class="rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/60 py-10 text-center dark:border-neutral-700 dark:bg-neutral-900/40">
                                <p class="text-gray-500 dark:text-neutral-400 italic">
                                    <template v-if="hasFiltrosActivos">Sin operaciones que coincidan con los filtros aplicados.</template>
                                    <template v-else>Sin operaciones registradas.</template>
                                </p>
                                <button v-if="hasFiltrosActivos" @click="limpiarFiltros" type="button"
                                    class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 hover:text-blue-700 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:hover:text-blue-300">
                                    <X class="h-3.5 w-3.5" />
                                    Limpiar filtros
                                </button>
                            </div>
                        </section>

                        <!-- ESTADÍSTICAS CLIENTE -->
                        <section v-if="activeTab === 'detalles'" class="space-y-6">
                            <div class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Estadísticas cliente</h2>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                                            Promedios, sumas y moda de las primas de todas las operaciones del cliente.
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <!-- Selector de moneda -->
                                        <div v-if="monedasIndicadores.length > 1" class="flex flex-wrap items-center gap-1 rounded-lg border border-gray-200/80 bg-gray-50/70 p-1 dark:border-neutral-800 dark:bg-neutral-900/60">
                                            <button v-for="m in monedasIndicadores" :key="m.moneda" @click="monedaSeleccionada = m.moneda" type="button"
                                                class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                                                :class="monedaSeleccionada === m.moneda
                                                        ? 'bg-blue-600 text-white shadow-sm dark:bg-blue-500'
                                                        : 'text-gray-600 hover:bg-gray-200/70 dark:text-neutral-300 dark:hover:bg-neutral-800'">
                                                {{ m.moneda }}
                                                <span class="ml-1 text-[10px] font-normal opacity-80">({{ m.total_operaciones }})</span>
                                            </button>
                                        </div>
                                        <button @click="exportarEstadisticasCsv" type="button"
                                            :disabled="operacionesBase.length === 0"
                                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm shadow-emerald-600/20 transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-emerald-700 hover:shadow-md hover:shadow-emerald-600/30 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100 dark:border-emerald-500 dark:bg-emerald-500 dark:shadow-black/20 dark:hover:bg-emerald-600 dark:hover:shadow-black/30 dark:ring-offset-neutral-950"
                                            :title="operacionesBase.length === 0 ? 'El cliente no tiene operaciones para exportar' : 'Exporta las estadísticas de todas las monedas del cliente a CSV'">
                                            <Download class="h-4 w-4" />
                                            Exportar CSV
                                        </button>
                                    </div>
                                </div>

                                <div v-if="!indicadoresMoneda" class="mt-6 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/60 py-10 text-center text-sm text-gray-500 italic dark:border-neutral-700 dark:bg-neutral-900/40 dark:text-neutral-400">
                                    Este cliente no tiene operaciones registradas, por lo que no hay indicadores que mostrar.
                                </div>

                                <template v-else>
                                    <!-- Primas -->
                                    <div class="mt-6">
                                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-600 dark:text-neutral-400">Primas</h3>
                                        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                            <div v-for="tarjeta in tarjetasPrimas" :key="tarjeta.titulo"
                                                class="rounded-lg border border-gray-200/70 bg-gray-50/50 p-4 dark:border-neutral-700/60 dark:bg-neutral-900/30">
                                                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">{{ tarjeta.titulo }}</p>
                                                <p class="mt-1 text-xl font-bold" :class="tarjeta.color">{{ tarjeta.valor }}</p>
                                                <p class="mt-1 text-[11px] leading-snug text-gray-400 dark:text-neutral-500">{{ tarjeta.ayuda }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sumas y pagos -->
                                    <div class="mt-6">
                                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-600 dark:text-neutral-400">Sumas y pagos</h3>
                                        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                            <div v-for="tarjeta in tarjetasSumas" :key="tarjeta.titulo"
                                                class="rounded-lg border border-gray-200/70 bg-gray-50/50 p-4 dark:border-neutral-700/60 dark:bg-neutral-900/30">
                                                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">{{ tarjeta.titulo }}</p>
                                                <p class="mt-1 text-xl font-bold" :class="tarjeta.color">{{ tarjeta.valor }}</p>
                                                <p class="mt-1 text-[11px] leading-snug text-gray-400 dark:text-neutral-500">{{ tarjeta.ayuda }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Volumen -->
                                    <div class="mt-6">
                                        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-600 dark:text-neutral-400">Volumen de operaciones</h3>
                                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                            <div v-for="tarjeta in tarjetasVolumen" :key="tarjeta.titulo"
                                                class="rounded-lg border border-gray-200/70 bg-white/70 p-3 text-center dark:border-neutral-700/60 dark:bg-neutral-900/30">
                                                <p class="text-2xl font-bold text-gray-800 dark:text-neutral-100">{{ tarjeta.valor }}</p>
                                                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-neutral-400">{{ tarjeta.titulo }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Nota de cálculo -->
                            <div class="rounded-xl border border-blue-200/80 bg-blue-50/60 p-5 dark:border-blue-500/20 dark:bg-blue-900/20">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-blue-900 dark:text-blue-200">
                                    <Gauge class="h-4 w-4" />
                                    Cómo se calculan los indicadores
                                </h3>
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-blue-900/80 dark:text-blue-200/80">
                                    <li>La <b>prima total acumulada</b> suma la prima de todas las operaciones del cliente: emisiones más endosos.</li>
                                    <li>Los <b>endosos de aumento</b> traen prima positiva y los de <b>disminución</b> prima negativa, por lo que se suman y se restan del balance de la póliza.</li>
                                    <li>El <b>endoso de cancelación</b> se autocorrige con la prima espejo (negación del historial previo de la póliza), por lo que una póliza cancelada queda con balance en cero.</li>
                                    <li>El <b>promedio por operación</b> es la prima total acumulada entre el número de operaciones; los promedios por tipo (emisiones, aumentos, disminuciones) se muestran por separado para que los endosos no distorsionen la lectura.</li>
                                    <li>La <b>prima total modal</b> es el importe de prima que más se repite entre todas las operaciones de la moneda; si hay empate se muestra el importe menor y, si ningún importe se repite, se indica “Sin moda”.</li>
                                    <li>Los indicadores se calculan <b>por moneda</b>: PrimaTotal depende de la moneda de la operación y no se guarda tipo de cambio, por lo que no se suman importes en MXN y USD.</li>
                                </ul>
                            </div>
                        </section>

                        <!-- ALERTAS -->
                        <section v-if="activeTab === 'alertas'" class="space-y-4">
                            <div v-if="props.alertas.length" v-for="alerta in props.alertas" :key="alerta.IDRegistroAlerta"
                                class="rounded-xl p-5 shadow-lg backdrop-blur-lg"
                                :class="alerta.Estatus==='Generado' ? 'border border-orange-200/80 bg-orange-50/70 shadow-orange-200/40 dark:border-orange-500/20 dark:bg-orange-900/20 dark:shadow-black/20' : 'border border-green-200/80 bg-green-50/70 shadow-green-200/40 dark:border-green-500/20 dark:bg-green-900/20 dark:shadow-black/20'">
                                <div class="flex items-start justify-between">
                                    <h3 class="text-base font-semibold" :class="alerta.Estatus==='Generado' ? 'text-orange-800 dark:text-orange-200' : 'text-green-800 dark:text-green-200'">Patrón: {{ alerta.Patron }}</h3>
                                    <span class="rounded-full px-3 py-1 text-xs font-medium" :class="alerta.Estatus==='Generado' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/50' : 'bg-green-100 text-green-800 dark:bg-green-900/50'">{{ alerta.Estatus }}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-neutral-300">{{ alerta.Descripcion }}</p>
                                <div class="mt-4 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-neutral-700/50 dark:text-neutral-400 space-y-1">
                                    <p><b>Razones:</b> {{ alerta.Razones }}</p>
                                    <p><b>Monto:</b> {{ formatCurrency(alerta.MontoOperacion) }} | <b>Póliza:</b> {{ alerta.Poliza }}</p>
                                    <p><b>Fecha Detección:</b> {{ formatDate(alerta.FechaDeteccion) }} {{ alerta.HoraDeteccion }}</p>
                                </div>
                                <!-- Botón "Ver detalles" por cada alerta -->
                                <div class="mt-4">
                                    <Link
                                        :href="`/alertas/${alerta.IDRegistroAlerta}/detalles`"
                                        class="inline-flex items-center gap-2 rounded bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                        Ver detalles
                                    </Link>
                                </div>
                                <div v-if="alerta.Evidencias" class="mt-3">
                                    <b class="text-xs font-medium text-gray-600 dark:text-neutral-300">Evidencias:</b>

                                    <!-- Caso especial: Mostrar visual para JSON de pagos fraccionados/acumulados -->
                                    <template v-if="getPagosFraccionadosVisual(alerta.Evidencias)">
                                        <div class="mt-3 border border-orange-200 bg-orange-50/60 rounded-lg p-4 dark:border-orange-500/20 dark:bg-orange-900/20">
                                            <div class="flex flex-wrap gap-6">
                                                <div class="flex-1 min-w-[170px]">
                                                    <div class="text-xs text-gray-500 dark:text-neutral-400 mb-1 font-medium">Total de Pagos</div>
                                                    <div class="text-base font-bold text-orange-900 dark:text-orange-200">{{ getPagosFraccionadosVisual(alerta.Evidencias)?.total_pagos }}</div>
                                                </div>
                                                <div class="flex-1 min-w-[170px]">
                                                    <div class="text-xs text-gray-500 dark:text-neutral-400 mb-1 font-medium">Total Pagado</div>
                                                    <div class="text-base font-bold text-green-800 dark:text-green-300">{{ formatCurrency(getPagosFraccionadosVisual(alerta.Evidencias)?.total_pagado) }}</div>
                                                </div>
                                                <div class="flex-1 min-w-[170px]">
                                                    <div class="text-xs text-gray-500 dark:text-neutral-400 mb-1 font-medium">Saldo Pendiente</div>
                                                    <div :class="getPagosFraccionadosVisual(alerta.Evidencias)?.saldo_pendiente === 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-300'"
                                                        class="text-base font-bold">
                                                        {{ formatCurrency(getPagosFraccionadosVisual(alerta.Evidencias)?.saldo_pendiente) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-4 flex flex-wrap gap-6">
                                                <div>
                                                    <span class="text-xs text-gray-500 dark:text-neutral-400 font-medium">¿Operación pagada?</span>
                                                    <span class="ml-2 font-bold px-2 py-1 rounded-full text-xs" :class="getPagosFraccionadosVisual(alerta.Evidencias)?.operacion_pagada ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'">
                                                        {{ getPagosFraccionadosVisual(alerta.Evidencias)?.operacion_pagada ? 'Sí' : 'No' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-6">
                                                <div class="font-semibold text-orange-900 dark:text-orange-100 text-sm mb-1">Detalle de Pagos</div>
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-[260px] w-full text-xs text-left border rounded-sm">
                                                        <thead>
                                                            <tr class="bg-orange-100 dark:bg-orange-900/30 text-orange-900 dark:text-orange-200">
                                                                <th class="py-1 px-2 font-semibold">Monto</th>
                                                                <th class="py-1 px-2 font-semibold">Moneda</th>
                                                                <th class="py-1 px-2 font-semibold">Forma de Pago</th>
                                                                <th class="py-1 px-2 font-semibold">Fecha de Pago</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(pago, index) in getPagosFraccionadosVisual(alerta.Evidencias)?.detalle_pagos" :key="index">
                                                                <td class="py-1 px-2 font-mono">{{ formatCurrency(pago.monto) }}</td>
                                                                <td class="py-1 px-2">{{ pago.moneda }}</td>
                                                                <td class="py-1 px-2">{{ pago.forma_pago }}</td>
                                                                <td class="py-1 px-2">{{ formatDate(pago.fecha_pago) }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="mt-6">
                                                <div class="font-semibold text-orange-900 dark:text-orange-100 text-sm mb-1">Análisis Fraccionado</div>
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-[260px] w-full text-xs text-left border rounded-sm">
                                                        <thead>
                                                            <tr class="bg-orange-100 dark:bg-orange-900/30 text-orange-900 dark:text-orange-200">
                                                                <th class="py-1 px-2 font-semibold">Monto pago</th>
                                                                <th class="py-1 px-2 font-semibold">Esperado</th>
                                                                <th class="py-1 px-2 font-semibold">¿Dentro de tolerancia?</th>
                                                                <th class="py-1 px-2 font-semibold">Diferencia</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(analisis, index) in getPagosFraccionadosVisual(alerta.Evidencias)?.analisis_fraccionado" :key="index">
                                                                <td class="py-1 px-2 font-mono">{{ formatCurrency(analisis.monto_pago) }}</td>
                                                                <td class="py-1 px-2 font-mono">{{ formatCurrency(analisis.esperado) }}</td>
                                                                <td class="py-1 px-2">
                                                                    <span class="font-semibold" :class="analisis.dentro_tolerance ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                                                                        {{ analisis.dentro_tolerance ? 'Sí' : 'No' }}
                                                                    </span>
                                                                </td>
                                                                <td class="py-1 px-2 font-mono"
                                                                    :class="analisis.diferencia !== 0 ? 'text-orange-800 dark:text-orange-200 font-semibold' : ''">
                                                                    {{ formatCurrency(analisis.diferencia) }}
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Fin del caso especial -->

                                    <!-- Case 1: List of files -->
                                    <div v-else-if="getEvidenceFiles(alerta.Evidencias)" class="mt-2 space-y-1.5">
                                        <a v-for="(file, index) in getEvidenceFiles(alerta.Evidencias)" :key="index" :href="getPublicUrl(file.path)" target="_blank" rel="noopener noreferrer"
                                           class="group inline-flex items-center gap-2 rounded-md bg-gray-100/80 p-2 text-sm transition-colors hover:bg-blue-100/70 dark:bg-neutral-800/60 dark:hover:bg-blue-900/30">
                                            <Paperclip class="h-4 w-4 text-gray-500 group-hover:text-blue-600 dark:text-neutral-400 dark:group-hover:text-blue-400" />
                                            <span class="font-medium text-gray-700 group-hover:text-blue-700 dark:text-neutral-300 dark:group-hover:text-blue-300">{{ file.original || file.path.split('/').pop() }}</span>
                                        </a>
                                    </div>

                                    <!-- Case 2: List of payments -->
                                    <div v-else-if="getEvidencePayments(alerta.Evidencias)" class="mt-2 rounded-lg border border-gray-200/80 dark:border-neutral-800/80 overflow-hidden">
                                        <ul class="divide-y divide-gray-200/80 dark:divide-neutral-800/80">
                                            <li v-for="(pago, index) in getEvidencePayments(alerta.Evidencias)" :key="index" class="px-3 py-2 text-xs bg-gray-50/50 dark:bg-neutral-900/30">
                                                <div class="flex justify-between items-center">
                                                    <span class="font-semibold text-gray-800 dark:text-neutral-200">{{ formatCurrency(pago.Monto) }} <span class="font-normal text-gray-500 dark:text-neutral-400">{{ pago.IDMoneda }}</span></span>
                                                    <span class="text-gray-500 dark:text-neutral-400">{{ formatDate(pago.FechaPago) }}</span>
                                                </div>
                                                <div v-if="pago.IDFormaPago" class="text-gray-500 dark:text-neutral-500 mt-0.5">
                                                    Forma de Pago: {{ pago.IDFormaPago }}
                                                </div>
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- Case 3: Other JSON or plain string -->
                                    <pre v-else class="mt-1 whitespace-pre-wrap rounded-md bg-gray-100/70 p-2 text-xs text-gray-800 dark:bg-neutral-800/50 dark:text-neutral-200">{{ formatEvidenceText(alerta.Evidencias) }}</pre>
                                </div>
                            </div>
                            <div v-else class="text-center text-gray-500 dark:text-neutral-400 italic py-10">Sin alertas para este cliente.</div>
                        </section>

                        <!-- LISTAS DE OBSERVACIÓN -->
                        <section v-if="activeTab === 'listas'" class="grid grid-cols-1 gap-8 md:grid-cols-2">
                             <div class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                 <h3 class="font-semibold text-rose-900 dark:text-rose-300">CNSF (Listas Negras)</h3>
                                 <ul v-if="props.listasNegras.length" class="mt-3 divide-y divide-gray-200/80 dark:divide-neutral-800">
                                     <li v-for="l in props.listasNegras" :key="l.IDRegistroListaCNSF" class="py-3 text-sm">
                                         <p class="font-semibold text-gray-800 dark:text-neutral-200">{{ l.Nombre }} <span class="font-mono text-xs text-gray-500 dark:text-neutral-400">({{ l.RFC }})</span></p>
                                         <p class="text-xs text-gray-500 dark:text-neutral-400">{{ l.Direccion }}, {{ l.Pais }}</p>
                                     </li>
                                 </ul>
                                 <p v-else class="mt-3 text-sm text-gray-500 dark:text-neutral-400 italic">Sin coincidencias.</p>
                             </div>
                             <div class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                 <h3 class="font-semibold text-rose-900 dark:text-rose-300">UIF (Personas Bloqueadas)</h3>
                                 <ul v-if="props.listasUIF.length" class="mt-3 divide-y divide-gray-200/80 dark:divide-neutral-800">
                                     <li v-for="l in props.listasUIF" :key="l.IDRegistroListaUIF" class="py-3 text-sm">
                                         <p class="font-semibold text-gray-800 dark:text-neutral-200">{{ l.Nombre }} <span class="font-mono text-xs text-gray-500 dark:text-neutral-400">({{ l.RFC }})</span></p>
                                         <p class="text-xs text-gray-500 dark:text-neutral-400">Oficio: {{ l.NoOficioUIF }} ({{ l.AnioLista }})</p>
                                     </li>
                                 </ul>
                                 <p v-else class="mt-3 text-sm text-gray-500 dark:text-neutral-400 italic">Sin coincidencias.</p>
                             </div>
                        </section>

                        <!-- PERFIL TRANSACCIONAL -->
                        <section v-if="activeTab === 'perfil'">
                            <div v-if="props.perfilTransaccional" class="rounded-xl border border-gray-200/80 bg-white/70 p-6 shadow-lg shadow-gray-200/40 backdrop-blur-lg dark:border-neutral-800 dark:bg-neutral-950/70 dark:shadow-black/20">
                                <h3 class="text-base font-semibold text-purple-900 dark:text-purple-300">Perfil Transaccional Calculado</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-neutral-400">Fecha de ejecución: {{ formatDate(props.perfilTransaccional.FechaEjecucción) }}</p>
                                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Perfil Total</dt><dd class="mt-1 text-lg font-bold text-purple-800 dark:text-purple-200">{{ props.perfilTransaccional.Perfil }}</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Nivel Riesgo Ocupación</dt><dd class="mt-1 font-semibold text-gray-800 dark:text-neutral-200">{{ riesgoNombre(props.perfilTransaccional.NivelRiesgo) }}</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Rubro/Giro</dt><dd class="mt-1 text-gray-700 dark:text-neutral-300">{{ props.perfilTransaccional.OcupGiro }}</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Riesgo Nacionalidad</dt><dd class="mt-1 font-semibold text-gray-800 dark:text-neutral-200">{{ riesgoNombre(props.perfilTransaccional.NivelRiesgoNac) }}</dd></div>
                                     <div><dt class="font-medium text-gray-500 dark:text-neutral-400">Riesgo Residencia</dt><dd class="mt-1 font-semibold text-gray-800 dark:text-neutral-200">{{ riesgoNombre(props.perfilTransaccional.NivelRiesgoResidencia) }}</dd></div>
                                </dl>
                            </div>
                             <div v-else class="text-center text-gray-500 dark:text-neutral-400 italic py-10">Sin perfil transaccional registrado.</div>
                        </section>
                    </div>
                </Transition>
            </main>
                </div>
            </div>
        </Transition>

        <div
            v-if="showActivarClienteModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        >
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-neutral-900">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100">
                    Confirmar activación de cliente
                </h3>
                <p class="mt-3 text-sm text-gray-700 dark:text-neutral-300">
                    Antes de activar este cliente, se sugiere revisar primero sus coincidencias en listas de observación y sus alertas PLD.
                </p>
                <div class="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-xs text-yellow-800 dark:border-yellow-800/60 dark:bg-yellow-900/20 dark:text-yellow-200">
                    Estado actual:
                    <span class="font-semibold">{{ onBlacklist ? 'Con coincidencias en listas' : 'Sin coincidencias en listas' }}</span>
                    |
                    <span class="font-semibold">{{ hasAlerts ? 'Con alertas PLD' : 'Sin alertas PLD' }}</span>
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
                        :disabled="activandoCliente"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-70"
                    >
                        <span
                            v-if="activandoCliente"
                            class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"
                        ></span>
                        Confirmar activación
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script lang="ts">
// Icono de mundo para extranjero
import { Globe as GlobeIcon } from 'lucide-vue-next'
</script>

<style>
.fade-enter-active,
.fade-leave-active,
.fade-in-enter-active {
  transition: opacity 0.4s ease-out;
}

.fade-enter-from,
.fade-leave-to,
.fade-in-enter-from {
  opacity: 0;
}
</style>
