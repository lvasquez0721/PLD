<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { Users } from 'lucide-vue-next';
</script>

<template>
  <AppLayout title="Perfil Transaccional">
    <div class="flex items-center justify-between">
      <Titulo :icon="Users" title="Perfil Transaccional" size="md" weight="bold" class="mb-2" />
    </div>
    <!-- Contenido de la vista Perfil Transaccional -->
  </AppLayout>
</template>