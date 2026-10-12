<script setup lang="ts">
import { PLD_CATEGORY_OPTIONS } from '@/components/Clientes/categoryMeta';
import { ChevronDown, Info } from 'lucide-vue-next';
import { ref } from 'vue';

const open = ref(false);
</script>

<template>
    <div class="rounded-xl border border-border bg-card">
        <button
            type="button"
            :aria-expanded="open"
            aria-controls="pld-legend-panel"
            @click="open = !open"
            class="flex w-full items-center justify-between gap-3 rounded-xl px-4 py-3 text-left transition-colors duration-150 outline-none hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background motion-reduce:transition-none"
        >
            <span class="inline-flex min-w-0 items-center gap-2.5">
                <span
                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
                    aria-hidden="true"
                >
                    <Info class="size-3.5" />
                </span>
                <span class="min-w-0">
                    <span
                        class="block truncate text-[13px] font-medium text-foreground"
                    >
                        Significado de las categorías
                    </span>
                    <span class="block truncate text-xs text-muted-foreground">
                        6 categorías de riesgo para el monitoreo de clientes
                    </span>
                </span>
            </span>
            <ChevronDown
                class="size-4 shrink-0 text-muted-foreground transition-transform duration-200 motion-reduce:transition-none"
                :class="{ 'rotate-180': open }"
                aria-hidden="true"
            />
        </button>
        <div
            v-show="open"
            id="pld-legend-panel"
            class="border-t border-border px-4 py-3"
        >
            <ul class="grid gap-x-8 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="opt in PLD_CATEGORY_OPTIONS"
                    :key="opt.value"
                    class="flex items-start gap-2.5"
                >
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full ring-1 ring-black/10 ring-inset dark:ring-white/15"
                        :class="opt.dot"
                        aria-hidden="true"
                    />
                    <span class="min-w-0">
                        <span
                            class="block text-[13px] leading-snug font-medium text-foreground"
                        >
                            {{ opt.short }}
                        </span>
                        <span
                            class="block text-xs leading-snug text-muted-foreground"
                        >
                            {{ opt.description }}
                        </span>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
