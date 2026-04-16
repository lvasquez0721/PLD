<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
import { Head } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes/index.js';
import { computed } from 'vue';

// Simula el catálogo de monedas, usando la parametría (de CatParametriaPLD.php y ParametrosPLDSeeder.php)
const MONEDAS = [
    { id: 'MXN', nombre: 'Peso mexicano', simbolo: '$', min: 0.01, max: 999999999.99, decimales: 2 },
    { id: 'USD', nombre: 'Dólar estadounidense', simbolo: 'US$', min: 0.01, max: 999999999.99, decimales: 2 },
    // agrega más monedas de acuerdo al seeder/cat si es necesario
];

// Obtén el símbolo y descripciones adicionales de la moneda según el catálogo
function getMonedaInfo(id: string | null | undefined) {
    const m = MONEDAS.find(m => m.id === (id || '').toUpperCase());
    return m || MONEDAS[0]; // fallback MXN
}

// Props (inertia)
const props = defineProps<{
    alerta: any,
    cliente?: any,
    operacion?: any,
    reportes?: any[]
}>();

const { alerta, cliente, operacion, reportes } = props;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alertas', href: dashboard().url },
    { title: 'Detalle de Alerta', href: '' },
];

function statusBadgeColor(status: string): string {
    if (!status) return 'bg-gray-200 text-gray-600';
    switch (status.toLowerCase()) {
        case 'generado':
            return 'bg-yellow-100 text-yellow-800 border border-yellow-300';
        case 'analizada':
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
    // Map some more if needed
    return 'bg-slate-200 text-slate-700 border border-slate-300';
}

function personaTipoDisplay(id: number | null | undefined) {
    if (id === 1) return 'Física';
    if (id === 2) return 'Moral';
    return '';
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

function resolveCurrency(moneda: string | null | undefined) {
    const info = getMonedaInfo(moneda);
    return info.id;
}

function resolveMonedaSimbolo(moneda: string | null | undefined) {
    return getMonedaInfo(moneda).simbolo;
}

function parseEvidencias(e: string | null | undefined): any {
    if (!e) return null;
    try { return typeof e === 'string' ? JSON.parse(e) : e; } catch { return null; }
}

const evidencias = computed(() => parseEvidencias(alerta?.Evidencias));

const pagosOperacion = computed(() => {
    return (operacion?.pagos || []);
});

const analisisFraccionado = computed(() => {
    return evidencias.value?.analisis_fraccionado ?? [];
});

const detallePagos = computed(() => evidencias.value?.detalle_pagos ?? []);

const defaultMoneda = computed(() => {
    // ParametroPLDSeeder/CatParametria usa MXN como default
    return alerta.IDMoneda ?? operacion?.IDMoneda ?? 'MXN';
});
// For UI: show alerta, cliente, operacion, reportes, fallback as -, and hide section if data not present except alerta
</script>

<template>
  <Head title="Detalle de alerta" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <FadeIn>
      <div class="mx-auto max-w-6xl py-6 px-2 sm:px-4 md:px-6 space-y-7">

        <!-- 1. Encabezado de la alerta principal -->
        <div class="flex flex-col md:flex-row md:items-center gap-4 pb-1 border-b border-gray-200 dark:border-neutral-700">
          <div class="flex-1 flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-2">
              <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-widest align-middle"
                    :class="patronBadgeClass(alerta.Patron)">
                {{ alerta.Patron || '---' }}
              </span>
              <span class="inline-block px-2 py-1 rounded-full text-[11px] font-medium border"
                    :class="statusBadgeColor(alerta.Estatus)">
                {{ alerta.Estatus || '---' }}
              </span>
              <span v-if="!!alerta.IDMoneda" class="inline-block px-2 py-0.5 rounded-full text-xs bg-blue-50 border border-blue-200 text-blue-800">
                Moneda: {{ getMonedaInfo(alerta.IDMoneda).nombre }} ({{ getMonedaInfo(alerta.IDMoneda).simbolo }})
              </span>
            </div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-neutral-100">
              {{ alerta.Descripcion || `Alerta #${alerta.IDRegistroAlerta ?? ''}` }}
            </h2>
          </div>
          <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div>
              <div class="text-[12px] uppercase font-medium text-gray-500 dark:text-neutral-300">Detectada</div>
              <div class="font-semibold tracking-tight text-gray-900 dark:text-neutral-100 mt-1">
                {{ formatDate(alerta.FechaDeteccion) }}
                <span v-if="alerta.HoraDeteccion" class="text-xs text-gray-500 ml-2">{{ alerta.HoraDeteccion }}</span>
              </div>
            </div>
            <div>
              <div class="text-[12px] uppercase font-medium text-gray-500 dark:text-neutral-300">Monto involucrado</div>
              <div class="font-semibold tracking-tight text-gray-900 dark:text-neutral-100 mt-1">
                {{ numberFormat(alerta.MontoOperacion, alerta.IDMoneda ?? operacion?.IDMoneda ?? 'MXN') }}
                <span class="ml-1 text-xs text-gray-600 dark:text-gray-300">
                  {{ getMonedaInfo(alerta.IDMoneda ?? operacion?.IDMoneda).nombre }}
                </span>
              </div>
            </div>
            <div v-if="alerta.Poliza">
              <div class="text-[12px] uppercase font-medium text-gray-500 dark:text-neutral-300">Póliza</div>
              <div class="font-semibold tracking-tight text-gray-900 dark:text-neutral-100 mt-1">
                {{ alerta.Poliza }}
              </div>
            </div>
          </div>
        </div>

        <!-- 2. Resumen rápido -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
          <div class="bg-white dark:bg-neutral-900 border rounded-xl p-3 flex flex-col items-center shadow-sm">
            <div class="text-xs text-gray-500 dark:text-neutral-400 font-medium uppercase mb-1">Cliente</div>
            <div class="font-semibold text-gray-800 dark:text-neutral-100 truncate text-center">
              {{ alerta.Cliente || [cliente?.Nombre, cliente?.ApellidoPaterno, cliente?.ApellidoMaterno].filter(Boolean).join(' ') || '-' }}
            </div>
          </div>
          <div class="bg-white dark:bg-neutral-900 border rounded-xl p-3 flex flex-col items-center shadow-sm">
            <div class="text-xs text-gray-500 dark:text-neutral-400 font-medium uppercase mb-1">RFC</div>
            <div class="font-semibold text-gray-800 dark:text-neutral-100 truncate text-center">
              {{ cliente?.RFC || '-' }}
            </div>
          </div>
          <div class="bg-white dark:bg-neutral-900 border rounded-xl p-3 flex flex-col items-center shadow-sm">
            <div class="text-xs text-gray-500 dark:text-neutral-400 font-medium uppercase mb-1">Póliza</div>
            <div class="font-semibold text-gray-800 dark:text-neutral-100 truncate text-center">
              {{ alerta.Poliza || operacion?.FolioPoliza || '-' }}
            </div>
          </div>
          <div class="bg-white dark:bg-neutral-900 border rounded-xl p-3 flex flex-col items-center shadow-sm">
            <div class="text-xs text-gray-500 dark:text-neutral-400 font-medium uppercase mb-1">Monto operación</div>
            <div class="font-semibold text-gray-800 dark:text-neutral-100 truncate text-center">
              {{ numberFormat(operacion?.PrimaTotal ?? alerta.MontoOperacion, operacion?.IDMoneda ?? alerta.IDMoneda ?? 'MXN') }}
              <span class="ml-1 text-xs text-gray-600 dark:text-gray-300">
                {{ getMonedaInfo(operacion?.IDMoneda ?? alerta.IDMoneda).nombre }}
              </span>
            </div>
          </div>
          <div class="bg-white dark:bg-neutral-900 border rounded-xl p-3 flex flex-col items-center shadow-sm">
            <div class="text-xs text-gray-500 dark:text-neutral-400 font-medium uppercase mb-1">Pagos detectados</div>
            <div class="font-semibold text-gray-800 dark:text-neutral-100 truncate text-center">
              {{ detallePagos.length || pagosOperacion.length || '0' }}
            </div>
          </div>
          <div class="bg-white dark:bg-neutral-900 border rounded-xl p-3 flex flex-col items-center shadow-sm">
            <div class="text-xs text-gray-500 dark:text-neutral-400 font-medium uppercase mb-1">Estado operación</div>
            <div class="font-semibold text-gray-800 dark:text-neutral-100 truncate text-center">
              <span v-if="evidencias?.operacion_pagada === true" class="text-green-700 dark:text-green-300">Liquidada</span>
              <span v-else-if="evidencias?.operacion_pagada === false" class="text-yellow-700 dark:text-yellow-200">Pendiente</span>
              <span v-else>-</span>
            </div>
          </div>
        </div>

        <!-- 3. Información de cliente -->
        <div v-if="cliente" class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border mt-2">
          <div class="font-semibold text-lg text-gray-900 dark:text-neutral-100 mb-3 flex items-center gap-2">
            Información del Cliente
            <span v-if="cliente.CoincideEnListasNegras == 1" class="bg-red-100 text-red-800 px-2 py-0.5 rounded-full text-[11px] font-semibold ml-2">Lista negra</span>
            <span v-if="cliente.EsPPEActivo" class="bg-purple-100 text-purple-800 px-2 py-0.5 rounded-full text-[11px] font-semibold">PPE Activo</span>
          </div>
          <div class="grid md:grid-cols-3 gap-x-6 gap-y-3">
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Nombre Completo</div>
              <div class="font-medium">{{ [cliente?.Nombre, cliente?.ApellidoPaterno, cliente?.ApellidoMaterno].filter(Boolean).join(' ') }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">RFC</div>
              <div class="font-medium">{{ cliente.RFC || '-' }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Tipo de Persona</div>
              <div class="font-medium">{{ personaTipoDisplay(cliente.IDTipoPersona) }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Nacionalidad</div>
              <div class="font-medium">{{ cliente.IDNacionalidad || '-' }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Fecha de Nacimiento</div>
              <div class="font-medium">{{ formatDate(cliente.FechaNacimiento) }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Ingresos estimados</div>
              <div class="font-medium">{{ numberFormat(cliente.IngresosEstimados, 'MXN') }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Ocupación/Giro</div>
              <div class="font-medium">{{ cliente.IDOcupacionGiro || '-' }}</div>
            </div>
            <!-- Add more if needed -->
          </div>
        </div>

        <!-- 4. Información de la operación -->
        <div v-if="operacion" class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border">
          <div class="font-semibold text-lg text-gray-900 dark:text-neutral-100 mb-3">Datos de la operación</div>
          <div class="grid md:grid-cols-3 gap-x-6 gap-y-3">
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Póliza</div>
              <div class="font-medium">{{ operacion.FolioPoliza || '-' }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Endoso</div>
              <div class="font-medium">{{ operacion.FolioEndoso || '-' }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Fecha de Emisión</div>
              <div class="font-medium">{{ formatDate(operacion.FechaEmision) }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Vigencia</div>
              <div class="font-medium">{{ formatDate(operacion.FechaInicioVigencia) }} a {{ formatDate(operacion.FechaFinVigencia) }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Prima total</div>
              <div class="font-medium">{{ numberFormat(operacion.PrimaTotal, operacion.IDMoneda ?? 'MXN') }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Gastos de emisión</div>
              <div class="font-medium">{{ numberFormat(operacion.GastosEmision, operacion.IDMoneda ?? 'MXN') }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Agente</div>
              <div class="font-medium">{{ [operacion.NombreAgente, operacion.APaternoAgente, operacion.AMaternoAgente].filter(Boolean).join(' ') }}</div>
            </div>
            <div>
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">RFC Agente</div>
              <div class="font-medium">{{ operacion.RFCAgente }}</div>
            </div>
          </div>
        </div>

        <!-- 5. Pagos de la operación -->
        <div v-if="detallePagos.length || pagosOperacion.length" class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border">
          <div class="flex flex-row items-center gap-2 mb-2">
            <div class="font-semibold text-lg text-gray-900 dark:text-neutral-100">Pagos detectados</div>
            <span v-if="(detallePagos.length||pagosOperacion.length) > 1" class="text-xs bg-purple-50 dark:bg-purple-900/10 text-purple-700 dark:text-purple-300 px-2 py-0.5 rounded-full ml-2">Fraccionado</span>
          </div>
          <div class="overflow-auto">
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
              <tr v-for="pago, i in detallePagos.length ? detallePagos : pagosOperacion" :key="i">
                <td class="px-3 py-2 whitespace-nowrap">{{ pago.fecha_pago ?? pago.FechaPago ?? '-' }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ pago.forma_pago ?? '-' }}</td>
                <td class="px-3 py-2 whitespace-nowrap">
                  {{ numberFormat(pago.monto ?? pago.Monto, pago.moneda ?? pago.IDMoneda ?? 'MXN') }}
                </td>
                <td class="px-3 py-2 whitespace-nowrap">
                  {{ getMonedaInfo(pago.moneda ?? pago.IDMoneda).nombre }}
                  <span v-if="pago.moneda || pago.IDMoneda">({{ getMonedaInfo(pago.moneda ?? pago.IDMoneda).simbolo }})</span>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>

        <!-- 6. Evidencias del sistema -->
        <div v-if="evidencias" class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border">
          <div class="font-semibold text-lg text-gray-900 dark:text-neutral-100 mb-3">Análisis automático</div>
          <div class="flex flex-wrap gap-x-8 gap-y-2 mb-4">
            <div>
              <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Total de pagos</span>
              <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">{{ evidencias.total_pagos ?? '-' }}</span>
            </div>
            <div>
              <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Total pagado</span>
              <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">
                {{ numberFormat(evidencias.total_pagado, alerta.IDMoneda ?? operacion?.IDMoneda ?? 'MXN') }}
                <span class="ml-1 text-xs text-gray-600 dark:text-gray-300">
                  {{ getMonedaInfo(alerta.IDMoneda ?? operacion?.IDMoneda).nombre }}
                </span>
              </span>
            </div>
            <div>
              <span class="block text-xs text-gray-500 uppercase font-medium mb-0.5">Saldo pendiente</span>
              <span class="text-sm font-bold text-gray-950 dark:text-neutral-100">
                {{ numberFormat(evidencias.saldo_pendiente, alerta.IDMoneda ?? operacion?.IDMoneda ?? 'MXN') }}
                <span class="ml-1 text-xs text-gray-600 dark:text-gray-300">
                  {{ getMonedaInfo(alerta.IDMoneda ?? operacion?.IDMoneda).nombre }}
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
          <div class="overflow-auto">
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
              <tr v-for="pago, i in detallePagos.length ? detallePagos : pagosOperacion" :key="i">
                <td class="px-3 py-2 whitespace-nowrap">{{ pago.fecha_pago ?? pago.FechaPago ?? '-' }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ pago.forma_pago ?? '-' }}</td>
                <td class="px-3 py-2 whitespace-nowrap">
                  {{ numberFormat(pago.monto ?? pago.Monto, pago.moneda ?? pago.IDMoneda ?? 'MXN') }}
                </td>
                <td class="px-3 py-2 whitespace-nowrap">
                  {{ getMonedaInfo(pago.moneda ?? pago.IDMoneda).nombre }}
                  <span v-if="pago.moneda || pago.IDMoneda">({{ getMonedaInfo(pago.moneda ?? pago.IDMoneda).simbolo }})</span>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>

        <!-- 7. Descripción de la alerta -->
        <div class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border">
          <div class="font-semibold text-lg text-gray-900 dark:text-neutral-100 mb-1">Descripción</div>
          <div class="mb-2 text-sm text-gray-800 dark:text-neutral-200 whitespace-pre-line">{{ alerta.Descripcion || '-' }}</div>
          <div v-if="alerta.Razones" class="bg-yellow-50/80 dark:bg-yellow-900/15 border-l-4 border-yellow-400 py-2 px-4 rounded-md text-sm text-yellow-900 dark:text-yellow-200 mt-3">
            <strong>Justificación:</strong>
            <span class="pl-1">{{ alerta.Razones }}</span>
          </div>
        </div>

        <!-- 8. Reportes relacionados -->
        <div v-if="Array.isArray(reportes) && reportes.length" class="bg-white dark:bg-neutral-900 p-5 rounded-2xl shadow border">
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
                      <span v-else class="inline-block px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700 border border-gray-200">{{ reporte.Estatus || '-' }}</span>
                    </td>
                    <td class="px-3 py-2">{{ formatDate(reporte.FechaOperacion) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
        </div>

      </div>
    </FadeIn>
  </AppLayout>
</template>
