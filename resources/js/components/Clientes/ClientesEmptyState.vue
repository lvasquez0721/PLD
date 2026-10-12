<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { SearchX } from 'lucide-vue-next';

defineProps<{
    hasActiveFilters: boolean;
}>();

const emit = defineEmits<{
    clearAll: [];
    clearSearch: [];
}>();
</script>

<template>
    <div
        class="flex flex-col items-center rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center"
    >
        <span
            class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
            aria-hidden="true"
        >
            <SearchX class="size-5" />
        </span>
        <p class="mt-4 text-sm font-medium text-foreground">
            Sin resultados para los filtros actuales
        </p>
        <p
            class="mx-auto mt-1 max-w-md text-[13px] leading-relaxed text-muted-foreground"
        >
            Prueba con menos palabras, revisa la ortografía o quita algún
            filtro. La búsqueda ignora mayúsculas, acentos y el orden de las
            palabras.
        </p>
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
            <Button size="sm" @click="emit('clearAll')">Limpiar filtros</Button>
            <Button
                v-if="hasActiveFilters"
                size="sm"
                variant="outline"
                @click="emit('clearSearch')"
            >
                Solo quitar búsqueda
            </Button>
        </div>
    </div>
</template>
