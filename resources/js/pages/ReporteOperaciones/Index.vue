<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { FileText } from 'lucide-vue-next';
</script>

<template>
  <AppLayout title="Reporte de Operaciones">
    <div class="flex items-center justify-between">
      <Titulo :icon="FileText" title="Reporte de Operaciones" size="md" weight="bold" class="mb-2" />
    </div>
    <!-- Contenido de la vista Reporte de Operaciones -->
  </AppLayout>
</template>