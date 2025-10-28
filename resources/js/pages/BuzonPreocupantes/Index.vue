<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Titulo from '@/components/ui/Titulo.vue';
import { MailWarning } from 'lucide-vue-next';
</script>

<template>
  <AppLayout title="Buzón de Preocupantes">
    <div class="flex items-center justify-between">
      <Titulo :icon="MailWarning" title="Buzón de Preocupantes" size="md" weight="bold" class="mb-2" />
    </div>
    <!-- Contenido de la vista Buzón de Preocupantes -->
  </AppLayout>
</template>