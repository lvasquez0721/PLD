<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import Titulo from '@/components/ui/Titulo.vue';
import { FileText } from 'lucide-vue-next';
import { Label } from '@/components/ui/label';
import { activeTab, setTab } from "../../../scripts/setTab.js";
import DatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { type BreadcrumbItem } from '@/types'
import { Settings } from 'lucide-vue-next'

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Alta en listas UIF',
        href: '',
    },
]
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
// const guardar = () => {
//   console.log('Nombre:', nombre.value);
//   console.log('RFC:', rfc.value);
//   console.log('Acuerdo:', acuerdo.value);
//   console.log('No. Oficio:', noOficio.value);
//   console.log('Año Lista:', anioLista.value);
//   console.log('Fecha nacimiento:', formatFecha(fechaNacimiento.value));
//   console.log('Fecha publicación acuerdo:', formatFecha(fechaAcuerdo.value));
// };

const guardar = () => {
    const datos = {
        nombre: nombre.value,
        RFCCURP: rfc.value,
        fechaNacimiento: formatFecha(fechaNacimiento.value),
        fechaPublicacionAcuerdo: formatFecha(fechaAcuerdo.value),
        acuerdo: acuerdo.value,
        noOficioUIF: noOficio.value,
    };

    axios.post('/listas-uif/altaListas', datos)
        .then(response => {
            console.log('Datos guardados exitosamente:', response.data);
        })
        .catch(error => {
            console.error('Error al guardar los datos:', error);
        });

    axios.post('/listas-uif/bajaListas', datos)
        .then(response => {
            console.log('Datos guardados exitosamente:', response.data);
        })
        .catch(error => {
            console.error('Error al guardar los datos:', error);
        });

    axios.post('/listas-uif/actualizaListas', datos)
        .then(response => {
            console.log('Datos guardados exitosamente:', response.data);
        })
        .catch(error => {
            console.error('Error al guardar los datos:', error);
        });

    axios.get('/listas-uif/consultaListas', { params: datos })
        .then(response => {
            console.log('Datos consultados exitosamente:', response.data);
        })
        .catch(error => {
            console.error('Error al consultar los datos:', error);
        });


};

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">


        <!-- Tabs -->
        <div
            class="flex gap-1 mb-8 p-1 rounded-xl border border-slate-100 bg-gradient-to-r from-white via-slate-50/70 to-white shadow-sm backdrop-blur-sm dark:border-neutral-800 dark:bg-gradient-to-r dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90 w-fit transition-all duration-700">
            <button @click="setTab('altaListas')" :class="[
                'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 ease-out',
                activeTab === 'altaListas'
                    ? 'bg-blue-600 text-white shadow-md'
                    : 'text-slate-700 dark:text-neutral-300 hover:bg-slate-100 dark:hover:bg-neutral-800'
            ]">
                Alta de listas
            </button>
            <button @click="setTab('consulta')" :class="[
                'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 ease-out',
                activeTab === 'consulta'
                    ? 'bg-blue-600 text-white shadow-md'
                    : 'text-slate-700 dark:text-neutral-300 hover:bg-slate-100 dark:hover:bg-neutral-800'
            ]">
                Consulta de listas
            </button>
        </div>

        <!-- Alta de listas -->
        <div
            class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <form v-if="activeTab === 'altaListas'" @submit.prevent="guardar" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label for="nombre"
                            class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Nombre / Razón
                            social:</Label>
                        <input v-model="nombre" id="nombre" type="text" placeholder="Ingrese el nombre o razón social"
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900" />
                    </div>
                    <div>
                        <Label for="rfc" class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">RFC
                            / CURP:</Label>
                        <input v-model="rfc" id="rfc" type="text" placeholder="Ingrese RFC o CURP"
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900" />
                    </div>
                    <div>
                        <Label for="fecha-nac"
                            class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Fecha
                            nacimiento:</Label>
                        <DatePicker v-model="fechaNacimiento" placeholder="dd-mm-yyyy"
                            input-class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                            :enableTimePicker="false" :format="'dd-MM-yyyy'" :editable="true" :closeOnSelect="true" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label for="fecha-acuerdo"
                            class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Fecha
                            publicación acuerdo:</Label>
                        <DatePicker v-model="fechaAcuerdo" placeholder="dd-mm-yyyy"
                            input-class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900"
                            :enableTimePicker="false" :format="'dd-MM-yyyy'" :editable="true" :closeOnSelect="true" />
                    </div>
                    <div>
                        <Label for="acuerdo"
                            class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Acuerdo:</Label>
                        <input v-model="acuerdo" id="acuerdo" type="text" placeholder="Ingrese el número de acuerdo"
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900" />
                    </div>
                    <div>
                        <Label for="no-oficio"
                            class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">No Oficio
                            UIF:</Label>
                        <input v-model="noOficio" id="no-oficio" type="text" placeholder="Ingrese número de oficio"
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900" />
                    </div>

                    <div>
                        <Label for="anio-lista"
                            class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Año
                            Lista:</Label>
                        <input v-model="anioLista" id="anio-lista" type="text" placeholder="Ingrese año de la lista"
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
        <!-- Consulta -->
        <div
            class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)] p-6">
            <form v-if="activeTab === 'consulta'" @submit.prevent="guardar" class="space-y-4">
                <div>
                    <Label for="buscar"
                        class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1 block">Buscar por Nombre,
                        RFC o CURP:</Label>
                    <input v-model="buscar" id="buscar" type="text" placeholder="Ingrese término de búsqueda"
                        class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900" />
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Buscar
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
