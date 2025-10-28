<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { Bell } from 'lucide-vue-next';
</script>

<template>
  <AppLayout title="Módulo de Alertas">
    <div class="flex items-center justify-between">
      <Titulo :icon="Bell" title="Módulo de Alertas" size="md" weight="bold" class="mb-2" />
    </div>
    <!-- Contenido de la vista Módulo de Alertas -->
  </AppLayout>
</template>