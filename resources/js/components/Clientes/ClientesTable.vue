<script setup lang="ts">
import {
    displayNameForCliente,
    getCategoryTags,
} from '@/components/Clientes/categoryMeta';
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineProps<{
    clientes: any[];
    isSearching: boolean;
}>();

const emit = defineEmits<{
    open: [cliente: any];
}>();

function onRowKeydown(event: KeyboardEvent, cliente: any) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        emit('open', cliente);
    }
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-border bg-card">
        <div
            v-if="isSearching"
            class="h-0.5 w-full overflow-hidden bg-muted"
            aria-hidden="true"
        >
            <div
                class="h-full w-1/3 animate-[clientes-loading-bar_1s_ease-in-out_infinite] bg-primary/70"
            />
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] border-collapse text-sm">
                <caption class="sr-only">
                    Listado de clientes
                </caption>
                <thead>
                    <tr class="border-b border-border">
                        <th
                            scope="col"
                            class="px-4 py-3 text-left align-middle text-[11px] font-medium tracking-[0.08em] text-muted-foreground uppercase"
                        >
                            Cliente
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3 text-left align-middle text-[11px] font-medium tracking-[0.08em] text-muted-foreground uppercase"
                        >
                            RFC
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3 text-left align-middle text-[11px] font-medium tracking-[0.08em] text-muted-foreground uppercase"
                        >
                            CURP
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3 text-left align-middle text-[11px] font-medium tracking-[0.08em] text-muted-foreground uppercase"
                        >
                            Tipo
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3 text-left align-middle text-[11px] font-medium tracking-[0.08em] text-muted-foreground uppercase"
                        >
                            Categorías
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3 text-right align-middle text-[11px] font-medium tracking-[0.08em] text-muted-foreground uppercase"
                        >
                            <span class="sr-only">Acciones</span>
                            <span aria-hidden="true">Detalle</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="isSearching && clientes.length === 0">
                        <td colspan="6" class="px-4 py-4">
                            <div class="space-y-2" aria-hidden="true">
                                <Skeleton
                                    v-for="i in 4"
                                    :key="i"
                                    class="h-11 w-full"
                                />
                            </div>
                            <p
                                class="mt-3 text-center text-xs text-muted-foreground"
                                role="status"
                            >
                                Buscando clientes…
                            </p>
                        </td>
                    </tr>
                    <tr
                        v-for="cliente in clientes"
                        v-else
                        :key="cliente.IDCliente"
                        tabindex="0"
                        role="link"
                        :aria-label="`Ver detalle de ${displayNameForCliente(cliente)}`"
                        class="cursor-pointer border-b border-border/70 transition-colors duration-150 outline-none last:border-0 hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset motion-reduce:transition-none"
                        @click="emit('open', cliente)"
                        @keydown="onRowKeydown($event, cliente)"
                    >
                        <td class="max-w-[280px] px-4 py-3 align-middle">
                            <p
                                class="truncate text-sm font-medium text-foreground"
                            >
                                {{ displayNameForCliente(cliente) }}
                            </p>
                            <p
                                class="mt-0.5 truncate text-xs text-muted-foreground"
                            >
                                <span v-if="cliente.RazonSocial"
                                    >{{ cliente.RazonSocial }} ·
                                </span>
                                <span class="tabular-nums"
                                    >ID {{ cliente.IDCliente }}</span
                                >
                            </p>
                        </td>
                        <td
                            class="px-4 py-3 align-middle font-mono text-[12.5px] whitespace-nowrap text-muted-foreground"
                        >
                            {{ cliente.RFC || '—' }}
                        </td>
                        <td
                            class="px-4 py-3 align-middle font-mono text-[12.5px] whitespace-nowrap text-muted-foreground"
                        >
                            {{ cliente.CURP || '—' }}
                        </td>
                        <td class="px-4 py-3 align-middle whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground"
                            >
                                {{
                                    Number(cliente.IDTipoPersona) === 1
                                        ? 'Física'
                                        : 'Moral'
                                }}
                            </span>
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <span class="flex items-center gap-1.5">
                                <template
                                    v-for="(tag, i) in getCategoryTags(cliente)"
                                    :key="i"
                                >
                                    <span
                                        v-if="tag.kind === 'text'"
                                        class="text-xs text-muted-foreground italic"
                                    >
                                        {{ tag.tooltip }}
                                    </span>
                                    <TooltipProvider
                                        v-else
                                        :delay-duration="150"
                                    >
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <span
                                                    class="inline-block h-2 w-2 rounded-full ring-1 ring-black/10 ring-inset dark:ring-white/15"
                                                    :class="tag.dot"
                                                >
                                                    <span class="sr-only">{{
                                                        tag.tooltip
                                                    }}</span>
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p class="text-xs">
                                                    {{ tag.tooltip }}
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </template>
                            </span>
                        </td>
                        <td
                            class="px-4 py-3 text-right align-middle whitespace-nowrap"
                        >
                            <span
                                class="inline-flex items-center gap-1 text-[13px] font-medium text-foreground/80 transition-colors duration-150 group-hover:text-foreground"
                            >
                                Ver detalle
                                <span
                                    aria-hidden="true"
                                    class="transition-transform duration-150 motion-reduce:transition-none"
                                    >→</span
                                >
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style>
@keyframes clientes-loading-bar {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(300%);
    }
}
@media (prefers-reduced-motion: reduce) {
    .animate-\[clientes-loading-bar_1s_ease-in-out_infinite\] {
        animation: none;
    }
}
</style>
