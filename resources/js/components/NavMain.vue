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
import { ref, computed } from 'vue';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
const hoveredItem = ref<string | null>(null);

// Computed para determinar si un item está activo
const isItemActive = (href: string) => {
    return urlIsActive(href, page.url);
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel class="text-xs font-semibold text-sidebar-foreground/70 uppercase tracking-wider mb-3">
            Navegación Principal
        </SidebarGroupLabel>
        <SidebarMenu class="space-y-1">
            <SidebarMenuItem v-for="(item, index) in items" :key="item.title" class="relative"
                :style="{ '--animation-delay': `${index * 0.1}s` }">
                <SidebarMenuButton as-child :is-active="isItemActive(item.href)"
                    :tooltip="item.description || item.title"
                    class="group relative overflow-hidden transition-all duration-200 ease-out hover:bg-sidebar-accent/80 focus:bg-sidebar-accent/80"
                    :class="{
                        'bg-sidebar-accent text-sidebar-accent-foreground shadow-sm': isItemActive(item.href),
                        'hover:translate-x-1': !isItemActive(item.href),
                        'scale-105': hoveredItem === item.title
                    }" @mouseenter="hoveredItem = item.title" @mouseleave="hoveredItem = null">
                    <Link :href="item.href"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-sidebar-accent/20">
                    <!-- Indicador de estado activo -->
                    <div v-if="isItemActive(item.href)"
                        class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-sidebar-accent-foreground rounded-r-full" />

                    <!-- Efecto de brillo en hover -->
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform -skew-x-12 translate-x-[-100%] group-hover:translate-x-[100%]" />

                    <!-- Icono con animación sutil -->
                    <div class="relative z-10 flex-shrink-0">
                        <component :is="item.icon" class="w-5 h-5 transition-all duration-200 group-hover:scale-110"
                            :class="{
                                'text-sidebar-accent-foreground': isItemActive(item.href),
                                'text-sidebar-foreground/70 group-hover:text-sidebar-foreground': !isItemActive(item.href)
                            }" />
                    </div>

                    <!-- Contenido del texto -->
                    <div class="flex-1 min-w-0">
                        <span class="font-medium transition-colors duration-200" :class="{
                            'text-sidebar-accent-foreground': isItemActive(item.href),
                            'text-sidebar-foreground group-hover:text-sidebar-foreground': !isItemActive(item.href)
                        }">
                            {{ item.title }}
                        </span>

                        <!-- Descripción sutil (solo en hover) -->
                        <div v-if="item.description && hoveredItem === item.title"
                            class="text-xs text-sidebar-foreground/60 mt-0.5 leading-tight">
                            {{ item.description }}
                        </div>
                    </div>

                    <!-- Badge de notificación -->
                    <div v-if="item.badge" class="flex-shrink-0 ml-auto">
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition-all duration-200"
                            :class="{
                                'bg-sidebar-accent-foreground/20 text-sidebar-accent-foreground': isItemActive(item.href),
                                'bg-sidebar-accent text-sidebar-accent-foreground': !isItemActive(item.href) && item.badge === 'Nuevo',
                                'bg-red-500/20 text-red-500': !isItemActive(item.href) && item.badge !== 'Nuevo'
                            }">
                            {{ item.badge }}
                        </span>
                    </div>

                    <!-- Indicador de carga sutil -->
                    <div v-if="isItemActive(item.href)"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-2 h-2 bg-sidebar-accent-foreground rounded-full animate-pulse" />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>

<style scoped>
/* Animaciones de entrada escalonadas */
.sidebar-menu-item {
    animation: slideInFromLeft 0.4s ease-out;
    animation-fill-mode: both;
    animation-delay: var(--animation-delay);
}

@keyframes slideInFromLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Efectos de micro-interacción */
.group:hover .group-hover\:scale-110 {
    transform: scale(1.1);
}

/* Mejoras en el estado activo */
.sidebar-menu-button[data-state="active"] {
    position: relative;
}

.sidebar-menu-button[data-state="active"]::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(to bottom, var(--sidebar-accent-foreground), transparent);
    border-radius: 0 2px 2px 0;
}

/* Efectos de profundidad */
.sidebar-menu-button:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

/* Transiciones suaves para todos los elementos */
* {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Mejoras en modo oscuro */
@media (prefers-color-scheme: dark) {
    .sidebar-menu-button:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }
}
</style>
