<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useForm, usePage, router, Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Titulo from '@/components/ui/Titulo.vue'
import FadeIn from '@/components/ui/animation/fadeIn.vue'
import Toast from '@/components/ui/alert/Toast.vue'
import { type BreadcrumbItem } from '@/types'
import { Settings } from 'lucide-vue-next'

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Parametría PLD',
    href: '',
  },
]

interface Props {
  parametros: {
    operacionesRelevantes: string
    desviacionEstandarInusualidad: string
    aniosConsideradosInusualidad: string
    montoMinimoAlerta: string
    toleranciaPagosFraccionados: string
    riesgoAltoPerfil: string
    reporteadorMontoAcumulado: string
    umbralBuscadorUIF: string
    umbralBuscadorCNSF: string
    montoAutorizaPagoEfectivoPF: string
    montoAutorizaPagoEfectivoPM: string
  }
}

const props = defineProps<Props>()

const form = useForm({
  operacionesRelevantes: props.parametros.operacionesRelevantes,
  desviacionEstandarInusualidad: props.parametros.desviacionEstandarInusualidad,
  aniosConsideradosInusualidad: props.parametros.aniosConsideradosInusualidad,
  montoMinimoAlerta: props.parametros.montoMinimoAlerta,
  toleranciaPagosFraccionados: props.parametros.toleranciaPagosFraccionados,
  riesgoAltoPerfil: props.parametros.riesgoAltoPerfil,
  reporteadorMontoAcumulado: props.parametros.reporteadorMontoAcumulado,
  umbralBuscadorUIF: props.parametros.umbralBuscadorUIF,
  umbralBuscadorCNSF: props.parametros.umbralBuscadorCNSF,
  montoAutorizaPagoEfectivoPF: props.parametros.montoAutorizaPagoEfectivoPF,
  montoAutorizaPagoEfectivoPM: props.parametros.montoAutorizaPagoEfectivoPM
})

const page = usePage()

const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref<'success' | 'warning' | 'error'>('success')

function parseUrlToastParams() {
  const query = window.location.search.substring(1)
  if (!query) return {}
  const params: Record<string, string> = {}
  query.split('&').forEach((pair) => {
    let [key, value] = pair.split('=')
    if (key) {
      value = (value || '').replace(/\+/g, ' ')
      params[decodeURIComponent(key)] = decodeURIComponent(value)
    }
  })
  return params
}

onMounted(() => {
  let foundToast = false

  const propToast = page.props.toast as { message: string; type: 'success' | 'warning' | 'error' } | undefined
  if (propToast && propToast.message && propToast.type) {
    toastMessage.value = propToast.message
    toastType.value = propToast.type
    showToast.value = true
    foundToast = true
  }

  if (!foundToast) {
    const urlParams = parseUrlToastParams()
    if (urlParams.toast_message) {
      toastMessage.value = urlParams.toast_message
      if (
        urlParams.toast_type === 'success' ||
        urlParams.toast_type === 'warning' ||
        urlParams.toast_type === 'error'
      ) {
        toastType.value = urlParams.toast_type
      } else {
        toastType.value = 'success'
      }
      showToast.value = true

      try {
        const url = new URL(window.location.href)
        url.searchParams.delete('toast_message')
        url.searchParams.delete('toast_type')
        window.history.replaceState({}, document.title, url.pathname + url.search)
      } catch (e) { }
    }
  }
})

const actualizarParametros = () => {
  form.post('/parametria-pld/actualizar', {
    preserveScroll: true,
    onSuccess: () => {
      toastMessage.value = 'Parámetros actualizados correctamente.'
      toastType.value = 'success'
      showToast.value = true

      router.reload({ only: ['parametros'] })
    },
    onError: () => {
      toastMessage.value = 'Error al actualizar los parámetros. Verifica los datos ingresados.'
      toastType.value = 'error'
      showToast.value = true
    }
  })
}
</script>

<template>
  <Head title="Parametría PLD" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <FadeIn>
      <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-0">


        <form @submit.prevent="actualizarParametros" class="space-y-6">
          <!-- Sección: Parámetros Alertas -->
          <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <section class="space-y-4">
              <h3 class="text-2xl font-semibold text-slate-800 dark:text-neutral-200 mb-4">Parámetros Alertas</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Operaciones Relevantes (USD):</label>
                  <input
                    type="number"
                    v-model="form.operacionesRelevantes"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="0.01"
                  />
                </div>
                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Desviación Estándar Alerta Monto Inusual:</label>
                  <input
                    type="number"
                    v-model="form.desviacionEstandarInusualidad"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="0.01"
                  />
                </div>
                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Años considerados Alerta Monto Inusual:</label>
                  <input
                    type="number"
                    v-model="form.aniosConsideradosInusualidad"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="1"
                  />
                </div>
                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Monto Mínimo Alertas (USD):</label>
                  <input
                    type="number"
                    v-model="form.montoMinimoAlerta"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="0.01"
                  />
                </div>
                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Porcentaje Tolerancia +/- Pagos Fraccionados (%):</label>
                  <input
                    type="number"
                    v-model="form.toleranciaPagosFraccionados"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="0.01"
                  />
                </div>
              </div>
            </section>
          </div>

          <!-- Sección: Perfil Transaccional -->
          <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <section class="space-y-4">
              <h3 class="text-2xl font-semibold text-slate-800 dark:text-neutral-200 mb-4">Parámetros Perfil Transaccional</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Riesgo Alto Perfil:</label>
                  <input
                    type="number"
                    v-model="form.riesgoAltoPerfil"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="0.01"
                  />
                </div>
              </div>
            </section>
          </div>

          <!-- Sección: Reporteador PLD -->
          <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <section class="space-y-4">
              <h3 class="text-2xl font-semibold text-slate-800 dark:text-neutral-200 mb-4">Parámetros Reporteador PLD</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Reporteador Monto Acumulado (USD):</label>
                  <input
                    type="number"
                    v-model="form.reporteadorMontoAcumulado"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="0.01"
                  />
                </div>
              </div>
            </section>
          </div>

          <!-- Sección: Buscadores Listas Negras -->
          <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <section class="space-y-4">
              <h3 class="text-2xl font-semibold text-slate-800 dark:text-neutral-200 mb-4">Parámetros Buscadores Listas Negras</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Umbral mínimo Buscador UIF (%):</label>
                  <input
                    type="number"
                    v-model="form.umbralBuscadorUIF"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="0.01"
                  />
                </div>
                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Umbral mínimo Buscador CNSF (%):</label>
                  <input
                    type="number"
                    v-model="form.umbralBuscadorCNSF"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="0.01"
                  />
                </div>
              </div>
            </section>
          </div>

          <!-- Sección: Autorización Pagos en Efectivo -->
          <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <section class="space-y-4">
              <h3 class="text-2xl font-semibold text-slate-800 dark:text-neutral-200 mb-4">
                Parámetros Autorización Aplicación de Pagos en Efectivo
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">
                    Monto Autorización Pagos Efectivo Persona Física <span>(MXN):</span>
                  </label>
                  <input
                    type="number"
                    v-model="form.montoAutorizaPagoEfectivoPF"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="0.01"
                  />
                </div>

                <div>
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">
                    Monto Autorización Pagos Efectivo Persona Moral (MXN):
                  </label>
                  <input
                    type="number"
                    v-model="form.montoAutorizaPagoEfectivoPM"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                    step="0.01"
                  />
                </div>
              </div>
            </section>
          </div>

          <!-- Botón -->
          <div class="mt-8 flex justify-end">
            <button
              type="submit"
              class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Actualizando...' : 'Actualizar parámetros' }}
            </button>
          </div>
        </form>
      </div>
    </FadeIn>

    <!-- Toast notification -->
    <Toast v-model="showToast" :message="toastMessage" :type="toastType" :duration="5000" />
  </AppLayout>
</template>

