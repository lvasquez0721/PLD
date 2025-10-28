<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { Gavel } from 'lucide-vue-next';
</script>

<template>
  <AppLayout title="Listas UIF">
    <div class="flex items-center justify-between">
      <Titulo :icon="Gavel" title="Parametría PLD" size="md" weight="bold" class="mb-2" />
    </div>
    <!-- Contenido de la vista Listas UIF -->
  </AppLayout>
</template>