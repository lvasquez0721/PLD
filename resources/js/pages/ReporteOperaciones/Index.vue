<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { Label } from '@/components/ui/label';
import { Gavel } from 'lucide-vue-next';
import axios from 'axios';
import DatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const tipoOperacion = ref('');
const estatusOperacion = ref('');
const fechaInicial = ref<Date | null>(null);
const fechaFinal = ref<Date | null>(null);

const formatFecha = (fecha: Date | null) => {
  if (!fecha) return '';
  const dia = String(fecha.getDate()).padStart(2, '0');
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const anio = fecha.getFullYear();
  return `${dia}-${mes}-${anio}`;
};

// const buscar = () => {
//   console.log('Tipo de operación:', tipoOperacion.value);
//   console.log('Estatus:', estatusOperacion.value);
//   console.log('Fecha inicial:', formatFecha(fechaInicial.value));
//   console.log('Fecha final:', formatFecha(fechaFinal.value));     
// };

const buscar = () => {
  const params = {
    tipo: tipoOperacion.value,
    estatus: estatusOperacion.value,
    fecha_ini: formatFecha(fechaInicial.value),
    fecha_fin: formatFecha(fechaFinal.value)
  };

  axios.get('/reporte-operaciones/obtener', { params })
    .then(res => {
      console.log('Datos recibidos:', res.data);
    })
    .catch(err => {
      console.error('Error al obtener el reporte:', err);
    });
};

</script>

<template>
  <AppLayout title="Listas UIF">
    <div class="flex items-center justify-between">
      <Titulo :icon="Gavel" title="Listas UIF" size="md" weight="bold" class="mb-2" />
    </div>

    <h1 class="mb-4 text-lg font-semibold">Reporte de Operación Relevante, Inusual y Preocupante</h1>

    <form @submit.prevent="buscar" class="space-y-4">
      <div class="grid grid-cols-3 gap-4">
        <!-- Tipo de operación -->
        <div>
          <Label for="tipo-operacion">Tipo de operación:</Label>
          <select id="tipo-operacion" v-model="tipoOperacion" class="w-full border border-gray-300 rounded px-3 py-2">
            <option class="text-black" value="">Seleccione tipo de operación</option>
            <option class="text-black" value="relevante">Relevante</option>
            <option class="text-black" value="inusual">Inusual</option>
            <option class="text-black" value="preocupante">Preocupante</option>
          </select>
        </div>

        <!-- Estatus de operación -->
        <div>
          <Label for="estatus-operacion">Estatus Operación:</Label>
          <select id="estatus-operacion" v-model="estatusOperacion" class="w-full border border-gray-300 rounded px-3 py-2">
            <option class="text-black" value="">Seleccione estatus</option>
            <option class="text-black" value="pendiente">Pendiente</option>
            <option class="text-black" value="en-proceso">En proceso</option>
            <option class="text-black" value="finalizado">Finalizado</option>
          </select>
        </div>

        <!-- Rango de fechas -->
        <div>
          <Label>Rango de fechas:</Label>
          <div class="flex gap-2">
            <DatePicker 
              v-model="fechaInicial" 
              placeholder="Fecha inicial" 
              class="w-full" 
              :enableTimePicker="false"
              :format="'dd/MM/yyyy'"
            />
            <DatePicker 
              v-model="fechaFinal" 
              placeholder="Fecha final" 
              class="w-full" 
              :enableTimePicker="false"
              :format="'dd/MM/yyyy'" 
            />
          </div>
        </div>
      </div>

      <!-- Botón a la derecha -->
      <div class="mt-4 flex justify-end">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          Buscar
        </button>
      </div>
    </form>
  </AppLayout>
</template>
