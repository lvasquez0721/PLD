<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { FileText } from 'lucide-vue-next';
// import { Label } from '@/components/ui/label';
import {activeTab, setTab } from "../../../scripts/setTab.js";
import axios from 'axios'; // Para enviar al backend
import '@vuepic/vue-datepicker/dist/main.css';
import { router } from '@inertiajs/vue3'
import Toast from '@/components/ui/alert/Toast.vue'

// Tipado del buzon
interface BuzonItem {
  idBuzonPreocupantes: number
  IDReporteOP: string
  Fecha: string
  Descripcion: string
  Usuario: string
  Estatus: string
}

defineProps<{
  buzon: BuzonItem[]
  toast?: string
}>()

// IDs seleccionados
const seleccionados = ref<string[]>([]);
// Función para marcar/desmarcar
const toggleSeleccion = (id: string) => {
  if (seleccionados.value.includes(id)) {
    seleccionados.value = seleccionados.value.filter(item => item !== id);
  } else {
    seleccionados.value.push(id);
  }
};

// Función para enviar al backend
const pasarAlertas = async () => {
  if (seleccionados.value.length === 0) {
    alert('Selecciona al menos un reporte.');
    return;
  }

  try {
    const response = await axios.post('/buzon-preocupantes/pasar-alertas', {
      ids: seleccionados.value
    });

    // alert(response.data.message || 'Alertas generadas correctamente.');
    toastMessage.value = 'Alertas generadas correctamente.'
    toastType.value = 'success'
    showToast.value = true
    seleccionados.value = []; // limpiar selección
    // Refresca la página actual (vuelve a ejecutar el controlador index)
    router.reload()
    
  } catch (error: any) {
    console.error(error);
    // alert(error.response?.data?.error || 'Ocurrió un error al generar las alertas.');
    toastMessage.value = 'Ocurrió un error al generar las alertas. Verifica los datos ingresados.'
    toastType.value = 'error'
    showToast.value = true
  }
};

// Variable reactiva
const buscar = ref('')
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref<'success' | 'warning' | 'error'>('success')
const loading = ref(false)

const guardar = async () => {
  try {
    await axios.post('/buzon-preocupantes/guardar', {
      Descripcion: buscar.value,
    });
    loading.value = true

    setTimeout(() => {
      loading.value = false
    }, 1000)

    // Mostrar mensaje de éxito
    toastMessage.value = 'Reporte registrado correctamente.'
    toastType.value = 'success'
    showToast.value = true

    // Limpiar input
    buscar.value = ''

    // Recargar solo los datos del buzón después de un breve delay
    
    setTimeout(() => {
      router.reload({ only: ['buzon'] })
    }, 1000)

    // Cambiar a la pestaña "Buzón"
    setTab('altaListas')
  } catch (error) {
    // Mostrar error
    toastMessage.value = 'Error al registrar reporte. Verifica los datos ingresados.'
    toastType.value = 'error'
    showToast.value = true
    console.error(error)
  }
}

</script>

<style scoped>
/* Animación fade */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

/* Fondo con blur */
.loader-overlay {
  position: fixed;
  inset: 0;
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  transition: background-color 0.3s ease;
}

/* Modo claro */
.loader-overlay {
  background-color: rgba(255, 255, 255, 0.7);
}

.spinner {
  width: 55px;
  height: 55px;
  border: 5px solid rgba(0, 0, 0, 0.1);
  border-top: 5px solid #2563eb;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

/* Cuando la clase "dark" esté activa en el body o html */
:deep(html.dark) .loader-overlay,
:deep(body.dark) .loader-overlay {
  background-color: rgba(0, 0, 0, 0.6);
}

:deep(html.dark) .spinner,
:deep(body.dark) .spinner {
  border: 5px solid rgba(255, 255, 255, 0.1);
  border-top: 5px solid #60a5fa;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>

<template>
  <AppLayout title="Buzón de Operaciones Preocupantes">
    <div class="flex items-center justify-between">
      <Titulo :icon="FileText" title="Buzón de Operaciones Preocupantes" size="md" weight="bold" class="mb-4" />
    </div>

    <div class="border-b border-gray-300 mb-6 flex space-x-4">
      <button @click="setTab('altaListas')" :class="[
        'py-2 px-4 font-semibold border-b-4 transition cursor-pointer',
        activeTab === 'altaListas'
          ? 'border-[#8de9fb] text-[#8de9fb]'
          : 'border-transparent text-white hover:text-[#8de9fb]'
      ]">
        Buzón
      </button>
      <button @click="setTab('consulta')" :class="[
        'py-2 px-4 font-semibold border-b-4 transition cursor-pointer',
        activeTab === 'consulta'
          ? 'border-[#8de9fb] text-[#8de9fb]'
          : 'border-transparent text-white hover:text-[#8de9fb]'
      ]">
        Registrar Reporte
      </button> 
    </div>

     <!-- Tabla -->
    <form v-if="activeTab === 'altaListas'" @submit.prevent="pasarAlertas" class="space-y-4">
      <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow-lg">
        <table class="min-w-full border border-gray-300 dark:border-gray-700">
          <thead class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
            <tr>
              <th class="p-2 border dark:border-gray-600">Seleccionar</th>
              <th class="p-2 border dark:border-gray-600">ID</th>
              <!-- <th class="p-2 border dark:border-gray-600">Reporte OP</th> -->
              <th class="p-2 border dark:border-gray-600">Fecha</th>
              <th class="p-2 border dark:border-gray-600">Descripción</th>
              <!-- <th class="p-2 border dark:border-gray-600">Usuario</th> -->
              <!-- <th class="p-2 border dark:border-gray-600">Estatus</th> -->
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in buzon"
              :key="item.idBuzonPreocupantes"
              class="hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              <td class="border p-2 text-center dark:border-gray-600">
                <input
                  type="checkbox"
                  :value="item.IDReporteOP"
                  @change="toggleSeleccion(item.IDReporteOP)"
                  :checked="seleccionados.includes(item.IDReporteOP)"
                  class="w-4 h-4 accent-blue-600 cursor-pointer"
                />
              </td>
              <td class="border p-2 dark:border-gray-600">{{ item.idBuzonPreocupantes }}</td>
              <!-- <td class="border p-2 dark:border-gray-600">{{ item.IDReporteOP }}</td> -->
              <td class="border p-2 dark:border-gray-600">
                {{ new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(item.Fecha)) }}
              </td>
              <td class="border p-2 dark:border-gray-600">{{ item.Descripcion }}</td>
              <!-- <td class="border p-2 dark:border-gray-600">{{ item.Usuario }}</td> -->
              <!-- <td class="border p-2 dark:border-gray-600">{{ item.Estatus }}</td> -->
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-6 flex justify-end">
        <button
          type="submit"
          class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400 transition cursor-pointer"
        >
          Pasar alerta
        </button>
      </div>
    </form>

    <!-- Consulta -->
   <form v-if="activeTab === 'consulta'" class="space-y-4" @submit.prevent="guardar">
    <div>
      <Label for="buscar">Reportes:</Label>
      <input
        v-model="buscar"
        id="buscar"
        type="text"
        placeholder="Ingrese su reporte"
        class="w-full border border-gray-300 rounded px-3 py-2"
      />
    </div>
    <div class="mt-4 flex justify-end">
      <button
        type="submit"
        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition cursor-pointer"
      >
        Guardar
      </button>
    </div>
  </form>
  <Toast v-model="showToast" :message="toastMessage" :type="toastType" :duration="3000" />
  <!-- Loader -->
    <transition name="fade">
      <div v-if="loading" class="loader-overlay">
        <div class="spinner"></div>
      </div>
    </transition>
  
  </AppLayout>
</template>
