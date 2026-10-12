<script setup lang="ts">
import {
    displayNameForCliente,
    getCategoryTags,
} from '@/components/Clientes/categoryMeta';
import { ChevronRight } from 'lucide-vue-next';

defineProps<{
    clientes: any[];
}>();

const emit = defineEmits<{
    open: [cliente: any];
}>();
</script>

<template>
    <ul class="space-y-2.5 md:hidden" aria-label="Listado de clientes">
        <li
            v-for="cliente in clientes"
            :key="cliente.IDCliente"
            class="rounded-xl border border-border bg-card p-4 transition-colors duration-150 active:bg-muted/50 motion-reduce:transition-none"
        >
            <button
                type="button"
                class="flex w-full items-start justify-between gap-3 rounded-lg text-left outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                @click="emit('open', cliente)"
                :aria-label="`Ver detalle de ${displayNameForCliente(cliente)}`"
            >
                <span class="min-w-0 flex-1">
                    <span
                        class="block truncate text-sm font-medium text-foreground"
                    >
                        {{ displayNameForCliente(cliente) }}
                    </span>
                    <span
                        v-if="cliente.RazonSocial"
                        class="mt-0.5 block truncate text-xs text-muted-foreground"
                    >
                        {{ cliente.RazonSocial }}
                    </span>
                    <span
                        class="mt-1.5 block font-mono text-xs text-muted-foreground"
                    >
                        {{ cliente.RFC || 'Sin RFC' }} · ID
                        {{ cliente.IDCliente }}
                    </span>
                    <span
                        class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1"
                    >
                        <span
                            class="inline-flex items-center rounded-full border border-border px-2 py-0.5 text-[11px] text-muted-foreground"
                        >
                            {{
                                Number(cliente.IDTipoPersona) === 1
                                    ? 'Física'
                                    : 'Moral'
                            }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <template
                                v-for="(tag, i) in getCategoryTags(cliente)"
                                :key="i"
                            >
                                <span
                                    v-if="tag.kind === 'text'"
                                    class="text-[11px] text-muted-foreground italic"
                                >
                                    {{ tag.tooltip }}
                                </span>
                                <span
                                    v-else
                                    class="inline-block h-2 w-2 rounded-full ring-1 ring-black/10 ring-inset dark:ring-white/15"
                                    :class="tag.dot"
                                    role="img"
                                    :aria-label="tag.tooltip"
                                />
                            </template>
                        </span>
                    </span>
                </span>
                <ChevronRight
                    class="mt-1 size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </button>
        </li>
    </ul>
</template>
