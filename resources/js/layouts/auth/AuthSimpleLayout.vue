<script setup lang="ts">
// AuthSimpleLayout.vue: Solo detalles para expertos en detalles.
// Capas de composición, microtransiciones y detalles de accesibilidad refinados.
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

import { ref, onMounted, onBeforeUnmount } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

// Fondo animado con gradiente en movimiento
const gradientRef = ref<HTMLElement | null>(null);
let frameId: number | null = null;
let angle = 135;

// Gradiente de azul-800 a blanco (más azul, menos morado)
const gradientColors = [
    '#0046e8', // blue-800
    '#fff'     // white
];

function animateGradient() {
    if (gradientRef.value) {
        angle = (angle + 0.04) % 360; // Ajusta la velocidad aquí
        gradientRef.value.style.background = `linear-gradient(${angle}deg, ${gradientColors[0]} 0%, ${gradientColors[1]} 100%)`;
    }
    frameId = requestAnimationFrame(animateGradient);
}

onMounted(() => {
    animateGradient();
});

onBeforeUnmount(() => {
    if (frameId) {
        cancelAnimationFrame(frameId);
    }
});
</script>

<template>
    <div ref="gradientRef"
        class="relative flex min-h-svh flex-col items-center gap-6 p-6 pt-10 md:p-10 overflow-hidden subpixel-antialiased selection:bg-blue-200 dark:selection:bg-blue-800 selection:text-blue-900/90 transition-all duration-700"
        style="background: linear-gradient(135deg, #1e40af 0%, #fff 100%);">
        <!-- Overlay de contraste/control luminancia. Experto: activable con clase. -->
        <div class="absolute inset-0 bg-gradient-to-b from-white/60 via-white/30 to-transparent pointer-events-none z-0 transition-all duration-700 will-change-[background]"
            aria-hidden="true"></div>

        <!-- Contenido principal -->
        <div class="w-full max-w-sm relative z-10 animate-fade-in drop-shadow-2xl">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link :href="home()"
                        class="focus-visible:outline-none group flex flex-col items-center gap-2 font-medium">
                    <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center pointer-events-auto">
                        <h1 class="text-xl font-medium tracking-tight text-foreground drop-shadow-sm">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground/90 italic select-none">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
        <!-- Micro-overlay detallista para foco extremo, apenas perceptible a usuarios pro -->
        <div class="absolute inset-0 pointer-events-none z-0">
            <div
                class="w-full h-full bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-300/15 to-transparent" />
            <div class="absolute bottom-6 left-6 w-16 h-16 bg-blue-800/10 rounded-full blur-3xl rotate-12 hidden md:block"
                aria-hidden="true"></div>
        </div>
    </div>
</template>
