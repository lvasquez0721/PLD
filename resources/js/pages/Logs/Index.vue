<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/ui/tables/Datatable.vue';
import Titulo from '@/components/ui/Titulo.vue';
import FadeIn from '@/components/ui/animation/fadeIn.vue';
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
  tiene_payload: boolean;
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
  })),
);
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
          search-placeholder="Buscar logs..."
          empty-message="No se encontraron registros de logs"
        />
      </div>
    </FadeIn>
  </AppLayout>
</template>
