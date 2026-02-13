<script setup lang="ts">
import { ref } from 'vue';
import { Label } from '@/components/ui/label';
import { FileText } from 'lucide-vue-next';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
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

const breadcrumbs = [{ title: 'Reporte de operaciones', href: '' }];

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
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
    </div>

    <h1 class="mb-4 text-lg font-semibold text-slate-800 dark:text-neutral-200">Reporte de Operación Relevante, Inusual y Preocupante</h1>

    <div class="mt-6 flex flex-col gap-4 rounded-xl border border-slate-100 bg-gradient-to-r from-white/90 via-slate-50/70 to-white/90 p-4 shadow-sm backdrop-blur-sm transition-colors duration-200 ease-out focus-within:border-blue-400/80 focus-within:shadow-[0_0_0_1px_rgba(59,130,246,0.3)] dark:border-neutral-800/80 dark:bg-gradient-to-r dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90">
      <form @submit.prevent="buscar" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Tipo de operación -->
          <div>
            <Label for="tipo-operacion" class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Tipo de operación:</Label>
            <select id="tipo-operacion" v-model="tipoOperacion" class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900">
              <option value="">Seleccione tipo de operación</option>
              <option value="relevante">Relevante</option>
              <option value="inusual">Inusual</option>
              <option value="preocupante">Preocupante</option>
            </select>
          </div>

          <!-- Estatus de operación -->
          <div>
            <Label for="estatus-operacion" class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Estatus Operación:</Label>
            <select id="estatus-operacion" v-model="estatusOperacion" class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900">
              <option value="">Seleccione estatus</option>
              <option value="pendiente">Pendiente</option>
              <option value="en-proceso">En proceso</option>
              <option value="finalizado">Finalizado</option>
            </select>
          </div>

          <!-- Rango de fechas -->
          <div>
            <Label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Rango de fechas:</Label>
            <div class="flex gap-2">
              <DatePicker
                v-model="fechaInicial"
                placeholder="Fecha inicial"
                input-class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                :enableTimePicker="false"
                :format="'dd/MM/yyyy'"
              />
              <DatePicker
                v-model="fechaFinal"
                placeholder="Fecha final"
                input-class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                :enableTimePicker="false"
                :format="'dd/MM/yyyy'"
              />
            </div>
          </div>
        </div>

        <!-- Botón a la derecha -->
        <div class="mt-4 flex justify-end">
          <button type="submit" class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            Buscar
          </button>
        </div>
      </form>
    </div>  </AppLayout>
</template>
