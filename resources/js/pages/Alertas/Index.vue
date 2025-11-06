<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { Bell } from 'lucide-vue-next';
import axios from 'axios';

const fechaInicio = ref('');
const fechaFin = ref('');
const alertas = ref([]);

const buscarAlertas = async () => {
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
  }
};

</script>

<template>
  <AppLayout title="Módulo de Alertas">
    <div class="flex items-center justify-between">
      <Titulo :icon="Bell" title="Módulo de Alertas" size="md" weight="bold" class="mb-2" />
    </div>
    <div class="flex items-center space-x-4 mb-4">
      <div>
        <label for="fechaInicio" class="block text-sm font-medium text-gray-700">Fecha inicio</label>
        <input type="date" id="fechaInicio" v-model="fechaInicio"
          class="p-2 border bg-white mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" />
      </div>
      <div>
        <label for="fechaFin" class="block text-sm font-medium text-gray-700">Fecha fin</label>
        <input type="date" id="fechaFin" v-model="fechaFin"
          class="p-2 border bg-white mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" />
      </div>
      <div>
        <button @click="buscarAlertas"
          class="block rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Buscar</button>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID
              Alerta</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Folio
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Patrón</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Nombre Cliente</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.
              Operación</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.
              Póliza</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha
              Detección</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha
              Operación</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora
              Operación</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.
              Movimiento</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Instrumento Monetario</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Agente</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Estatus</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Descripción</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Razones</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Evidencias</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID
              Reporte OP</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID
              Pago</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="alerta in alertas" :key="alerta.IDAlertas">
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.IDAlertas }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.Folio }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.Patron }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.Nombre }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.NoOperacion }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.NoPoliza }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.FechaDeteccion }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.Hora }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.FechaOperacion }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.HoraOperacion }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.NoMovimiento }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.Monto }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.InstrumentoMonetario }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.Agente }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.Estatus }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.Descripcion }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.Razones }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.Evidencias }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.IDReporteOP }}</td>
            <td class="px-6 py-4 whitespace-nowrap">{{ alerta.IDPago }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
