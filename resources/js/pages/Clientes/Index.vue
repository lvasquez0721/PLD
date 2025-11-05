<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Titulo from '@/components/ui/Titulo.vue'
import Toast from '@/components/ui/alert/Toast.vue'
import { type BreadcrumbItem } from '@/types'
import { Gavel } from 'lucide-vue-next'

// Props que vienen del controlador Laravel
const props = defineProps<{
  clientes: any[],
  toast?: { type: string, message: string }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Clientes', href: '/clientes' },
]

// Variables reactivas
const busqueda = ref('')
const showModal = ref(false)
const clienteSeleccionado = ref<any | null>(null)

// Métodos
const clientesFiltrados = computed(() => {
  const term = busqueda.value.toLowerCase().trim()
  if (!term) return props.clientes

  return props.clientes.filter(c => {
    const nombreCompleto = `${c.Nombre || ''} ${c.ApellidoPaterno || ''} ${c.ApellidoMaterno || ''}`.trim()
    
    return nombreCompleto.toLowerCase().includes(term) ||
           (c.RFC || '').toLowerCase().includes(term) ||
           (c.CURP || '').toLowerCase().includes(term) ||
           (c.RazonSocial || '').toLowerCase().includes(term)
  })
})

function abrirModal(cliente: any) {
  clienteSeleccionado.value = cliente
  showModal.value = true
}

function cerrarModal() {
  showModal.value = false
  clienteSeleccionado.value = null
}

function descargarBasePersonas() {
  // Simula descarga (puedes reemplazar con tu endpoint real)
  window.open('/ruta/descargar/base/personas', '_blank')
}
</script>

<template>
  <Head title="Clientes" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <Titulo :icon="Gavel" title="Consulta de Clientes" />

    <div class="mt-6">
      <input
        v-model="busqueda"
        type="text"
        class="form-control w-full md:w-1/2 mb-4"
        placeholder="Búsqueda general (nombre, RFC, CURP, etc.)"
      />
    </div>

    <div class="overflow-x-auto border rounded-lg bg-white dark:bg-gray-900 p-4">
      <h4 class="font-semibold mb-2">Código de color categorías PLD</h4>
      <table class="table-auto border-collapse w-full text-sm">
        <thead>
          <tr class="bg-gray-200 dark:bg-gray-700">
            <th class="border p-2 w-24">Color</th>
            <th class="border p-2">Descripción</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="border p-2">&nbsp;</td>
            <td class="border p-2">Sin coincidencia en listas</td>
          </tr>
          <tr>
            <td class="border p-2 bg-red-600">&nbsp;</td>
            <td class="border p-2">Persona / Empresa bloqueada</td>
          </tr>
          <tr>
            <td class="border p-2 bg-orange-500">&nbsp;</td>
            <td class="border p-2">Aparece en listas bloqueadas, necesita revisión</td>
          </tr>
          <tr>
            <td class="border p-2 bg-sky-500">&nbsp;</td>
            <td class="border p-2">Persona Políticamente Expuesta (PPE)</td>
          </tr>
          <tr>
            <td class="border p-2 bg-indigo-400">&nbsp;</td>
            <td class="border p-2">PPE, necesita revisión</td>
          </tr>
          <tr>
            <td class="border p-2 bg-yellow-300">&nbsp;</td>
            <td class="border p-2">Autorizada que aparece en listas</td>
          </tr>
          <tr>
            <td class="border p-2 bg-purple-500">&nbsp;</td>
            <td class="border p-2">Fuera de categoría Tláloc, necesita revisión</td>
          </tr>
          <tr>
            <td class="border p-2 bg-rose-400">&nbsp;</td>
            <td class="border p-2">Detectada en listas internas (oficios CNSF)</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex justify-start mt-6">
      <button
        class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded"
        @click="descargarBasePersonas"
      >
        Descargar Base Personas
      </button>
    </div>

    <!-- Listado de clientes -->
    <div class="mt-8 border rounded-lg p-4 overflow-y-auto">
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="bg-gray-200 dark:bg-gray-700">
            <th class="border p-2">Nombre</th>
            <th class="border p-2">RFC</th>
            <th class="border p-2">CURP</th>
            <th class="border p-2">Tipo</th>
            <th class="border p-2">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="cliente in clientesFiltrados" 
            :key="cliente.IDCliente"
            class="hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
          >
            <td class="border p-2">
              {{ cliente.Nombre }} {{ cliente.ApellidoPaterno }} {{ cliente.ApellidoMaterno }}
            </td>
            <td class="border p-2">{{ cliente.RFC }}</td>
            <td class="border p-2">{{ cliente.CURP }}</td>
            <td class="border p-2">
              {{ cliente.IDTipoPersona === 1 ? 'Física' : 'Moral' }}
            </td>
            <td class="border p-2 text-center">
              <button
                class="text-blue-600 hover:underline"
                @click="abrirModal(cliente)"
              >
                Ver Detalle
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg w-2/3 p-6 relative">
        <button
          class="absolute top-2 right-3 text-gray-500 hover:text-gray-800"
          @click="cerrarModal"
        >
          ✕
        </button>
        <h3 class="text-lg font-bold mb-4">Datos Registrados</h3>
        <div v-if="clienteSeleccionado">
          <p><strong>Nombre:</strong> {{ clienteSeleccionado.Nombre }} {{ clienteSeleccionado.ApellidoPaterno }} {{ clienteSeleccionado.ApellidoMaterno }}</p>
          <p><strong>RFC:</strong> {{ clienteSeleccionado.RFC }}</p>
          <p><strong>CURP:</strong> {{ clienteSeleccionado.CURP }}</p>
          <p><strong>Razón Social:</strong> {{ clienteSeleccionado.RazonSocial }}</p>
        </div>
      </div>
    </div>

    <Toast v-if="props.toast" :type="props.toast.type" :message="props.toast.message" />
  </AppLayout>
</template>