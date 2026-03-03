<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useForm, usePage, router, Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Titulo from '@/components/ui/Titulo.vue'
import FadeIn from '@/components/ui/animation/fadeIn.vue'
import Toast from '@/components/ui/alert/Toast.vue'
import Input from '@/components/forms/Input.vue'
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
              <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4">
                <Input
                  label="Operaciones Relevantes"
                  v-model="form.operacionesRelevantes"
                  type="float"
                  icon="dollar"
                  suffix="USD"
                />
                <Input
                  label="Desviación Estándar Alerta Monto Inusual"
                  v-model="form.desviacionEstandarInusualidad"
                  type="float"
                  icon="chart"
                />
                <Input
                  label="Años Considerados Alerta Monto Inusual"
                  v-model="form.aniosConsideradosInusualidad"
                  type="int"
                  icon="calendar"
                  suffix="años"
                />
                <Input
                  label="Monto Mínimo Alertas"
                  v-model="form.montoMinimoAlerta"
                  type="float"
                  icon="alert"
                  suffix="USD"
                />
                <Input
                  label="Porcentaje Tolerancia +/- Pagos Fraccionados"
                  v-model="form.toleranciaPagosFraccionados"
                  type="float"
                  icon="percent"
                  suffix="%"
                />
              </div>
            </section>
          </div>

          <!-- Sección: Perfil Transaccional -->
          <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <section class="space-y-4">
              <h3 class="text-2xl font-semibold text-slate-800 dark:text-neutral-200 mb-4">Parámetros Perfil Transaccional</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4">
                <Input
                  label="Riesgo Alto Perfil"
                  v-model="form.riesgoAltoPerfil"
                  type="float"
                  icon="alert"
                />
              </div>
            </section>
          </div>

          <!-- Sección: Reporteador PLD -->
          <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <section class="space-y-4">
              <h3 class="text-2xl font-semibold text-slate-800 dark:text-neutral-200 mb-4">Parámetros Reporteador PLD</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4">
                <Input
                  label="Reporteador Monto Acumulado"
                  v-model="form.reporteadorMontoAcumulado"
                  type="float"
                  icon="dollar"
                  suffix="USD"
                />
              </div>
            </section>
          </div>

          <!-- Sección: Buscadores Listas Negras -->
          <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <section class="space-y-4">
              <h3 class="text-2xl font-semibold text-slate-800 dark:text-neutral-200 mb-4">Parámetros Buscadores Listas Negras</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4">
                <Input
                  label="Umbral mínimo Buscador UIF"
                  v-model="form.umbralBuscadorUIF"
                  type="float"
                  icon="search"
                  suffix="%"
                />
                <Input
                  label="Umbral mínimo Buscador CNSF"
                  v-model="form.umbralBuscadorCNSF"
                  type="float"
                  icon="search"
                  suffix="%"
                />
              </div>
            </section>
          </div>

          <!-- Sección: Autorización Pagos en Efectivo -->
          <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <section class="space-y-4">
              <h3 class="text-2xl font-semibold text-slate-800 dark:text-neutral-200 mb-4">
                Parámetros Autorización Aplicación de Pagos en Efectivo
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                <Input
                  label="Monto Autorización Pagos Efectivo Persona Física"
                  v-model="form.montoAutorizaPagoEfectivoPF"
                  type="float"
                  icon="users"
                  suffix="MXN"
                />

                <Input
                  label="Monto Autorización Pagos Efectivo Persona Moral"
                  v-model="form.montoAutorizaPagoEfectivoPM"
                  type="float"
                  icon="building"
                  suffix="MXN"
                />
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

