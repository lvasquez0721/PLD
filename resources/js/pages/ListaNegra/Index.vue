<script setup lang="ts">
  import { ref, computed, onMounted, watch } from 'vue';
  import { Head, usePage, router } from '@inertiajs/vue3';
  import AppLayout from '@/layouts/AppLayout.vue';
  import Titulo from '@/components/ui/Titulo.vue';
  import { ListX } from 'lucide-vue-next';

  const breadcrumbs = [{ title: 'Lista Negra', href: '' }];

  const showModal = ref(false);
  const selectedId = ref<number | null>(null);
  const mostrarError = ref(false);
  const codigoError = ref('');
  const listaErrores = ref('');
  const erroresValidacion = ref<Record<string, string>>({});
  const mostrarAlertaErrores = ref(false);

  interface FormData {
    accion: number;
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

  function validarCampos(): boolean {
    erroresValidacion.value = {};
    if (form.value.accion === 2) { 
      if (!form.value.archivoListaNegra) {
        erroresValidacion.value.archivoListaNegra = 'Debe ingresar el archivo correspondiente para poder eliminar el registro';
      }
    } else {
      if (!form.value.nombreListaNegra.trim()) erroresValidacion.value.nombreListaNegra = 'Ingrese el nombre de la persona en Listas Negra';
      if (!form.value.RFCListaNegra.trim()) erroresValidacion.value.RFCListaNegra = 'El RFC es un campo obligatorio';
      if (!form.value.CURPListaNegra.trim()) erroresValidacion.value.CURPListaNegra = 'La CURP es un campo obligatorio';
      if (!form.value.Fecha_NacimientoListaNegra) erroresValidacion.value.Fecha_NacimientoListaNegra = 'Seleccione la fecha de nacimiento';
      if (!form.value.paisOtro) erroresValidacion.value.paisOtro = 'Seleccione el país de origen';
      if (form.value.paisOtro === 'Otro' && !form.value.paisListaNegra.trim()) erroresValidacion.value.paisListaNegra = 'Especifique el país de origen';
      if (!form.value.archivoListaNegra) erroresValidacion.value.archivoListaNegra = 'Debe ingresar el archivo correspondiente para poder continuar';
    }
    if (Object.keys(erroresValidacion.value).length > 0) {
      mostrarAlertaErrores.value = true;
      return false;
    }
    return true;
  }

  function getInputClass(fieldName: string): string {
    return erroresValidacion.value[fieldName] ? 'form-input border-red-500' : 'form-input';
  }

  function getFileInputClass(fieldName: string): string {
    return erroresValidacion.value[fieldName] ? 'form-file border-red-500' : 'form-file';
  }

  function cerrarAlertaErrores() { mostrarAlertaErrores.value = false; }

  function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
      form.value.archivoListaNegra = target.files[0];
      if (erroresValidacion.value.archivoListaNegra) delete erroresValidacion.value.archivoListaNegra;
    } else {
      form.value.archivoListaNegra = null;
    }
  }

  function resetForm() {
    form.value = { accion: 3, nombreListaNegra: '', RFCListaNegra: '', CURPListaNegra: '', Fecha_NacimientoListaNegra: '', paisOtro: '', paisListaNegra: '', archivoListaNegra: null };
    selectedId.value = null;
    erroresValidacion.value = {};
    mostrarAlertaErrores.value = false;
  }

  function closeModal() { showModal.value = false; resetForm(); }
  function openAddModal() { resetForm(); form.value.accion = 3; showModal.value = true; }

  function openEditModal(item: any) {
    form.value.accion = 1;
    selectedId.value = item.IDRegistroListaCNSF;
    form.value.nombreListaNegra = item.Nombre;
    form.value.RFCListaNegra = item.RFC;
    form.value.CURPListaNegra = item.CURP;
    if (item.FechaNacimiento) {
      const fecha = new Date(item.FechaNacimiento);
      form.value.Fecha_NacimientoListaNegra = fecha.toISOString().split('T')[0];
    } else {
      form.value.Fecha_NacimientoListaNegra = '';
    }
    form.value.paisOtro = item.Pais === 'MEXICO' ? 'MEXICO' : 'Otro';
    form.value.paisListaNegra = item.Pais !== 'MEXICO' ? item.Pais : '';
    showModal.value = true;
    erroresValidacion.value = {};
    mostrarAlertaErrores.value = false;
  }

  function openDeleteModal(item: any) {
    form.value.accion = 2;
    selectedId.value = item.IDRegistroListaCNSF;
    showModal.value = true;
    form.value.nombreListaNegra = item.Nombre;
    form.value.RFCListaNegra = item.RFC;
    form.value.CURPListaNegra = item.CURP;
    form.value.archivoListaNegra = null;
    erroresValidacion.value = {};
    mostrarAlertaErrores.value = false;
  }

  const modalTitle = computed(() => {
    switch (form.value.accion) {
      case 1: return 'Actualizar Registro';
      case 2: return 'Eliminar Registro';
      case 3: return 'Agregar Registro';
      default: return 'Registro';
    }
  });

  const actionButtonText = computed(() => {
    switch (form.value.accion) {
      case 1: return 'Actualizar';
      case 2: return 'Eliminar';
      case 3: return 'Guardar';
      default: return 'Aceptar';
    }
  });

  function submitForm() {
    if (!validarCampos()) return;
    const formData = new FormData();
    formData.append('nombre', form.value.nombreListaNegra);
    formData.append('rfc', form.value.RFCListaNegra);
    formData.append('curp', form.value.CURPListaNegra);
    formData.append('fecha_nacimiento', form.value.Fecha_NacimientoListaNegra);
    const pais = form.value.paisOtro === 'Otro' ? form.value.paisListaNegra : form.value.paisOtro;
    formData.append('pais', pais);
    if (form.value.archivoListaNegra) formData.append('archivo', form.value.archivoListaNegra);
    let url = '';
    if (form.value.accion === 1) url = `/lista-negra/update/${selectedId.value}`;
    else if (form.value.accion === 2) url = `/lista-negra/delete/${selectedId.value}`;
    else url = `/lista-negra/insert`;
    router.post(url, formData, {
      onSuccess: () => { closeModal(); router.reload(); },
      onError: (errors) => { console.error('Errores de validación:', errors); },
    });
  }

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
  const isLoading = ref(true)
  onMounted(() => { setTimeout(() => { isLoading.value = false }, 800) })

  const search = ref('');
  const perPage = ref(10);
  const currentPage = ref(1);

  function normalize(text: string): string {
    return text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  const filteredListas = computed(() => {
    if (!search.value) return listas.value;
    const term = normalize(search.value);
    return listas.value.filter(item => {
      const nombre = normalize(item.Nombre);
      const rfc = normalize(item.RFC);
      const curp = normalize(item.CURP ?? '');
      const pais = normalize(item.Pais ?? '');
      return (nombre.includes(term) || rfc.includes(term) || curp.includes(term) || pais.includes(term));
    });
  });

  const paginatedListas = computed(() => {
    if (perPage.value === -1) return filteredListas.value
    const start = (currentPage.value - 1) * perPage.value
    return filteredListas.value.slice(start, start + perPage.value)
  })

  const totalPages = computed(() => {
    if (perPage.value === -1) return 1
    return Math.ceil(filteredListas.value.length / perPage.value)
  })

  function nextPage() { if (currentPage.value < totalPages.value) currentPage.value++ }
  function prevPage() { if (currentPage.value > 1) currentPage.value-- }

  watch([search, perPage], () => (currentPage.value = 1))

  const showingMessage = computed(() => {
    if (isLoading.value || !filteredListas.value.length) return ''
    const total = filteredListas.value.length
    if (perPage.value === -1) return `Mostrando todos los ${total.toLocaleString()} registros.`
    const start = (currentPage.value - 1) * perPage.value + 1
    const end = Math.min(start + perPage.value - 1, total)
    return `Mostrando ${start.toLocaleString()} a ${end.toLocaleString()} de un total de ${total.toLocaleString()} registros.`
  })
</script>

<template>
  <AppLayout title="Lista Negra">
    <div class="flex items-center justify-between">
      <Titulo :icon="ListX" title="Lista Negra" size="md" weight="bold" class="mb-2" />
    </div>

    <section class="content">
      <!-- FILTROS -->
      <div class="filter-section">  
        <div class="filter-item">
          <label>Número de elementos</label>
          <select v-model.number="perPage" :disabled="isLoading" class="form-input">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option><option :value="-1">Todos</option>
          </select>
        </div>
        <div class="filter-item">
          <label>Buscar una persona</label>
          <input v-model="search" :disabled="isLoading" type="text" placeholder="Nombre o RFC" class="form-input" />
        </div>
      </div>

      <!-- PAGINADOR -->
      <div class="row">
        <div class="col-md-12">
          <div class="pagination-controls">
            <button @click="prevPage" :disabled="currentPage === 1" class="pagination-btn">&lt;</button>
            <span class="pagination-text">Página {{ currentPage }} de {{ totalPages }}</span>
            <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-btn">&gt;</button>
          </div>
          <br>
          <div class="table-responsive">
            <div class="table-header">
              <div v-if="showingMessage" class="results-info">{{ showingMessage }}</div>
              <button @click="openAddModal" :disabled="isLoading" class="add-button">+</button>
            </div>
            <table id="table-lista-negra-cnsf" class="data-table">
              <thead>
                <tr class="table-header-row">
                  <th>#</th>
                  <th>Nombre</th>
                  <th>RFC</th>
                  <th class="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody v-if="paginatedListas.length" class="table-body">
                <tr v-for="item in paginatedListas" :key="item.IDRegistroListaCNSF" class="table-row">
                  <td>{{ item.IDRegistroListaCNSF }}</td>
                  <td>{{ item.Nombre }}</td>
                  <td>{{ item.RFC }}</td>
                  <td class="text-center actions-cell">
                    <button @click="openEditModal(item)" class="edit-btn">✏️</button>
                    <button @click="openDeleteModal(item)" class="delete-btn">🗑️</button>
                  </td>
                </tr>
              </tbody>
              <tbody v-else>
                <tr class="no-results-row">
                  <td colspan="4">No hay resultados para "{{ search }}"</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <!-- MODAL -->
    <transition name="fade-in">
      <div v-if="showModal" class="modal-overlay">
        <div class="modal-container">

          <div class="modal-header"> <!-- ALERTA -->
            <h2 class="modal-title">{{ modalTitle }}</h2>
            <div v-if="mostrarAlertaErrores" class="error-alert">
              <div class="alert-content">
                <h3 class="alert-title">Por favor revise la siguiente información en el formulario:</h3>
                <ul class="error-list">
                  <li v-for="(error, field) in erroresValidacion" :key="field">{{ error }}</li>
                </ul>
              </div>
              <button @click="cerrarAlertaErrores" class="alert-close-btn">✕</button>
            </div>
          </div>
             
          <div class="modal-content"> 
            <form @submit.prevent="submitForm" class="form-container">
              <div v-if="form.accion === 2" class="delete-confirmation">
                <p>¿Estás segura de eliminar al usuario <strong>{{ form.nombreListaNegra }}</strong> con RFC <strong>{{ form.RFCListaNegra }}</strong> de la lista negra?</p>
              </div>

              <div v-if="form.accion !== 2" class="form-fields">
                <input type="hidden" v-model="form.accion" />
                <div class="form-field">
                  <label>Nombre</label>
                  <input v-model="form.nombreListaNegra" placeholder="Ingresa el Nombre Completo" type="text" :class="getInputClass('nombreListaNegra')" />
                </div>
                <div class="form-field">
                  <label>RFC</label>
                  <input v-model="form.RFCListaNegra" placeholder="Ingresa el RFC" type="text" :class="getInputClass('RFCListaNegra')" />
                </div>
                <div class="form-field">
                  <label>CURP</label>
                  <input v-model="form.CURPListaNegra" placeholder="Ingresa el CURP" type="text" :class="getInputClass('CURPListaNegra')" />
                </div>
                <div class="form-field">
                  <label>Fecha de Nacimiento</label>
                  <input v-model="form.Fecha_NacimientoListaNegra" type="date" :class="getInputClass('Fecha_NacimientoListaNegra')" />
                </div>
                <div class="form-field">
                  <label>País</label>
                  <select v-model="form.paisOtro" :class="getInputClass('paisOtro')">
                    <option value="">Seleccione una opción</option>
                    <option value="MEXICO">MEXICO</option>
                    <option value="Otro">Otro</option>
                  </select>
                  <input v-if="form.paisOtro === 'Otro'" v-model="form.paisListaNegra" placeholder="Especifique el país" :class="getInputClass('paisListaNegra')" class="mt-2" />
                </div>
              </div>

              <div class="form-field">
                <label>Subir Oficio<span class="required-asterisk">*</span></label>
                <input @change="handleFileChange" type="file" accept="application/pdf, application/x-pdf, application/acrobat, applications/pdf, text/pdf, application/vnd.pdf" :class="getFileInputClass('archivoListaNegra')" />
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <div class="modal-actions">
              <button @click="closeModal" class="cancel-btn">Cancelar</button>
              <button @click="submitForm" class="submit-btn">{{ actionButtonText }}</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

  </AppLayout>
</template>

<!-- ESTYLOS -->
<style scoped>
  /* Inputs base */
  .form-input, .form-file, select { width:100%; padding:0.5rem 0.75rem; border-radius:0.5rem; transition:all 0.2s; outline:none; background:#FFF; color:#1F2937; border:1px solid #D1D5DB; }
  .dark .form-input, .dark .form-file, .dark select { background:#374151; border-color:#4B5563; color:#F9FAFB; }
  .form-input::placeholder { color:#9CA3AF; } .dark .form-input::placeholder { color:#9CA3AF; }
  .form-input:hover, .form-file:hover { box-shadow:0 1px 4px rgba(0,0,0,0.08); }
  .form-input:focus, .form-file:focus { border-color:#3B82F6; box-shadow:0 0 0 2px rgba(59,130,246,0.3); }
  .dark .form-input:focus, .dark .form-file:focus { border-color:#3B82F6; box-shadow:0 0 0 2px rgba(59,130,246,0.3); }

  /* File inputs */
  .form-file { cursor:pointer; background:#F3F4F6; } .dark .form-file { background:#4B5563; }
  .form-file::file-selector-button { margin-right:1rem; padding:0.35rem 0.75rem; border:none; border-radius:0.375rem; background:#6B7280; color:#FFF; font-weight:500; cursor:pointer; transition:background 0.2s; }
  .form-file:hover::file-selector-button { background:#4B5563; } .dark .form-file::file-selector-button { background:#6B7280; } .dark .form-file:hover::file-selector-button { background:#9CA3AF; }

  /* Utilidades */
  .border-red-500 { border-color:#EF4444 !important; }

  /* Filtros */
  .filter-section { display:flex; flex-direction:column; gap:1rem; margin-bottom:1rem; }
  .filter-item { display:flex; flex-direction:column; width:16rem; }
  .filter-item label { color:#374151; font-weight:500; margin-bottom:0.25rem; } .dark .filter-item label { color:#D1D5DB; }

  /* Paginación */
  .pagination-controls { display:flex; justify-content:space-between; align-items:center; margin-top:1rem; }
  .pagination-btn { padding:0.4rem 0.9rem; border-radius:0.5rem; border:1px solid #D1D5DB; background:#fff; color:#374151; font-weight:500; transition:0.2s; }
  .pagination-btn:hover { background:#E5E7EB; } .pagination-btn:disabled { opacity:0.5; cursor:not-allowed; }
  .pagination-btn:focus { outline:none; border-color:#3B82F6; box-shadow:0 0 0 2px rgba(59,130,246,0.3); }
  .dark .pagination-btn { background:#374151; border-color:#4B5563; color:#F9FAFB; } .dark .pagination-btn:hover { background:#4B5563; }
  .pagination-text { color:#374151; font-weight:500; } .dark .pagination-text { color:#D1D5DB; }

  /* Tabla */
  .table-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
  .results-info { font-size:0.875rem; color:#374151; } .dark .results-info { color:#D1D5DB; }
  .add-button { padding:0.5rem 1.5rem; border-radius:0.5rem; background:#2563EB; color:white; font-weight:500; transition:0.2s; }
  .add-button:hover { background:#1D4ED8; } .add-button:disabled { opacity:0.5; cursor:not-allowed; }
  .data-table { width:100%; border-collapse:collapse; font-size:0.875rem; }
  .table-header-row { background:#F3F4F6; color:#374151; } .dark .table-header-row { background:#374151; color:#D1D5DB; }
  .table-header-row th { border:1px solid #D1D5DB; padding:0.5rem 1rem; text-align:left; } .dark .table-header-row th { border-color:#4B5563; }
  .table-body { background:#FFF; color:#374151; } .dark .table-body { background:#1F2937; color:#D1D5DB; }
  .table-row { transition:background 0.2s; } .table-row:hover { background:#F9FAFB; } .dark .table-row:hover { background:#374151; }
  .table-row td { border:1px solid #D1D5DB; padding:0.5rem 1rem; } .dark .table-row td { border-color:#4B5563; }
  .actions-cell { text-align:center; }
  .edit-btn { padding:0.25rem 0.5rem; background:#F59E0B; color:white; border-radius:0.25rem; transition:0.2s; } .edit-btn:hover { background:#D97706; }
  .delete-btn { padding:0.25rem 0.5rem; background:#EF4444; color:white; border-radius:0.25rem; margin-left:0.5rem; transition:0.2s; } .delete-btn:hover { background:#DC2626; }
  .no-results-row td { background:#FFF; color:#374151; text-align:center; padding:0.5rem; border:1px solid #D1D5DB; }
  .dark .no-results-row td { background:#1F2937; color:#D1D5DB; border-color:#4B5563; }

  /* Modal */
  .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:50; padding:1rem; }
  .modal-container { background:#FFF; border-radius:0.75rem; box-shadow:0 10px 25px rgba(0,0,0,0.1); width:24rem; max-width:100%; max-height:90vh; display:flex; flex-direction:column; }
  .dark .modal-container { background:#1F2937; }
  .modal-header { padding:1.5rem; border-bottom:1px solid #E5E7EB; flex-shrink:0; } .dark .modal-header { border-color:#374151; }
  .modal-title { font-size:1.25rem; font-weight:600; }
  .error-alert { margin-top:1rem; padding:1rem; border:1px solid #FCA5A5; background:#FEF2F2; border-radius:0.5rem; display:flex; justify-content:space-between; align-items:flex-start; }
  .dark .error-alert { border-color:#7F1D1D; background:rgba(127,29,29,0.1); }
  .alert-content { flex:1; }
  .alert-title { color:#991B1B; font-weight:600; margin-bottom:0.5rem; } .dark .alert-title { color:#FECACA; }
  .error-list { color:#DC2626; font-size:0.875rem; list-style:disc inside; line-height:1.5; } .dark .error-list { color:#FCA5A5; }
  .alert-close-btn { margin-left:0.5rem; color:#EF4444; transition:0.2s; } .alert-close-btn:hover { color:#DC2626; }
  .dark .alert-close-btn { color:#FCA5A5; } .dark .alert-close-btn:hover { color:#F87171; }
  .modal-content { flex:1; overflow-y:auto; padding:1.5rem; }
  .form-container { display:flex; flex-direction:column; gap:1rem; }
  .delete-confirmation { margin-bottom:0.5rem; }
  .form-fields { display:flex; flex-direction:column; gap:1rem; }
  .form-field { display:flex; flex-direction:column; }
  .form-field label { font-size:0.875rem; font-weight:500; color:#374151; margin-bottom:0.25rem; } .dark .form-field label { color:#D1D5DB; }
  .required-asterisk { color:#EF4444; }
  .modal-footer { padding:1.5rem; border-top:1px solid #E5E7EB; flex-shrink:0; } .dark .modal-footer { border-color:#374151; }
  .modal-actions { display:flex; justify-content:flex-end; gap:0.5rem; }
  .cancel-btn { padding:0.5rem 1rem; border-radius:0.5rem; background:#D1D5DB; color:#374151; transition:0.2s; } .cancel-btn:hover { background:#9CA3AF; }
  .dark .cancel-btn { background:#4B5563; color:#D1D5DB; } .dark .cancel-btn:hover { background:#6B7280; }
  .submit-btn { padding:0.5rem 1rem; border-radius:0.5rem; background:#2563EB; color:white; transition:0.2s; } .submit-btn:hover { background:#1D4ED8; }

  /* Scrollbar */
  .overflow-y-auto { scrollbar-width:thin; scrollbar-color:#cbd5e0 #f7fafc; } .dark .overflow-y-auto { scrollbar-color:#4a5568 #2d3748; }
  .overflow-y-auto::-webkit-scrollbar { width:6px; }
  .overflow-y-auto::-webkit-scrollbar-track { background:#f7fafc; border-radius:3px; } .dark .overflow-y-auto::-webkit-scrollbar-track { background:#2d3748; }
  .overflow-y-auto::-webkit-scrollbar-thumb { background:#cbd5e0; border-radius:3px; } .dark .overflow-y-auto::-webkit-scrollbar-thumb { background:#4a5568; }
  .overflow-y-auto::-webkit-scrollbar-thumb:hover { background:#a0aec0; } .dark .overflow-y-auto::-webkit-scrollbar-thumb:hover { background:#718096; }

  /* Transiciones */
  .fade-in-enter-active, .fade-in-leave-active { transition:opacity 0.3s ease; }
  .fade-in-enter-from, .fade-in-leave-to { opacity:0; }
</style>