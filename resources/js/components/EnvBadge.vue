<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Badge global "Entorno de desarrollo".
// Se monta junto a la app Inertia en app.ts, por lo que aparece
// en todas las pantallas (autenticadas y login) cuando el switch en BD está activo.
const page = usePage();

const activo = computed(() => {
    const envBadge = page.props.envBadge as unknown as
        | { activo?: unknown }
        | undefined;
    return envBadge?.activo === true;
});
</script>

<template>
    <div
        v-if="activo"
        role="status"
        aria-live="polite"
        class="pointer-events-none fixed top-4 right-4 z-[100] inline-flex items-center gap-2 rounded-full border border-amber-300 bg-amber-400 px-4 py-2 text-sm font-semibold text-amber-950 shadow-lg dark:border-amber-500 dark:bg-amber-500 dark:text-amber-950"
    >
        <span
            class="relative flex h-2.5 w-2.5"
            aria-hidden="true"
        >
            <span
                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-700 opacity-60"
            />
            <span
                class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-800"
            />
        </span>
        Entorno de desarrollo
    </div>
</template>
