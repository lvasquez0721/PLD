<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <!--
          El slot de contenido ocupa todo el espacio disponible en AppLayout,
          y tiene scroll interno asegurado, así nunca rompe el sidebar.
        -->
        <div class="flex-1 min-h-0 h-full overflow-auto">
            <slot />
        </div>
    </AppLayout>
</template>
