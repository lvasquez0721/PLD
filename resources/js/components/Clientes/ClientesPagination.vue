<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const page = defineModel<number>('page', { default: 1 });
const perPage = defineModel<number>('perPage', { default: 10 });

defineProps<{
    totalPages: number;
    from: number;
    to: number;
    total: number;
    perPageOptions: number[];
    disabled: boolean;
}>();

const emit = defineEmits<{
    prev: [];
    next: [];
}>();
</script>

<template>
    <nav
        aria-label="Paginación de clientes"
        class="flex flex-col gap-3 rounded-xl border border-border bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <label
                class="inline-flex items-center gap-2 text-[13px] text-muted-foreground"
            >
                Mostrar
                <select
                    v-model="perPage"
                    :disabled="disabled"
                    aria-label="Registros por página"
                    class="h-8 cursor-pointer rounded-md border border-input bg-background px-2 text-[13px] text-foreground transition-[border-color,box-shadow] duration-150 outline-none hover:border-muted-foreground/40 focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none"
                >
                    <option
                        v-for="opt in perPageOptions"
                        :key="opt"
                        :value="opt"
                    >
                        {{ opt }}
                    </option>
                </select>
            </label>
            <p
                class="text-[13px] text-muted-foreground tabular-nums"
                aria-live="polite"
            >
                {{ from }}–{{ to }} de {{ total }}
            </p>
        </div>
        <div class="flex items-center gap-1.5">
            <Button
                variant="ghost"
                size="sm"
                :disabled="page <= 1 || disabled"
                aria-label="Página anterior"
                class="h-8 px-2.5 transition-colors duration-150 motion-reduce:transition-none"
                @click="emit('prev')"
            >
                <ChevronLeft class="size-4" aria-hidden="true" />
                <span class="hidden sm:inline">Anterior</span>
            </Button>
            <span
                class="inline-flex items-center gap-1.5 px-1 text-[13px] text-muted-foreground tabular-nums"
            >
                <label class="sr-only" for="clientes-page-input"
                    >Página actual</label
                >
                <input
                    id="clientes-page-input"
                    v-model.number="page"
                    type="number"
                    min="1"
                    :max="Math.max(1, totalPages)"
                    :disabled="disabled"
                    class="h-8 w-14 rounded-md border border-input bg-background text-center text-[13px] text-foreground tabular-nums transition-[border-color,box-shadow] duration-150 outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 disabled:opacity-50 motion-reduce:transition-none"
                />
                <span aria-hidden="true">/ {{ Math.max(1, totalPages) }}</span>
            </span>
            <Button
                variant="ghost"
                size="sm"
                :disabled="
                    page >= Math.max(1, totalPages) ||
                    totalPages === 0 ||
                    disabled
                "
                aria-label="Página siguiente"
                class="h-8 px-2.5 transition-colors duration-150 motion-reduce:transition-none"
                @click="emit('next')"
            >
                <span class="hidden sm:inline">Siguiente</span>
                <ChevronRight class="size-4" aria-hidden="true" />
            </Button>
        </div>
    </nav>
</template>
