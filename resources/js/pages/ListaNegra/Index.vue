<script setup lang="ts">
  import { ref, computed, onMounted, watch } from 'vue';
  import { Head, usePage, router } from '@inertiajs/vue3';
  import AppLayout from '@/layouts/AppLayout.vue';
  import Titulo from '@/components/ui/Titulo.vue';
  import { ListX } from 'lucide-vue-next';

  const page = usePage();
  const isSubmitting = ref(false)

  const breadcrumbs = [{ title: 'Lista Negra', href: '' }];

  const showModal = ref(false);
  const selectedId = ref<number | null>(null);
  const mostrarError = ref(false);
  const codigoError = ref('');
  const listaErrores = ref('');
  const erroresValidacion = ref<Record<string, string>>({});
  const mostrarAlertaErrores = ref(false);


  /* ===== CONTROL DE FLASH DEL SERVIDOR ===== */
  const flashSuccess = computed(() => (page.props as any).flash?.success || null);
  const flashError = computed(() => (page.props as any).flash?.error || null);

  /* ===== TÍTULO DE ALERTA ===== */
  const modalFlashTitle = computed(() => {
    if ((page.props as any).flash?.error) return "Error";
    if ((page.props as any).flash?.success) return "Exito";
    return null;
  });

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
    return erroresValidacion.value[fieldName] ? 'border-red-500' : '';
  }

  function getFileInputClass(fieldName: string): string {
    return erroresValidacion.value[fieldName] ? 'border-red-500' : '';
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

    // Validación simple del archivo - SOLO ESTAS 5 LÍNEAS
    if (form.value.archivoListaNegra) {
      const fileSizeMB = form.value.archivoListaNegra.size / (1024 * 1024);
      if (fileSizeMB > 50) {
        mostrarAlertaErrores.value = true;
        erroresValidacion.value.archivoListaNegra =
          `El archivo es muy grande (${fileSizeMB.toFixed(2)} MB). Compresión recomendada.`;
        return;
      }
    }

    const formData = new FormData();
    formData.append('nombre', form.value.nombreListaNegra);
    formData.append('rfc', form.value.RFCListaNegra);
    formData.append('curp', form.value.CURPListaNegra);
    formData.append('fecha_nacimiento', form.value.Fecha_NacimientoListaNegra);
    const pais = form.value.paisOtro === 'Otro' ? form.value.paisListaNegra : form.value.paisOtro;
    formData.append('pais', pais);
    if (form.value.archivoListaNegra) formData.append('archivo', form.value.archivoListaNegra);
    formData.append('accion', String(form.value.accion));

    let url = '';
    if (form.value.accion === 1)
      url = `/lista-negra/update/${selectedId.value}`;
    else if (form.value.accion === 2)
      url = `/lista-negra/delete/${selectedId.value}`;
    else
      url = `/lista-negra/insert`;

    // for (const [key, value] of formData.entries()) {
    //   console.log(`${key}:`, value);
    // }

      router.post(url, formData, {
        onStart: () => { isSubmitting.value = true },
        onFinish: () => { isSubmitting.value = false },
        onSuccess: () => {
          console.log("SERVIDOR DEVUELVE:", page.props.flash); //trae los mensajes del servidor
          closeModal();
        },
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

  const listas = computed(() => (page.props.listas as ListaNegra[]) ?? []);
  const isLoading = ref(true)
  onMounted(() => { setTimeout(() => { isLoading.value = false }, 800) })

  const search = ref('');
  const perPage = ref(10);
  const currentPage = ref(1);

  // New ref for the page input
  const goToPageInput = ref(currentPage.value);

  function normalize(text: string): string {
    return text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  const filteredListas = computed(() => {
    if (!search.value) return listas.value;
    const term = normalize(search.value);
    return listas.value.filter(item => {
      const nombre = normalize(item?.Nombre || '');
      const rfc = normalize(item?.RFC || '');
      const curp = normalize(item?.CURP || '');
      const pais = normalize(item?.Pais || '');
      return ( nombre.includes(term) || rfc.includes(term) || curp.includes(term) || pais.includes(term) );
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

  // Function to handle direct page input
  function handleGoToPage() {
    let pageNum = Math.floor(Number(goToPageInput.value)); // Ensure it's an integer
    if (isNaN(pageNum) || pageNum < 1 || pageNum > totalPages.value) {
      // Invalid input, reset to current valid page
      goToPageInput.value = currentPage.value;
    } else {
      currentPage.value = pageNum;
    }
  }

  // Watch currentPage changes to update goToPageInput
  watch(currentPage, (newPage) => {
    goToPageInput.value = newPage;
  });

  watch([search, perPage], () => (currentPage.value = 1))

  const showingMessage = computed(() => {
    if (isLoading.value || !filteredListas.value.length) return ''
    const total = filteredListas.value.length
    if (perPage.value === -1) return `Mostrando todos los ${total.toLocaleString()} registros.`
    const start = (currentPage.value - 1) * perPage.value + 1
    const end = Math.min(start + perPage.value - 1, total)
    return `Mostrando ${start.toLocaleString()} a ${end.toLocaleString()} de un total de ${total.toLocaleString()} registros.`
  })

  // Control de visibilidad del flash
  const mostrarFlash = ref(false)
  let flashTimeout: number | null = null

  watch([flashSuccess, flashError], () => {
    if (flashSuccess.value || flashError.value) {
      mostrarFlash.value = true
      if (flashTimeout) { clearTimeout(flashTimeout) }
      flashTimeout = window.setTimeout(() => {mostrarFlash.value = false}, 5000)
    }
    if (flashError.value) {
      mostrarFlash.value = true
    }
  })

</script>

<template>
  <AppLayout title="Lista Negra">
    <div class="flex items-center justify-between">
      <Titulo :icon="ListX" title="Listas Negras CNSF" size="md" weight="bold" class="mb-2" />
    </div>

    <!-- ALERTA SIMPLE DEL SERVIDOR -->
    <transition name="fade-in">
      <div v-if="mostrarFlash && modalFlashTitle"
        class="mb-4 rounded-xl border p-4 shadow-sm backdrop-blur-sm transition-shadow duration-300 ease-out flex items-start justify-between gap-3"
        :class="{
          'border-green-300 bg-green-50 text-green-700 dark:border-green-700 dark:bg-green-900/20 dark:text-green-300': flashSuccess,
          'border-red-300 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300': flashError
        }">
        <div class="flex-1">
          <strong class="font-semibold" :class="flashSuccess ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200'">{{ modalFlashTitle }}</strong>
          <p class="mt-2" v-if="flashSuccess">{{ flashSuccess }}</p>
          <p class="mt-2" v-if="flashError">{{ flashError }}</p>
        </div>
        <button @click="mostrarFlash = false" :class="flashSuccess ? 'text-green-500 hover:text-green-700 dark:text-green-400 dark:hover:text-green-200' : 'text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200'" class="transition-colors">✕</button>
      </div>
    </transition>


    <section class="content">
      <!-- FILTROS -->
      <div class="mt-6 flex flex-col gap-4 rounded-xl border border-slate-100 bg-gradient-to-r from-white/90 via-slate-50/70 to-white/90 p-4 shadow-sm backdrop-blur-sm transition-colors duration-200 ease-out focus-within:border-blue-400/80 focus-within:shadow-[0_0_0_1px_rgba(59,130,246,0.3)] md:flex-row md:items-end md:justify-start dark:border-neutral-800/80 dark:bg-gradient-to-r dark:from-neutral-950/90 dark:via-neutral-900/80 dark:to-neutral-950/90">
        <div class="flex flex-col gap-1 w-48">
          <label class="text-xs text-slate-600 dark:text-neutral-300">Número de elementos</label>
          <select v-model.number="perPage" :disabled="isLoading" class="w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-xs text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option><option :value="-1">Todos</option>
          </select>
        </div>
        <div class="relative w-72 flex flex-col">
          <label class="text-xs text-slate-600 dark:text-neutral-300 mb-1 block">Buscar una persona</label>
          <div class="relative">
            <input
              v-model="search"
              :disabled="isLoading"
              type="text"
              placeholder="Nombre o RFC"
              class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder-slate-400 shadow-inner outline-none ring-0 transition-all duration-150 focus:border-blue-500 focus:bg-white focus:shadow-[0_0_0_1px_rgba(59,130,246,0.35)] dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:placeholder-neutral-500 dark:focus:bg-neutral-900"
            />
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 flex items-center justify-center h-5 w-5">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.5 15.5 20 20m-3-9a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
              </svg>
            </span>
          </div>
        </div>
      </div>

      <!-- PAGINADOR Y TABLA -->
      <div class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white via-slate-50/80 to-white shadow-md shadow-slate-200/70 backdrop-blur-sm transition-shadow duration-300 ease-out hover:shadow-xl hover:hover:shadow-slate-300/70 dark:border-neutral-800 dark:bg-gradient-to-b dark:from-neutral-950/95 dark:via-neutral-950/90 dark:to-neutral-950/95 dark:shadow-lg dark:shadow-black/40 dark:hover:shadow-[0_24px_60px_rgba(0,0,0,0.85)]">
            <div class="p-4 flex items-center justify-between">
              <div v-if="showingMessage" class="text-xs text-slate-500 dark:text-neutral-400">{{ showingMessage }}</div>
              <button @click="openAddModal" :disabled="isLoading" class="inline-flex items-center rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-blue-600 shadow-sm transition-all duration-150 ease-out hover:-translate-y-[1px] hover:bg-blue-50 hover:shadow-md disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-blue-400 dark:hover:bg-blue-900/90">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Agregar Registro
              </button>
            </div>
            <div class="max-h-[28rem] overflow-y-auto">
              <table id="table-lista-negra-cnsf" class="min-w-full border-collapse text-sm text-slate-900 dark:text-white">
                <thead>
                  <tr class="sticky top-0 z-10 bg-gradient-to-r from-slate-50 via-slate-50/95 to-blue-50/60 text-xs font-semibold uppercase tracking-wide text-slate-700 backdrop-blur-sm dark:bg-gradient-to-r dark:from-neutral-900/95 dark:via-neutral-900/95 dark:to-slate-900/95 dark:text-neutral-200">
                    <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">#</th>
                    <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Nombre</th>
                    <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">RFC</th>
                    <th class="border-b border-slate-200 px-3 py-2 text-left align-middle text-[11px] font-semibold dark:border-neutral-800">Acciones</th>
                  </tr>
                </thead>
                <tbody v-if="paginatedListas.length" class="table-body">
                  <tr v-for="item in paginatedListas" :key="item.IDRegistroListaCNSF" class="group cursor-pointer border-b border-l-2 border-slate-100 border-l-transparent bg-white transition-all duration-200 ease-out hover:-translate-y-[1px] hover:border-l-blue-400 hover:bg-gradient-to-r hover:from-white hover:via-slate-50/80 hover:to-blue-50/40 hover:shadow-[0_10px_30px_rgba(15,23,42,0.08)] dark:border-neutral-800/60 dark:border-l-transparent dark:bg-neutral-950/40 dark:hover:border-l-blue-500 dark:hover:bg-gradient-to-r dark:hover:from-neutral-950/90 dark:hover:via-neutral-900/90 dark:hover:to-slate-800/90 dark:hover:shadow-[0_18px_40px_rgba(0,0,0,0.75)]">
                    <td class="px-3 py-2 align-middle">{{ item.IDRegistroListaCNSF }}</td>
                    <td class="px-3 py-2 align-middle">{{ item.Nombre }}</td>
                    <td class="px-3 py-2 align-middle">{{ item.RFC }}</td>
                    <td class="px-3 py-2 text-center align-middle">
                      <button @click="openEditModal(item)" class="inline-flex items-center gap-1 text-xs font-medium text-amber-600 transition-all duration-200 ease-out hover:text-amber-500 hover:underline hover:underline-offset-4 hover:decoration-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/70 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:text-amber-400 dark:hover:text-amber-300 dark:focus-visible:ring-offset-neutral-950">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Editar
                      </button>
                      <button @click="openDeleteModal(item)" class="inline-flex items-center gap-1 text-xs font-medium text-red-600 transition-all duration-200 ease-out hover:text-red-500 hover:underline hover:underline-offset-4 hover:decoration-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/70 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:text-red-400 dark:hover:text-red-300 dark:focus-visible:ring-offset-neutral-950">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Eliminar
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr class="table-row">
                    <td colspan="4" class="px-3 py-2 text-center text-sm text-slate-500 dark:text-neutral-400">No hay resultados para "{{ search }}"</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <!-- Controles de paginación -->
          <div class="mt-4 flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-100 bg-gradient-to-r from-white via-slate-50/70 to-white p-3 text-slate-900 shadow-sm backdrop-blur-sm sm:flex-row sm:items-center sm:gap-4 dark:border-neutral-800 dark:bg-gradient-to-r dark:from-neutral-950/95 dark:via-neutral-900/90 dark:to-neutral-950/95 dark:text-white">
              <p class="text-xs text-slate-500 dark:text-neutral-400">{{ showingMessage }}</p>
              <div class="flex items-center space-x-2">
                  <button @click="prevPage" :disabled="currentPage === 1"
                      class="rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-slate-700 shadow-sm transition-all duration-150 ease-out hover:-translate-y-[1px] hover:bg-slate-50 hover:shadow-md disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:bg-neutral-800/90">
                      Anterior
                  </button>
                  <span class="text-xs text-slate-600 dark:text-neutral-300">Página</span>
                  <input type="number" v-model.number="goToPageInput" @change="handleGoToPage" min="1" :max="totalPages"
                      class="w-16 rounded-lg border border-slate-300 bg-white px-3 py-2 text-center text-xs text-slate-900 outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900" />
                  <span class="text-xs text-slate-600 dark:text-neutral-300">de {{ totalPages }}</span>
                  <button @click="nextPage" :disabled="currentPage === totalPages"
                      class="rounded-lg border border-slate-300 bg-white/95 px-4 py-2 text-xs font-medium text-slate-700 shadow-sm transition-all duration-150 ease-out hover:-translate-y-[1px] hover:bg-slate-50 hover:shadow-md disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:hover:bg-neutral-800/90">
                      Siguiente
                  </button>
              </div>
          </div>
    </section>

    <!-- MODAL -->
    <transition name="modal-fade">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm transition-opacity">
        <div class="modal-fade-card relative w-full max-w-xl rounded-2xl border border-slate-200 bg-gradient-to-b from-white via-slate-50 to-white p-6 text-slate-900 shadow-2xl shadow-slate-300/70 transition-transform duration-200 ease-out dark:border-neutral-700 dark:bg-gradient-to-b dark:from-neutral-950 dark:via-neutral-950 dark:to-neutral-950 dark:text-white dark:shadow-black/70">

          <button
            class="absolute right-3 top-3 rounded-full bg-white/0 px-2 py-1 text-slate-400 shadow-none transition-all duration-150 hover:bg-slate-100/80 hover:text-slate-600 hover:shadow-sm dark:bg-transparent dark:text-neutral-400 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-200"
            @click="closeModal">
            ✕
          </button>

          <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-neutral-700">
            <h3 class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white">
              <span class="h-6 w-1 rounded-full bg-gradient-to-b from-blue-400 to-blue-600"></span>
              {{ modalTitle }}
            </h3>
          </div>

          <div v-if="mostrarAlertaErrores" class="mt-4 p-3 rounded-lg border border-red-300 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300 flex items-start justify-between gap-3">
              <div class="flex-1">
                <h4 class="font-semibold text-red-800 dark:text-red-200 mb-1">Por favor revise la siguiente información en el formulario:</h4>
                <ul class="mt-2 text-sm list-disc list-inside space-y-1">
                  <li v-for="(error, field) in erroresValidacion" :key="field">{{ error }}</li>
                </ul>
              </div>
              <button @click="cerrarAlertaErrores" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200 transition-colors">✕</button>
            </div>

          <div class="flex-1 overflow-y-auto pr-1 text-sm space-y-4 py-4">
            <form @submit.prevent="submitForm" class="space-y-4">
              <div v-if="form.accion === 2" class="text-center text-base text-slate-700 dark:text-neutral-300 mb-4">
                <p>¿Estás segura de eliminar al usuario <strong>{{ form.nombreListaNegra }}</strong> con RFC <strong>{{ form.RFCListaNegra }}</strong> de la lista negra?</p>
                <p class="text-red-500 font-semibold mt-2">Esta acción no se puede deshacer.</p>
              </div>

              <div v-if="form.accion !== 2" class="space-y-4">
                <input type="hidden" v-model="form.accion" />
                <div class="flex flex-col">
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1">Nombre</label>
                  <input v-model="form.nombreListaNegra" placeholder="Ingresa el Nombre Completo" type="text" :class="`w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 ${getInputClass('nombreListaNegra')}`" />
                </div>
                <div class="flex flex-col">
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1">RFC</label>
                  <input v-model="form.RFCListaNegra" placeholder="Ingresa el RFC" type="text" :class="`w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 ${getInputClass('RFCListaNegra')}`" />
                </div>
                <div class="flex flex-col">
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1">CURP</label>
                  <input v-model="form.CURPListaNegra" placeholder="Ingresa el CURP" type="text" :class="`w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 ${getInputClass('CURPListaNegra')}`" />
                </div>
                <div class="flex flex-col">
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1">Fecha de Nacimiento</label>
                  <input v-model="form.Fecha_NacimientoListaNegra" type="date" :class="`w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 ${getInputClass('Fecha_NacimientoListaNegra')}`" />
                </div>
                <div class="flex flex-col">
                  <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1">País</label>
                  <select v-model="form.paisOtro" :class="`w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 ${getInputClass('paisOtro')}`">
                    <option value="">Seleccione una opción</option>
                    <option value="MEXICO">MEXICO</option>
                    <option value="Otro">Otro</option>
                  </select>
                  <input v-if="form.paisOtro === 'Otro'" v-model="form.paisListaNegra" placeholder="Especifique el país" :class="`w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 mt-2 ${getInputClass('paisListaNegra')}`" />
                </div>
              </div>

              <div class="flex flex-col">
                <label class="text-sm font-medium text-slate-700 dark:text-neutral-300 mb-1">Subir Oficio<span class="text-red-500 ml-0.5">*</span></label>
                <input @change="handleFileChange" type="file" accept="application/pdf, application/x-pdf, application/acrobat, applications/pdf, text/pdf, application/vnd.pdf" :class="`w-full rounded-lg border border-slate-300 bg-white py-2.5 px-3 text-sm text-slate-900 shadow-inner outline-none transition-all duration-150 focus:border-blue-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-white dark:focus:bg-neutral-900 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-neutral-700 dark:file:text-neutral-200 dark:hover:file:bg-neutral-600 ${getFileInputClass('archivoListaNegra')}`" />
              </div>
            </form>
          </div>
          <div class="flex justify-end pt-4 mt-4 border-t border-slate-200 dark:border-neutral-700">
            <div class="flex gap-2">
              <button @click="closeModal" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition-all duration-150 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700">Cancelar</button>
              <button @click="submitForm" class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">{{ actionButtonText }}</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- BLOQUEO GLOBAL MIENTRAS CARGA -->
    <transition name="fade-in">
      <div v-if="isSubmitting" class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 flex flex-col items-center gap-3">
          <svg class="animate-spin h-8 w-8 text-blue-600" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
            <path class="opacity-75" fill="currentColor"
              d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8z"/>
          </svg>
          <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
            Procesando información…
          </p>
        </div>
      </div>
    </transition>

  </AppLayout>
</template>
