<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'; 
import AppLayout from '@/layouts/AppLayout.vue'; // Importa la estructura general de la página
//import Datatable from '@/components/ui/tables/Datatable.vue'; // Importa el componente de tabla de datos
//import FadeIn from '@/components/ui/animation/fadeIn.vue'; // Importa el componente de animación FadeIn
//import Toast from '@/components/ui/alert/Toast.vue'; // Importa el componente de notificaciones Toast
import { Head, usePage, router } from '@inertiajs/vue3'; // manejar la navegación y acceder a los datos enviados desde el backend de forma reactiva.
import Titulo from '@/components/ui/Titulo.vue'; // Importa el componente de título
import { ListX } from 'lucide-vue-next'; 

// Define la ruta de navegación que aparece en la parte superior de la página.
const breadcrumbs = [
  { title: 'Lista Negra', href: '' },
];

//! Inicio Modal Configuración
// Mostrar modal
const showModal = ref(false);

// Definimos la interfaz del formulario
interface FormData {
  nombreListaNegra: string;
  RFCListaNegra: string;
  CURPListaNegra: string;
  Fecha_NacimientoListaNegra: string;
  paisOtro: string;
  paisListaNegra: string;
  archivoListaNegra: File | null;
}

// Inicializamos el formulario con ref y tipado
const form = ref<FormData>({
  nombreListaNegra: '',
  RFCListaNegra: '',
  CURPListaNegra: '',
  Fecha_NacimientoListaNegra: '',
  paisOtro: '',
  paisListaNegra: '',
  archivoListaNegra: null,
});

// Manejo del archivo
function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    form.value.archivoListaNegra = target.files[0];
  } else {
    form.value.archivoListaNegra = null;
  }
}

// Abrir modal
function openModal() {
  showModal.value = true;
}

// Cerrar modal y resetear formulario
function closeModal() {
  showModal.value = false;
  form.value = {
    nombreListaNegra: '',
    RFCListaNegra: '',
    CURPListaNegra: '',
    Fecha_NacimientoListaNegra: '',
    paisOtro: '',
    paisListaNegra: '',
    archivoListaNegra: null,
  };
}
// Fin Modal Configuración


//Enviar formulario
function submitForm() {
  // Creamos FormData
  const formData = new FormData();

  // Campos del formulario
  formData.append('nombre', form.value.nombreListaNegra);
  formData.append('rfc', form.value.RFCListaNegra);
  formData.append('curp', form.value.CURPListaNegra);
  formData.append('fecha_nacimiento', form.value.Fecha_NacimientoListaNegra);

  // Manejo del país (si es 'Otro' usamos el input de texto)
  const pais = form.value.paisOtro === 'Otro' ? form.value.paisListaNegra : form.value.paisOtro;
  formData.append('pais', pais);

  // Archivo (solo si hay)
  if (form.value.archivoListaNegra) {
    formData.append('archivo', form.value.archivoListaNegra);
  }

  // Enviar a Laravel usando Inertia
  router.post('/lista-negra/insert', formData, {
    onStart: () => console.log('Enviando datos...'),
    onSuccess: () => {
      console.log('Registro guardado correctamente');
      closeModal(); // Cerramos modal
      router.reload(); // Recargamos la página para actualizar la lista
    },
    onError: (errors) => {
      console.error('Errores de validación:', errors);
    }
  });
}


// Definimos el tipo de datos que viene desde Laravel
interface ListaNegra {
  IDRegistroListaCNSF: number
  Nombre: string
  RFC: string
  CURP: string
  Fecha_Nacimiento: string
  Pais: string
  Oficio: string
}

// Acceso a los datos enviados desde Laravel
const page = usePage()
const listas = computed(() => (page.props.listas as ListaNegra[]) ?? [])

// Ver los datos en consola al montar el componente
onMounted(() => {
  console.log('Datos recibidos desde Laravel:', listas.value)
})


</script>

<template>
  <AppLayout title="Lista Negra">
    <div class="flex items-center justify-between">
      <Titulo :icon="ListX" title="Lista Negra" size="md" weight="bold" class="mb-2" />
    </div>

    <!-- Contenido de la vista Lista Negra -->
    <br />

    <section class="content "> <!-- vista  -->

      <div class="row"> <!-- alertas -->
        <div class="alert alert-success" id="compSuccessGlobal" style="display: none;">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <span></span>
        </div>
        <div class="alert alert-danger" id="compErrorGlobal" style="display: none;">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <span></span>
        </div>
        <div class="alert alert-warning" id="compWarningGlobal" style="display: none;">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <span></span>
        </div>
      </div>
      
      <div class="flex flex-col gap-4 mb-4">  <!-- filtrar y buscar -->
        <!-- Select cantidad de elementos -->
        <div class="flex flex-col w-64">
          <label for="select-page-size" class="text-gray-700 dark:text-gray-300 font-medium mb-1"> Número de elementos </label>
          <select  id="select-page-size" class="w-full border rounded-lg px-3 py-2">
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="-1">Todos</option>
          </select>
        </div>

        <!-- Buscar persona -->
        <div class="flex flex-col w-64">
          <label for="buscar-nombres" class="text-gray-700 dark:text-gray-300 font-medium mb-1"> Buscar una persona </label>
          <input id="buscar-nombres" type="text" placeholder="Nombre o RFC" class="w-full border rounded-lg px-4 py-2" />
        </div>
      </div>

      <div class="row" >
        <div class="col-md-12">
          <div class="table-responsive">
            <!-- Pagination -->
            <!-- <div class="pagination-container">
              <ul class="pagination"></ul>
            </div> -->

            <p id="query-results"></p>
            <div style="text-align: right;">
              <!-- <button type="button" id="btn_MoadalAgregar" class="btn btn-primary ml-4" data-bs-toggle="modal" data-bs-target="#generalModal">+</button> -->
               <button @click="openModal" class="px-6 py-2 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-400 dark:focus:ring-blue-500 transition-all" > + </button>
            </div>

            <br>

            <table id="table-lista-negra-cnsf" class="w-full border-collapse text-sm">
              <thead>
                <tr class="bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                  <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-left">#</th>
                  <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-left">Nombres</th>
                  <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-left">RFC</th>
                  <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-left" style="display: flex; justify-content: center; align-items: center;">.:.</th>
                </tr>
              </thead>
             
              <tbody v-if="listas && listas.length" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <tr v-for="item in listas" :key="item.IDRegistroListaCNSF">
                  <td>{{ item.IDRegistroListaCNSF }}</td>
                  <td>{{ item.Nombre }}</td>
                  <td>{{ item.RFC }}</td>
                  <td></td>
                </tr>
              </tbody>

              <tbody v-else class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <tr>
                  <td colspan="3" class="text-center py-4 text-gray-500">
                    No hay datos disponibles
                  </td>
                </tr>
              </tbody>

            </table>
          </div>
        </div>
      </div>

    </section>
    
    <!-- Modal AGREGAR/EDITAR -->
    <transition name="fade-in">
      <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg w-96 max-w-full p-6 relative">
      
          <!-- Título del modal -->
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4"  id="generalModalLabel">AGREGAR/EDITAR</h2>

          <div class="form-group">
            <!-- seccion de alertas modal -->
          </div>
                          
          <!-- Formulario simple -->
          <form @submit.prevent="submitForm" class="flex flex-col gap-3">

            <input type="hidden" id="accion" name="accion" value="listar"> <!--eliminar/agregar/editar -->

            <div class="form-group" id="CamposEliminar">
              <p id="modalContentEliminacion">_</p>
            </div>
                   
            <input type="hidden" id="IDRegistro"> <!--identificar la fila selecionda  -->

            <div id="CamposAgregar_Editar">
           
              <div class="form-group">
                <label for="nombre">Nombre</label>
                <input v-model="form.nombreListaNegra" type="text" id="nombreListaNegra" placeholder="Ingresa el Nombre Completo" class="form-input">
              </div>
              
              <div class="form-group">
                <label for="RFC">RFC</label>
                <input v-model="form.RFCListaNegra" type="text" id="RFCListaNegra" placeholder="Ingresa el RFC" class="form-input">
              </div>

              <div class="form-group">
                <label for="CURP">CURP</label>
                <input v-model="form.CURPListaNegra" type="text" id="CURPListaNegra" placeholder="Ingresa el CURP" class="form-input">
              </div>

              <div class="form-group">
                <label for="fecha"> Fecha de Nacimiento</label>
                <input v-model="form.Fecha_NacimientoListaNegra" type="date" id="Fecha_NacimientoListaNegra" placeholder="Ingresa la fecha de Naciemiento"
                  class="form-input">
              </div>
              <div class="form-group">
                <label class="form-label">Pais</label>
                <select  v-model="form.paisOtro" id="paisOtro" class="form-input">
                  <option value="" disabled selected>Seleccione una opción</option>
                  <option value="MEXICO">MEXICO</option>
                  <option value="Otro">Otro</option>
                </select>

                <!-- Campo de texto que permite escribir si se selecciona "Otro" -->
                <!-- <input v-model="form.paisListaNegra" type="text" id="paisListaNegra" placeholder="Especifique el pa&iacute;s" style="display: none;" class="form-input"> -->
                <input v-if="form.paisOtro === 'Otro'" v-model="form.paisListaNegra" type="text" id="paisListaNegra" placeholder="Especifique el país" class="form-input mt-2">

              </div>
            </div>

            <div class="form-group">
              <label for="archivo">Subir Oficio</label>
              <input @change="handleFileChange" type="file" id="archivoListaNegra" name="archivoListaNegra" accept="application/pdf, application/x-pdf, application/acrobat, applications/pdf, text/pdf, application/vnd.pdf" class="form-file">
            </div>
            <br>

            <div class="flex justify-end gap-2 mt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 rounded-lg bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-100 hover:bg-gray-400 dark:hover:bg-gray-500 transition"> Cancelar </button>
              <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800 transition-all"> Guardar </button>
            </div>

          </form>
        </div>
      </div>
    </transition>

  </AppLayout>
</template>

<!-- //! Estilos para los inputs/files del formulario -->
<style scoped>
  /* Inputs de texto, número, fecha */
  .form-input, .form-file {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #D1D5DB;
    border-radius: 0.5rem;
    transition: all 0.2s ease-in-out;
    outline: none;
    background-color: #FFF;
    color: #1F2937;
  }

  .form-input::placeholder { color: #9CA3AF; }

  .form-input:hover, .form-file:hover { box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
  .form-input:focus { border-color: #60A5FA; box-shadow: 0 0 0 2px rgba(96,165,250,0.3); }

  /* Input file */
  .form-file {
    cursor: pointer;
    background-color: #F3F4F6;
  }
  .form-file::file-selector-button {
    margin-right: 1rem;
    padding: 0.35rem 0.75rem;
    border: none;
    border-radius: 0.375rem;
    background-color: #6B7280;
    color: #FFF;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
  }
  .form-file:hover::file-selector-button { background-color: #4B5563; }

  /* Modo oscuro */
  .dark .form-input, .dark .form-file {
    background-color: #374151;
    border-color: #4B5563;
    color: #F9FAFB;
  }
  .dark .form-input::placeholder { color: #FFF; } /* Placeholder blanco en dark */
  .dark .form-input:focus, .dark .form-file:focus {
    border-color: #3B82F6;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.3);
  }
  .dark .form-file::file-selector-button { background-color: #4B5563; }
  .dark .form-file:hover::file-selector-button { background-color: #6B7280; }
</style>
