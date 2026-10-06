<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface NavGroup {
    label: string;
    items: NavItem[];
}

const props = defineProps<{
    groups?: NavGroup[];
    items?: NavItem[];
}>();

const page = usePage();

// Compatibilidad: si solo llegan `items` planos, se envuelven en un grupo sin etiqueta.
const normalizedGroups = computed<NavGroup[]>(() => {
    if (props.groups?.length) return props.groups;
    if (props.items?.length) return [{ label: '', items: props.items }];
    return [];
});

const isItemActive = (href: NavItem['href']) => urlIsActive(href, page.url);
</script>

<template>
    <nav aria-label="Navegación principal" class="sidenav-nav">
        <SidebarGroup
            v-for="group in normalizedGroups"
            :key="group.label || 'principal'"
            class="px-2 py-0"
        >
            <SidebarGroupLabel
                v-if="group.label"
                class="sidenav-label px-2.5 pt-4 pb-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-sidebar-foreground/55"
            >
                {{ group.label }}
            </SidebarGroupLabel>
            <SidebarMenu class="gap-0.5">
                <SidebarMenuItem v-for="item in group.items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isItemActive(item.href)"
                        :tooltip="item.title"
                        class="sidenav-item group relative h-9 rounded-[10px] px-2.5 transition-[background-color,color] duration-150 ease-out hover:bg-sidebar-accent/70 focus-visible:bg-sidebar-accent/70 active:scale-[0.985]"
                        :class="{
                            'bg-sidebar-accent font-medium text-sidebar-accent-foreground': isItemActive(item.href),
                        }"
                    >
                        <Link
                            :href="item.href"
                            :aria-current="isItemActive(item.href) ? 'page' : undefined"
                            class="flex w-full items-center gap-2.5 rounded-[10px] focus:outline-none focus-visible:ring-1 focus-visible:ring-sidebar-ring/60"
                        >
                            <!-- Único indicador activo: pill táctil -->
                            <span
                                v-if="isItemActive(item.href)"
                                aria-hidden="true"
                                class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-current opacity-70"
                            />
                            <!-- Icono en tile: el detalle que se siente al pasar -->
                            <span
                                aria-hidden="true"
                                class="sidenav-icon-tile grid size-7 shrink-0 place-items-center rounded-lg transition-[background-color,transform] duration-150 ease-out group-hover:-translate-y-px group-hover:bg-black/[0.04] dark:group-hover:bg-white/[0.07]"
                                :class="{
                                    'bg-black/[0.05] dark:bg-white/[0.08]': isItemActive(item.href),
                                }"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-4 transition-colors duration-150"
                                    :class="{
                                        'text-sidebar-accent-foreground': isItemActive(item.href),
                                        'text-sidebar-foreground/60 group-hover:text-sidebar-foreground': !isItemActive(item.href),
                                    }"
                                />
                            </span>
                            <span
                                class="min-w-0 flex-1 truncate text-[13.5px] leading-none"
                                :class="{
                                    'text-sidebar-accent-foreground': isItemActive(item.href),
                                    'text-sidebar-foreground/85 group-hover:text-sidebar-foreground': !isItemActive(item.href),
                                }"
                            >
                                {{ item.title }}
                            </span>
                            <span
                                v-if="item.badge"
                                class="ml-auto shrink-0 rounded-full bg-sidebar-primary/10 px-1.5 py-0.5 text-[11px] font-medium leading-none text-sidebar-accent-foreground"
                            >
                                {{ item.badge }}
                            </span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </nav>
</template>

<style scoped>
/* Entrada serena: un solo fundido por grupo, sin cascadas por item */
.sidenav-nav {
    animation: sidenav-settle 240ms var(--sidebar-ease, cubic-bezier(0.22, 1, 0.36, 1)) both;
}

@keyframes sidenav-settle {
    from {
        opacity: 0;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.sidenav-label {
    user-select: none;
}

/* El botón de shadcn ya aporta layout; aquí solo afinamos tacto y foco */
.sidenav-item:focus-visible {
    outline: none;
}
</style>
