<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useForm, usePage, router, Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Titulo from '@/components/ui/Titulo.vue'
import FadeIn from '@/components/ui/animation/fadeIn.vue'
import Toast from '@/components/ui/alert/Toast.vue'
import { type BreadcrumbItem } from '@/types'
import { Gavel } from 'lucide-vue-next'

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
      <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex items-center justify-between">
          <Titulo :icon="Gavel" title="Ajuste de Parámetros PLD" size="md" weight="bold" class="mb-2" />
        </div>

        <form @submit.prevent="actualizarParametros" class="space-y-6">
          <!-- Sección: Parámetros Alertas -->
          <section class="space-y-4">
            <h3 class="text-2xl regular">Parámetros Alertas</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="form-label">Operaciones Relevantes (USD):</label>
                <input 
                  type="number" 
                  v-model="form.operacionesRelevantes" 
                  class="form-control"
                  step="0.01"
                />
              </div>
              <div>
                <label class="form-label">Desviación Estándar Alerta Monto Inusual:</label>
                <input 
                  type="number" 
                  v-model="form.desviacionEstandarInusualidad" 
                  class="form-control"
                  step="0.01"
                />
              </div>
              <div>
                <label class="form-label">Años considerados Alerta Monto Inusual:</label>
                <input 
                  type="number" 
                  v-model="form.aniosConsideradosInusualidad" 
                  class="form-control"
                  step="1"
                />
              </div>
              <div>
                <label class="form-label">Monto Mínimo Alertas (USD):</label>
                <input 
                  type="number" 
                  v-model="form.montoMinimoAlerta" 
                  class="form-control"
                  step="0.01"
                />
              </div>
              <div>
                <label class="form-label">Porcentaje Tolerancia +/- Pagos Fraccionados (%):</label>
                <input 
                  type="number" 
                  v-model="form.toleranciaPagosFraccionados" 
                  class="form-control"
                  step="0.01"
                />
              </div>
            </div>
          </section>

          <!-- Sección: Perfil Transaccional -->
          <section class="space-y-4">
            <h3 class="text-2xl regular">Parámetros Perfil Transaccional</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="form-label">Riesgo Alto Perfil:</label>
                <input 
                  type="number" 
                  v-model="form.riesgoAltoPerfil" 
                  class="form-control"
                  step="0.01"
                />
              </div>
            </div>
          </section>

          <!-- Sección: Reporteador PLD -->
          <section class="space-y-4">
            <h3 class="text-2xl regular">Parámetros Reporteador PLD</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="form-label">Reporteador Monto Acumulado (USD):</label>
                <input 
                  type="number" 
                  v-model="form.reporteadorMontoAcumulado" 
                  class="form-control"
                  step="0.01"
                />
              </div>
            </div>
          </section>

          <!-- Sección: Buscadores Listas Negras -->
          <section class="space-y-4">
            <h3 class="text-2xl regular">Parámetros Buscadores Listas Negras</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="form-label">Umbral mínimo Buscador UIF (%):</label>
                <input 
                  type="number" 
                  v-model="form.umbralBuscadorUIF" 
                  class="form-control"
                  step="0.01"
                />
              </div>
              <div>
                <label class="form-label">Umbral mínimo Buscador CNSF (%):</label>
                <input 
                  type="number" 
                  v-model="form.umbralBuscadorCNSF" 
                  class="form-control"
                  step="0.01"
                />
              </div>
            </div>
          </section>

          <!-- Sección: Autorización Pagos en Efectivo -->
          <section class="space-y-4">
            <h3 class="text-2xl regular">
              Parámetros Autorización Aplicación de Pagos en Efectivo
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="form-label">
                  Monto Autorización Pagos Efectivo Persona Física <span>(MXN):</span>
                </label>
                <input
                  type="number"
                  v-model="form.montoAutorizaPagoEfectivoPF"
                  class="form-control"
                  step="0.01"
                />
              </div>

              <div>
                <label class="form-label">
                  Monto Autorización Pagos Efectivo Persona Moral (MXN):
                </label>
                <input
                  type="number"
                  v-model="form.montoAutorizaPagoEfectivoPM"
                  class="form-control"
                  step="0.01"
                />
              </div>
            </div>
          </section>

          <!-- Botón -->
          <div class="flex justify-end">
            <button 
              type="submit" 
              class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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

<style scoped>
.form-label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.25rem;
}
.form-control {
  width: 100%;
  padding: 0.4rem 0.6rem;
  border: 1px solid #ccc;
  border-radius: 0.375rem;
}
.form-control:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-control:disabled {
  background-color: #f3f4f6;
  cursor: not-allowed;
}
</style>    