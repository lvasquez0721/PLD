<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { SearchCheck } from 'lucide-vue-next';
</script>

<template>
  <AppLayout title="Consulta Inusualidad">
    <div class="flex items-center justify-between">
      <Titulo :icon="SearchCheck" title="Consulta Inusualidad" size="md" weight="bold" class="mb-2" />
    </div>
    <!-- Contenido de la vista Consulta Inusualidad -->
  </AppLayout>
</template>