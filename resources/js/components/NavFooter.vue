<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { ref } from 'vue';

interface Props {
    items: NavItem[];
    class?: string;
}

defineProps<Props>();

const hoveredItem = ref<string | null>(null);
</script>

<template>
    <SidebarGroup :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`">
        <SidebarGroupContent>
            <SidebarMenu class="space-y-1">
                <SidebarMenuItem v-for="(item, index) in items" :key="item.title" class="relative"
                    :style="{ '--animation-delay': `${index * 0.1}s` }">
                    <SidebarMenuButton
                        class="group relative overflow-hidden transition-all duration-200 ease-out hover:bg-sidebar-accent/60 focus:bg-sidebar-accent/60"
                        :class="{
                            'scale-105': hoveredItem === item.title
                        }" as-child @mouseenter="hoveredItem = item.title" @mouseleave="hoveredItem = null">
                        <a :href="toUrl(item.href)" target="_blank" rel="noopener noreferrer"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-sidebar-accent/20">
                            <!-- Efecto de brillo en hover -->
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform -skew-x-12 translate-x-[-100%] group-hover:translate-x-[100%]" />

                            <!-- Icono con animación sutil -->
                            <div class="relative z-10 flex-shrink-0">
                                <component :is="item.icon"
                                    class="w-4 h-4 transition-all duration-200 group-hover:scale-110 text-sidebar-foreground/60 group-hover:text-sidebar-foreground" />
                            </div>

                            <!-- Contenido del texto -->
                            <div class="flex-1 min-w-0">
                                <span
                                    class="text-sm font-medium transition-colors duration-200 text-sidebar-foreground/70 group-hover:text-sidebar-foreground">
                                    {{ item.title }}
                                </span>

                                <!-- Descripción sutil (solo en hover) -->
                                <div v-if="item.description && hoveredItem === item.title"
                                    class="text-xs text-sidebar-foreground/50 mt-0.5 leading-tight">
                                    {{ item.description }}
                                </div>
                            </div>

                            <!-- Badge de notificación -->
                            <div v-if="item.badge" class="flex-shrink-0 ml-auto">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition-all duration-200 bg-red-500/20 text-red-500">
                                    {{ item.badge }}
                                </span>
                            </div>

                            <!-- Indicador de enlace externo -->
                            <div
                                class="flex-shrink-0 ml-auto opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                <svg class="w-3 h-3 text-sidebar-foreground/40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </div>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>

<style scoped>
/* Animaciones de entrada escalonadas */
.sidebar-menu-item {
    animation: slideInFromBottom 0.4s ease-out;
    animation-fill-mode: both;
    animation-delay: var(--animation-delay);
}

@keyframes slideInFromBottom {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Efectos de micro-interacción */
.group:hover .group-hover\:scale-110 {
    transform: scale(1.1);
}

/* Efectos de profundidad */
.sidebar-menu-button:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

/* Transiciones suaves para todos los elementos */
* {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Mejoras en modo oscuro */
@media (prefers-color-scheme: dark) {
    .sidebar-menu-button:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }
}
</style>
