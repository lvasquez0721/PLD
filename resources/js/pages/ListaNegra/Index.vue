<script setup lang="ts">
  import { ref, computed, onMounted } from 'vue';
  import { Head, usePage, router } from '@inertiajs/vue3';
  import AppLayout from '@/layouts/AppLayout.vue';
  import Titulo from '@/components/ui/Titulo.vue';
  import { ListX } from 'lucide-vue-next';

  const breadcrumbs = [{ title: 'Lista Negra', href: '' }];

  const showModal = ref(false);
  const selectedId = ref<number | null>(null);

  // Estructura del formulario
  interface FormData {
    accion: number; // 1 = editar, 2 = eliminar, 3 = insertar
    nombreListaNegra: string;
    RFCListaNegra: string;
    CURPListaNegra: string;
    Fecha_NacimientoListaNegra: string;
    paisOtro: string;
    paisListaNegra: string;
    archivoListaNegra: File | null;
  }

  const form = ref<FormData>({
    accion: 3,
    nombreListaNegra: '',
    RFCListaNegra: '',
    CURPListaNegra: '',
    Fecha_NacimientoListaNegra: '',
    paisOtro: '',
    paisListaNegra: '',
    archivoListaNegra: null,
  });

  // Manejadores de archivo y modal
  function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
      form.value.archivoListaNegra = target.files[0];
    } else {
      form.value.archivoListaNegra = null;
    }
  }

  function resetForm() {
    form.value = {
      accion: 3,
      nombreListaNegra: '',
      RFCListaNegra: '',
      CURPListaNegra: '',
      Fecha_NacimientoListaNegra: '',
      paisOtro: '',
      paisListaNegra: '',
      archivoListaNegra: null,
    };
    selectedId.value = null;
  }

  function closeModal() {
    showModal.value = false;
    resetForm();
  }

  // Modal Agregar
  function openAddModal() {
    resetForm();
    form.value.accion = 3;
    showModal.value = true;
  }

  // Modal Editar
  function openEditModal(item: any) {
    form.value.accion = 1;
    selectedId.value = item.IDRegistroListaCNSF;
    form.value.nombreListaNegra = item.Nombre;
    form.value.RFCListaNegra = item.RFC;
    form.value.CURPListaNegra = item.CURP;
    form.value.Fecha_NacimientoListaNegra = item.FechaNacimiento;
    form.value.paisOtro = item.Pais === 'MEXICO' ? 'MEXICO' : 'Otro';
    form.value.paisListaNegra = item.Pais !== 'MEXICO' ? item.Pais : '';
    showModal.value = true;
  }

  // Modal Eliminar
  function openDeleteModal(item: any) {
    form.value.accion = 2;
    selectedId.value = item.IDRegistroListaCNSF;
    showModal.value = true;
    form.value.nombreListaNegra = item.Nombre;
    form.value.RFCListaNegra = item.RFC;
    form.value.CURPListaNegra = item.CURP;
  }

  // Texto dinámico del modal y botón
  const modalTitle = computed(() => {
    switch (form.value.accion) {
      case 1:
        return 'Actualizar Registro';
      case 2:
        return 'Eliminar Registro';
      case 3:
        return 'Agregar Registro';
      default:
        return 'Registro';
    }
  });

  const actionButtonText = computed(() => {
    switch (form.value.accion) {
      case 1:
        return 'Actualizar';
      case 2:
        return 'Eliminar';
      case 3:
        return 'Guardar';
      default:
        return 'Aceptar';
    }
  });

  // Envío del formulario
  function submitForm() {
    const formData = new FormData();
    formData.append('nombre', form.value.nombreListaNegra);
    formData.append('rfc', form.value.RFCListaNegra);
    formData.append('curp', form.value.CURPListaNegra);
    formData.append('fecha_nacimiento', form.value.Fecha_NacimientoListaNegra);
    const pais = form.value.paisOtro === 'Otro' ? form.value.paisListaNegra : form.value.paisOtro;
    formData.append('pais', pais);
    if (form.value.archivoListaNegra) {
      formData.append('archivo', form.value.archivoListaNegra);
    }

    // Determinar URL según acción
    let url = '';
    if (form.value.accion === 1) {
      url = `/lista-negra/update/${selectedId.value}`;
    } else if (form.value.accion === 2) {
      url = `/lista-negra/delete/${selectedId.value}`;
    } else {
      url = `/lista-negra/insert`;
    }

    // Enviar datos
    router.post(url, formData, {
      onStart: () => console.log(`Ejecutando acción ${form.value.accion}...`),
      onSuccess: () => {
        console.log('Operación exitosa');
        closeModal();
        router.reload();
      },
      onError: (errors) => {
        console.error('Errores de validación:', errors);
      },
    });
  }

  // Datos del backend
  interface ListaNegra {
    IDRegistroListaCNSF: number;
    Nombre: string;
    RFC: string;
    CURP: string;
    FechaNacimiento: string;
    Pais: string;
    Oficio: string;
  }

  const page = usePage();
  const listas = computed(() => (page.props.listas as ListaNegra[]) ?? []);

  // M
  onMounted(() => {
    console.log('Datos recibidos:', listas.value);
  });
</script>


<template>
  <AppLayout title="Lista Negra">
    <div class="flex items-center justify-between">
      <Titulo :icon="ListX" title="Lista Negra" size="md" weight="bold" class="mb-2" />
    </div>

    <section class="content">  <!-- vista  -->
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

            <p id="query-results"></p>
            <div style="text-align: right;">
              <div class="flex justify-end mb-4">
                <button @click="openAddModal" class="px-6 py-2 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-400 dark:focus:ring-blue-500 transition-all"> + </button>
              </div>
            </div>

            <br>

            <table id="table-lista-negra-cnsf" class="w-full border-collapse text-sm">
              <thead>
                <tr class="bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                  <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-left">#</th>
                  <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-left">Nombre</th>
                  <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-left">RFC</th>
                  <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-left" style="display: flex; justify-content: center; align-items: center;">Acciones</th>
                </tr>
              </thead>
              <tbody v-if="listas.length" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <tr v-for="item in listas" :key="item.IDRegistroListaCNSF">
                  <td>{{ item.IDRegistroListaCNSF }}</td>
                  <td>{{ item.Nombre }}</td>
                  <td>{{ item.RFC }}</td>
                  <td class="text-center">
                    <button @click="openEditModal(item)" class="px-2 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 transition">✏️</button>
                    <button @click="openDeleteModal(item)" class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition ml-2">🗑️</button>
                  </td>
                </tr>
              </tbody>
              <tbody v-else>
                <tr><td colspan="4" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">No hay datos disponibles</td></tr>
              </tbody>
            </table>

          </div>
        </div>
      </div>

    </section>

    <!-- Modal -->
    <transition name="fade-in">
      <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg w-96 max-w-full p-6 relative">
          <h2 class="text-xl font-semibold mb-4">{{ modalTitle }}</h2>

          <!-- Si es eliminar -->
          <div v-if="form.accion === 2">
            <p>¿Estás segura de eliminar al usuario <strong>{{ form.nombreListaNegra }}</strong> con RFC <strong>{{ form.RFCListaNegra }}</strong> de la lista negra?</p>
          </div>

          <!-- Si es agregar o editar -->
          <form v-else @submit.prevent="submitForm" class="flex flex-col gap-3">
            <input type="hidden" v-model="form.accion" /> <!--eliminar/agregar/editar -->
            <!-- <input type="hidden" v-model="form.IDRegistroListaCNSF" >  -->
            <div>
              <label>Nombre</label>
              <input v-model="form.nombreListaNegra" placeholder="Ingresa el Nombre Completo" type="text" class="form-input" />
            </div>
            <div>
              <label>RFC</label>
              <input v-model="form.RFCListaNegra" placeholder="Ingresa el RFC" type="text" class="form-input" />
            </div>
            <div>
              <label>CURP</label>
              <input v-model="form.CURPListaNegra" placeholder="Ingresa el CURP" type="text" class="form-input" />
            </div>
            <div>
              <label>Fecha de Nacimiento</label>
              <input v-model="form.Fecha_NacimientoListaNegra" id="Fecha_NacimientoListaNegra" type="date" class="form-input" />
            </div>
            <div>
              <label>País</label>
              <select v-model="form.paisOtro" class="form-input">
                <option value="">Seleccione una opción</option>
                <option value="MEXICO">MEXICO</option>
                <option value="Otro">Otro</option>
              </select>
              <input v-if="form.paisOtro === 'Otro'" v-model="form.paisListaNegra" placeholder="Especifique el país" class="form-input mt-2" />
            </div>
            <div>
              <label>Subir Oficio</label>
              <input @change="handleFileChange" type="file" accept="application/pdf, application/x-pdf, application/acrobat, applications/pdf, text/pdf, application/vnd.pdf" class="form-file" />
            </div>
          </form>

          <div class="flex justify-end gap-2 mt-4">
            <button @click="closeModal"  class="px-4 py-2 rounded-lg bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-100 hover:bg-gray-400 dark:hover:bg-gray-500 transition">Cancelar</button>
            <button @click="submitForm" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800 transition-all">{{ actionButtonText }}</button>
          </div>
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
