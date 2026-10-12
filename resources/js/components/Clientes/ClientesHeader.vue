<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Download } from 'lucide-vue-next';

defineProps<{
    total: number;
    isSearching: boolean;
}>();

const emit = defineEmits<{
    export: [];
}>();
</script>

<template>
    <div
        class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between"
    >
        <div class="min-w-0">
            <p
                class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
            >
                Gestión PLD · Clientes
            </p>
            <h1
                class="mt-1.5 text-2xl font-semibold tracking-tight text-foreground md:text-[30px] md:leading-[36px]"
            >
                Clientes
            </h1>
            <p
                class="mt-1.5 max-w-xl text-sm leading-relaxed text-muted-foreground"
            >
                Consulta y gestiona los clientes del sistema. La búsqueda abarca
                nombre, RFC, CURP, ID, domicilio, NCliente y póliza.
            </p>
        </div>
        <div class="flex shrink-0 flex-col items-start gap-2 md:items-end">
            <p class="text-sm text-muted-foreground" aria-live="polite">
                <span v-if="isSearching" class="inline-flex items-center gap-2">
                    <span
                        class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-muted-foreground/40 border-t-foreground"
                        aria-hidden="true"
                    />
                    Buscando…
                </span>
                <span v-else>
                    <span class="font-semibold text-foreground tabular-nums">{{
                        total
                    }}</span>
                    {{
                        total === 1
                            ? 'cliente encontrado'
                            : 'clientes encontrados'
                    }}
                </span>
            </p>
            <Button
                variant="outline"
                size="sm"
                @click="emit('export')"
                class="transition-colors duration-150"
            >
                <Download class="size-4" aria-hidden="true" />
                Exportar CSV
            </Button>
        </div>
    </div>
</template>
