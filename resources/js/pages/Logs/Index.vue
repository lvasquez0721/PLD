<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/ui/tables/Datatable.vue';
import Titulo from '@/components/ui/Titulo.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
import ModalForm from '@/components/ui/modals/modalForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { ScrollText } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Logs',
    href: '',
  },
];

interface Log {
  id: number;
  usuario: string | null;
  metodo: string;
  ruta: string | null;
  ip: string | null;
  estatus: number | null;
  duracion_ms: number | null;
  fecha: string | null;
  tiene_body: boolean;
  tiene_response: boolean;
}

const logs = usePage().props.logs as Log[];

const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'fecha', label: 'Fecha', sortable: true },
  { key: 'usuario', label: 'Usuario', filterType: 'text' as const },
  { key: 'metodo', label: 'Método', filterType: 'select' as const, options: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] },
  { key: 'ruta', label: 'Ruta', filterType: 'text' as const },
  { key: 'ip', label: 'IP', filterType: 'text' as const },
  { key: 'estatus', label: 'Estatus', filterType: 'select' as const, options: ['200', '201', '401', '403', '404', '422', '500'] },
  { key: 'duracion_ms', label: 'Duración (ms)', sortable: true },
];

const rowActions = [
  { id: 'body', label: 'Mostrar Body', icon: 'view', variant: 'secondary' as const },
  { id: 'response', label: 'Mostrar Response', icon: 'view', variant: 'secondary' as const },
];

const rows = computed(() =>
  logs.map((log) => ({
    id: log.id,
    fecha: log.fecha,
    usuario: log.usuario || 'Anónimo',
    metodo: log.metodo,
    ruta: log.ruta,
    ip: log.ip,
    estatus: log.estatus,
    duracion_ms: log.duracion_ms,
    tiene_body: log.tiene_body,
    tiene_response: log.tiene_response,
  })),
);

const showModal = ref(false);
const modalTitle = ref('');
const modalContent = ref('');
const modalLoading = ref(false);

function formatearJson(content: string | null): string {
  if (!content) {
    return '';
  }
  try {
    return JSON.stringify(JSON.parse(content), null, 2);
  } catch {
    return content;
  }
}

async function verDetalle(actionId: string, rowData: Record<string, any>) {
  const id = rowData.id;
  modalLoading.value = true;
  showModal.value = true;
  modalContent.value = 'Cargando...';

  if (actionId === 'body') {
    modalTitle.value = 'Body (solicitud)';
  } else {
    modalTitle.value = 'Response (respuesta)';
  }

  try {
    const res = await fetch(`/logs/${id}`);
    const data = await res.json();
    if (!res.ok) {
      modalContent.value = data.message || 'No se pudo cargar el log.';
    } else if (actionId === 'body') {
      modalContent.value = data.body ? formatearJson(data.body) : 'Sin body registrado.';
    } else {
      modalContent.value = data.response ? formatearJson(data.response) : 'Sin response registrado.';
    }
  } catch {
    modalContent.value = 'Error al obtener el detalle del log.';
  } finally {
    modalLoading.value = false;
  }
}

const handleRowAction = (actionId: string, rowData: Record<string, any>) => {
  verDetalle(actionId, rowData);
};
</script>

<template>
  <Head title="Logs" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <FadeIn>
      <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex items-center justify-between">
          <Titulo :icon="ScrollText" title="Registro de Logs de Endpoints" size="md" weight="bold" class="mb-2" />
        </div>
        <Datatable
          :columns="columns"
          :rows="rows"
          :row-actions="rowActions"
          search-placeholder="Buscar logs..."
          empty-message="No se encontraron registros de logs"
          @row-action="handleRowAction"
        />
      </div>
    </FadeIn>

    <ModalForm v-model="showModal" :title="modalTitle" width-class="max-w-3xl" @close="showModal = false">
      <div class="relative">
        <div v-if="modalLoading" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
          <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
          Cargando...
        </div>
        <pre v-else class="whitespace-pre-wrap break-words rounded-xl bg-gray-100 dark:bg-zinc-800 p-4 text-xs leading-relaxed text-gray-800 dark:text-gray-200 max-h-[60vh] overflow-auto">
{{ modalContent }}</pre>
      </div>
    </ModalForm>
  </AppLayout>
</template>
