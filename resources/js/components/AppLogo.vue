<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex items-center min-w-0">
        <AppLogoIcon class="size-5 flex-shrink-0 fill-current text-white dark:text-black" />
        <div class="ml-1 flex-1 min-w-0">
            <span
                class="block leading-tight font-semibold tracking-tight tabular-nums text-sm truncate sm:whitespace-normal"
                style="font-variant-ligatures: discretionary-ligatures; letter-spacing: -0.01em;">
                <span style="font-feature-settings: 'ss01', 'kern', 'liga', 'dlig'">Prevención</span>
                <span aria-hidden="true" class="inline-block align-middle select-none"
                    style="font-size: 0.6em; vertical-align: super; opacity: 0.75; margin-left: 0.18em;">•</span>
                <span style="letter-spacing: 0.005em; font-feature-settings: 'cv01', 'onum';">de Lavado de Dinero</span>
                <span aria-hidden="true" class="sr-only"> </span>
            </span>
        </div>
    </div>
</template>
