<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { FileText } from 'lucide-vue-next';
import { Label } from '@/components/ui/label';
import { activeTab, setTab } from "../../../scripts/setTab.js";
import DatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

// Inputs reactivos
const nombre = ref('');
const rfc = ref('');
const acuerdo = ref('');
const noOficio = ref('');
const anioLista = ref('');
const buscar = ref('');

// Fechas reactivas
const fechaNacimiento = ref<Date | null>(null);
const fechaAcuerdo = ref<Date | null>(null);

// Formateo dd-MM-yyyy
const formatFecha = (fecha: Date | null) => {
  if (!fecha) return '';
  const dia = String(fecha.getDate()).padStart(2, '0');
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const anio = fecha.getFullYear();
  return `${dia}-${mes}-${anio}`;
};

// Función submit
const guardar = () => {
  console.log('Nombre:', nombre.value);
  console.log('RFC:', rfc.value);
  console.log('Acuerdo:', acuerdo.value);
  console.log('No. Oficio:', noOficio.value);
  console.log('Año Lista:', anioLista.value);
  console.log('Fecha nacimiento:', formatFecha(fechaNacimiento.value));
  console.log('Fecha publicación acuerdo:', formatFecha(fechaAcuerdo.value));
};
</script>

<template>
  <AppLayout title="Alta en Listas UIF">
    <div class="flex items-center justify-between">
      <Titulo :icon="FileText" title="Alta en Listas UIF" size="md" weight="bold" class="mb-4" />
    </div>

    <div class="border-b border-gray-300 mb-6 flex space-x-4">
      <button @click="setTab('altaListas')" :class="[
        'py-2 px-4 font-semibold border-b-4 transition cursor-pointer',
        activeTab === 'altaListas'
          ? 'border-[#8de9fb] text-[#8de9fb]'
          : 'border-transparent text-white hover:text-[#8de9fb]'
      ]">
        Alta de listas
      </button>
      <button @click="setTab('consulta')" :class="[
        'py-2 px-4 font-semibold border-b-4 transition cursor-pointer',
        activeTab === 'consulta'
          ? 'border-[#8de9fb] text-[#8de9fb]'
          : 'border-transparent text-white hover:text-[#8de9fb]'
      ]">
        Consulta
      </button> 
    </div>

    <!-- Alta de listas -->
    <form v-if="activeTab === 'altaListas'" @submit.prevent="guardar" class="space-y-4">
      <div class="grid grid-cols-3 gap-4">
        <div>
          <Label for="nombre">Nombre / Razón social:</Label>
          <input v-model="nombre" id="nombre" type="text" placeholder="Ingrese el nombre o razón social" class="w-full border border-gray-300 rounded px-3 py-2" />
        </div>
        <div>
          <Label for="rfc">RFC / CURP:</Label>
          <input v-model="rfc" id="rfc" type="text" placeholder="Ingrese RFC o CURP" class="w-full border border-gray-300 rounded px-3 py-2" />
        </div>
        <div>
          <Label for="fecha-nac">Fecha nacimiento:</Label>
          <DatePicker 
            v-model="fechaNacimiento" 
            placeholder="dd-mm-yyyy"
            class="w-full"
            :enableTimePicker="false"
            :format="'dd-MM-yyyy'" 
            :editable="true"
            :closeOnSelect="true"
          />
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <Label for="fecha-acuerdo">Fecha publicación acuerdo:</Label>
          <DatePicker 
            v-model="fechaAcuerdo" 
            placeholder="dd-mm-yyyy"
            class="w-full"
            :enableTimePicker="false"
            :format="'dd-MM-yyyy'" 
            :editable="true"
            :closeOnSelect="true"
          />
        </div>
        <div>
          <Label for="acuerdo">Acuerdo:</Label>
          <input v-model="acuerdo" id="acuerdo" type="text" placeholder="Ingrese el número de acuerdo" class="w-full border border-gray-300 rounded px-3 py-2" />
        </div>
        <div>
          <Label for="no-oficio">No Oficio UIF:</Label>
          <input v-model="noOficio" id="no-oficio" type="text" placeholder="Ingrese número de oficio" class="w-full border border-gray-300 rounded px-3 py-2" />
        </div>
        
        <div>
          <Label for="anio-lista">Año Lista:</Label>
          <input v-model="anioLista" id="anio-lista" type="text" placeholder="Ingrese año de la lista" class="w-full border border-gray-300 rounded px-3 py-2" />
        </div>
      </div>

      <div class="mt-4 flex justify-end">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          Guardar
        </button>
      </div>
    </form>

    <!-- Consulta -->
    <form v-if="activeTab === 'consulta'" class="space-y-4">
      <div>
        <Label for="buscar">Buscar por Nombre, RFC o CURP:</Label>
        <input v-model="buscar" id="buscar" type="text" placeholder="Ingrese término de búsqueda" class="w-full border border-gray-300 rounded px-3 py-2" />
      </div>
      <div class="mt-4 flex justify-end">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          Buscar
        </button>
      </div>
    </form>
  </AppLayout>
</template>
