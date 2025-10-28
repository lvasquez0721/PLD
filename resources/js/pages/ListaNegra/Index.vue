<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { ListX } from 'lucide-vue-next';
</script>

<template>
  <AppLayout title="Lista Negra">
    <div class="flex items-center justify-between">
      <Titulo :icon="ListX" title="Lista Negra" size="md" weight="bold" class="mb-2" />
    </div>
    <!-- Contenido de la vista Lista Negra -->
  </AppLayout>
</template>