<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

// Componentes y utilidades
import AppLayout from '@/layouts/AppLayout.vue'
import Titulo from '@/components/ui/Titulo.vue'
import { Users, FileSpreadsheet, Search } from 'lucide-vue-next'

// Props desde Laravel
interface Campo {
  IDCampo: number
  IDModulo: number
  Seccion: number
  Tipo: string
  NombreCampo: string
  EtiquetaCampo?: string | null
  Placeholder?: string | null
  Requerido?: number | boolean
  Visible?: number | boolean
  Value?: string | null
}

const props = defineProps<{
  campos: Campo[]
  periodos: { FechaEjecucción: string; PeriodoFormateado: string }[]
}>()

// -----------------------------------------
// Estados
// -----------------------------------------
const formRegistrar = ref<Record<string, any>>({})
const showModalRegistrar = ref(false)
const resultados = ref<any[]>([])
const csvUrl = ref('')
const filtroNombre = ref('')
const showModalInfo = ref(false)
const modalTitulo = ref('')
const modalMensaje = ref('')

// -----------------------------------------
// Modales
// -----------------------------------------
// Modal registrar
const openModalRegistrar = () => (showModalRegistrar.value = true)
const closeModalRegistrar = () => {
  showModalRegistrar.value = false
  formRegistrar.value = {}
}
// Modal informativo
const mostrarModal = (titulo: string, mensaje: string) => {
  modalTitulo.value = titulo
  modalMensaje.value = mensaje
  showModalInfo.value = true
}
const cerrarModalInfo = () => (showModalInfo.value = false)
// Modal ejecutar perfil
const showModalEjecutar = ref(false)
const openModalEjecutar = () => (showModalEjecutar.value = true)
const closeModalEjecutar = () => (showModalEjecutar.value = false)

// -----------------------------------------
// Guardar perfil
// -----------------------------------------
const submitRegistrar = () => {
  router.post('/perfil-transaccional/insert', formRegistrar.value, {
    preserveScroll: true,
    onSuccess: () => {
      mostrarModal('Éxito', 'Perfil guardado correctamente.')
      closeModalRegistrar()
    },
    onError: (errors) => {
      console.error(errors)
      mostrarModal('Error', 'No se pudo guardar el perfil.')
    },
  })
}

// -----------------------------------------
// Buscar información
// -----------------------------------------
const buscarInformacion = async () => {
  if (!formRegistrar.value['Periodo']) {
    mostrarModal('Error', 'Seleccione un periodo antes de continuar.')
    return
  }

  try {
    const { data } = await axios.post('/perfil-transaccional/buscar', {
      Periodo: formRegistrar.value['Periodo'],
    })
    //console.log('Datos recibidos desde Laravel:', data) // imprime todo lo que llega
    if (data.success) {
      resultados.value = data.datos
      csvUrl.value = data.csvUrl
      mostrarModal('Éxito', data.mensaje)
    } else {
      mostrarModal('Sin resultados', data.mensaje || 'No se encontraron registros.')
    }
  } catch (error) {
    console.error(error)
    mostrarModal('Error', 'Ocurrió un problema al consultar la información.')
  }
}

const resultadosFiltrados = computed(() => {
  if (!filtroNombre.value) return resultados.value
  return resultados.value.filter((fila: any) =>
    fila.IDCliente.toString().toLowerCase().includes(filtroNombre.value.toLowerCase())
  )
});

// -----------------------------------------
// Ejecutar perfil
// -----------------------------------------
const ejecutarPerfil = async () => {
  console.log('Ejecutando perfil transaccional...')
  // try {
  //   await axios.post('/perfil-transaccional/ejecutar')
  //   mostrarModal('Ejecución completada', 'El perfil transaccional fue ejecutado correctamente.')
  // } catch (error) {
  //   mostrarModal('Error', 'No se pudo ejecutar el perfil.')
  // }
}

const confirmarEjecutar = async () => {
  closeModalEjecutar()
  await ejecutarPerfil()
}

// -----------------------------------------
// Títulos por sección
// -----------------------------------------
const getTituloSeccion = (index: number) => {
  switch (index) {
    case 0:
      return 'Física'
    case 3:
      return 'Moral'
    case 5:
      return 'Datos complementarios'
    default:
      return null
  }
}
</script>

<template>
  <AppLayout title="Perfil Transaccional">
    <div class="flex items-center justify-between mb-6">
      <Titulo :icon="Users" title="Perfil Transaccional" size="md" weight="bold" />
    </div>

    <!-- Botones -->
    <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
      <button @click="openModalRegistrar" class="px-6 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400 transition-all" > Registrar Perfil </button>

      <!-- <button @click="ejecutarPerfil" class="px-6 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-400 transition-all" > Ejecutar Perfil Transaccional </button> -->
      <button  @click="openModalEjecutar" class="px-6 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-400 transition-all"> Ejecutar Perfil Transaccional</button>

    </div>

    <!-- Contenedor principal -->
    <div class="border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-900 shadow-sm transition-all" >
      <div class="bg-blue-600 dark:bg-blue-500 text-white px-4 py-2 rounded-t-xl font-semibold transition-all" >
        Consulta de información
      </div>

      <div class="p-6 space-y-6">
        <!-- Filtros -->
        <div class="flex flex-wrap justify-between items-center gap-4 border-b dark:border-gray-700 pb-4">
          <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Periodo:</label>
            <select v-model="formRegistrar['Periodo']" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200" >
              <option value="" selected>Seleccione un periodo</option>
              <option v-for="(p, i) in props.periodos" :key="i" :value="p.FechaEjecucción">
                {{ p.PeriodoFormateado }}
              </option>
            </select>
          </div>

          <button @click="buscarInformacion" class="flex items-center gap-2 px-6 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400 transition">
            <Search class="w-4 h-4" /> Buscar Información
          </button>
        </div>

        <!-- Input búsqueda -->
        <div v-if="resultados.length" class="text-gray-600 dark:text-gray-300 italic w-full mb-3">
          <div class="relative">
            <input placeholder="Buscar por nombre del cliente" class="w-full pr-10 p-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 rounded-lg focus:ring-2 focus:ring-blue-400 dark:focus:ring-blue-600 outline-none transition" v-model="filtroNombre" />
            <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400 dark:text-gray-500">
              🔍
            </span>
          </div>
        </div>

        <!-- Resultados -->
        <div v-if="resultados.length" class="overflow-x-auto">
          <label > Resultados: </label>
          <table class="min-w-full border border-gray-300 dark:border-gray-700 text-sm">
            <thead class="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th colspan="3" class="p-2 font-semibold text-left">
                  <a :href="csvUrl" target="_blank" class="text-green-700 dark:text-green-400 hover:underline flex items-center gap-1" >
                    <FileSpreadsheet class="w-5 h-5" /> Información encontrada
                  </a>
                  <p class="text-xs text-right mt-1 text-gray-500 dark:text-gray-400">
                    {{ resultados.length }} registro(s).
                  </p>
                </th>
              </tr>
              <tr>
                <th class="p-2 text-center dark:text-gray-300">Nombre</th>
                <th class="p-2 text-center dark:text-gray-300">Evaluación Perfil</th>
                <th class="p-2 text-right dark:text-gray-300">Periodo</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(fila, index) in resultadosFiltrados" :key="index" class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition" >
                <!-- ID Cliente -->
                <td class="p-2 text-gray-900 dark:text-gray-100">
                  {{ fila.IDCliente }}
                </td>

                <!-- Evaluación Perfil con color dinámico -->
                <td
                  class="p-2 text-center font-semibold rounded-lg"
                  :style="{ backgroundColor: fila.Perfil >= 3
                        ? '#F78181' // rojo claro - riesgo alto
                        : fila.Perfil == 2
                        ? '#F3F781' // amarillo claro - riesgo medio
                        : '#81F781', // verde claro - riesgo bajo
                    color: fila.Perfil >= 3
                        ? '#600000'
                        : fila.Perfil == 2
                        ? '#6B5B00'
                        : '#004D00'
                  }"
                >
                  {{ fila.Perfil }}
                </td>

                <!-- Fecha -->
                <td class="p-2 text-right text-gray-900 dark:text-gray-100">
                  {{ fila.FechaEjecucción }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal de registro -->
    <transition name="modal-fade">
      <div
        v-if="showModalRegistrar"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      >
        <div
          class="bg-white dark:bg-gray-900 rounded-xl shadow-lg w-full max-w-3xl max-h-[90vh] overflow-hidden border border-gray-200 dark:border-gray-700"
        >
          <div
            class="bg-blue-600 dark:bg-blue-500 text-white px-6 py-4 flex justify-between items-center"
          >
            <h2 class="text-xl font-semibold">Registrar Perfil</h2>
            <button @click="closeModalRegistrar" class="text-white hover:text-gray-200">✕</button>
          </div>

          <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <form @submit.prevent="submitRegistrar" class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <template v-for="(campo, index) in props.campos" :key="campo.IDCampo">
                  <div v-if="getTituloSeccion(index)" class="col-span-2">
                    <h3
                      class="text-lg font-semibold text-blue-600 dark:text-blue-400 text-center border-b border-gray-300 dark:border-gray-700 pb-1 mb-2"
                    >
                      {{ getTituloSeccion(index) }}
                    </h3>
                  </div>

                  <div v-show="campo.Visible === 1" class="flex flex-col">
                    <label
                      class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                    >
                      {{ campo.EtiquetaCampo }}
                      <span v-if="campo.Requerido === 1" class="text-red-500">*</span>
                    </label>

                    <input
                      v-if="campo.Tipo !== 'select'"
                      :type="campo.Tipo"
                      v-model="formRegistrar[campo.NombreCampo]"
                      :placeholder="campo.Placeholder ?? ''"
                      :required="campo.Requerido === 1"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500"
                    />

                    <select
                      v-else
                      v-model="formRegistrar[campo.NombreCampo]"
                      :required="campo.Requerido === 1"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="">Seleccione...</option>
                      <option v-for="op in campo.Value?.split(',')" :key="op" :value="op.trim()">
                        {{ op }}
                      </option>
                    </select>
                  </div>
                </template>
              </div>
            </form>
          </div>

          <div
            class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3"
          >
            <button
              @click="closeModalRegistrar"
              type="button"
              class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600"
            >
              Cancelar
            </button>
            <button
              @click="submitRegistrar"
              class="px-4 py-2 bg-blue-600 dark:bg-blue-500 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-400"
            >
              Guardar Perfil
            </button>
          </div>
        </div>
      </div>
    </transition>

    <transition name="modal-fade">
      <div
        v-if="showModalEjecutar"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg w-full max-w-md border border-gray-200 dark:border-gray-700">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h5 class="text-lg font-bold text-gray-900 dark:text-gray-100">Perfil transaccional</h5>
            <button @click="closeModalEjecutar" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">✕</button>
          </div>
          <div class="p-6 text-gray-700 dark:text-gray-300">
            Se iniciará el proceso del perfil transaccional.
          </div>
          <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
            <button @click="closeModalEjecutar" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-700">
              Cancelar
            </button>
            <button @click="confirmarEjecutar" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">
              Aceptar
            </button>
          </div>
        </div>
      </div>
    </transition>


    <!-- Modal informativo
    <traon>nsition name="modal-fade">
      <div
        v-if="showModalInfo"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      >
        <div
          class="bg-white dark:bg-gray-900 rounded-lg shadow-lg max-w-md w-full p-6 text-center border border-gray-200 dark:border-gray-700"
        >
          <h2 class="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-100">
            {{ modalTitulo }}
          </h2>
          <p class="mb-5 text-gray-700 dark:text-gray-300">{{ modalMensaje }}</p>
          <button
            @click="cerrarModalInfo"
            class="px-4 py-2 bg-blue-600 dark:bg-blue-500 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-400"
          >
            Cerrar
          </button>
        </div>
      </div>
    </transiti -->
  </AppLayout>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
