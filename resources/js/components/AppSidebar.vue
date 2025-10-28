<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Users, Shield, BarChart3, Settings, Bell, MailWarning, ListX, FileText, Gavel, SearchCheck } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { ref, onMounted, onUnmounted } from 'vue';

// Añade ruta y nav item para Usuarios
const usuariosHref = '/usuarios';

// Estado para micro-interacciones
const isHovered = ref(false);
const currentTime = ref(new Date());

// Actualizar tiempo cada minuto para efectos sutiles
let timeInterval: number | null = null;

onMounted(() => {
    timeInterval = setInterval(() => {
        currentTime.value = new Date();
    }, 60000);

    // BLOQUEAR SCROLL DEL <body> global cuando el componente sidebar está montado (fijo)
    document.body.style.overflow = 'hidden';
});

onUnmounted(() => {
    if (timeInterval) {
        clearInterval(timeInterval);
    }
    // Restaurar scroll cuando se desmonta el sidebar
    document.body.style.overflow = '';
});

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        badge: null,
        description: 'Vista general del sistema'
    },
    {
        title: 'Usuarios',
        href: usuariosHref,
        icon: Users,
        badge: null,
        description: 'Gestión de usuarios'
    },
    {
        title: 'Perfil Transaccional',
        href: '/perfil-transaccional',
        icon: BookOpen,
        badge: null,
        description: 'Gestión de perfiles transaccionales'
    },
    {
        title: 'Módulo de Alertas',
        href: '/alertas',
        icon: Bell,
        badge: null,
        description: 'Gestión de alertas'
    },
    {
        title: 'Buzón de Preocupantes',
        href: '/buzon-preocupantes',
        icon: MailWarning,
        badge: null,
        description: 'Buzón de personas preocupantes'
    },
    {
        title: 'Lista Negra',
        href: '/lista-negra',
        icon: ListX,
        badge: null,
        description: 'Gestión de listas negras'
    },
    {
        title: 'Reporte de Operaciones',
        href: '/reporte-operaciones',
        icon: FileText,
        badge: null,
        description: 'Reportes de operaciones'
    },
    {
        title: 'Parametría PLD',
        href: '/parametria-pld',
        icon: Settings,
        badge: null,
        description: 'Configuración de parametría PLD'
    },
    {
        title: 'Listas UIF',
        href: '/listas-uif',
        icon: Gavel,
        badge: null,
        description: 'Gestión de listas UIF'
    },
    {
        title: 'Consulta Inusualidad',
        href: '/consulta-inusualidad',
        icon: SearchCheck,
        badge: null,
        description: 'Consulta de inusualidades'
    },
];

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Configuración',
    //     href: '/configuracion',
    //     icon: Settings,
    //     description: 'Ajustes del sistema'
    // },
    // {
    //     title: 'Notificaciones',
    //     href: '/notificaciones',
    //     icon: Bell,
    //     badge: '3',
    //     description: 'Centro de notificaciones'
    // },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="relative overflow-hidden fixed-sidebar"
        :class="{ 'sidebar-enhanced': true, 'hover:shadow-2xl': isHovered }" @mouseenter="isHovered = true"
        @mouseleave="isHovered = false">
        <!-- Efecto de gradiente sutil en el fondo -->
        <div
            class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-sidebar-accent/5 pointer-events-none" />

        <!-- Efecto de partículas sutiles (solo visible en hover) -->
        <div v-if="isHovered" class="absolute inset-0 opacity-30 pointer-events-none"
            style="background-image: radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.1) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);" />

        <SidebarHeader class="relative z-10 flex-shrink-0">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child
                        class="group relative overflow-hidden transition-all duration-300 ease-out hover:scale-[1.02] active:scale-[0.98]">
                        <Link :href="dashboard()"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all duration-200 hover:bg-sidebar-accent/50 focus:bg-sidebar-accent/50 focus:outline-none focus:ring-2 focus:ring-sidebar-accent/20">
                        <!-- Efecto de brillo sutil en hover -->
                        <div
                            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 transform -skew-x-12 translate-x-[-100%] group-hover:translate-x-[100%]" />

                        <AppLogo />

                        <!-- Indicador de tiempo sutil -->
                        <div class="ml-auto text-xs text-sidebar-foreground/60 font-mono tabular-nums">
                            {{ currentTime.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) }}
                        </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="relative z-10 flex-1 overflow-hidden">
            <!-- Contenedor con scroll interno preparado -->
            <div
                class="h-full overflow-y-auto overflow-x-hidden scrollbar-thin scrollbar-thumb-sidebar-accent/20 scrollbar-track-transparent hover:scrollbar-thumb-sidebar-accent/40">
                <!-- Separador visual sutil -->
                <div class="mx-4 my-2 h-px bg-gradient-to-r from-transparent via-sidebar-border to-transparent" />

                <NavMain :items="mainNavItems" />

                <!-- Indicador de progreso sutil -->
                <div class="mx-4 my-2 h-px bg-gradient-to-r from-transparent via-sidebar-border to-transparent" />
            </div>
        </SidebarContent>

        <SidebarFooter class="relative z-10 flex-shrink-0">
            <!-- Efecto de elevación sutil en el footer -->
            <div class="absolute inset-0 bg-gradient-to-t from-sidebar-accent/10 to-transparent rounded-t-lg" />

            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

<style scoped>
/* Sidebar fija que no se desplaza con el contenido */
.fixed-sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    height: 100dvh !important;
    /* Soporte para viewport dinámico */
    z-index: 50 !important;
    will-change: transform;
}

/* EVITA QUE SE CORTE LA SIDEBAR CUANDO HAY MUCHO CONTENIDO
   BLOQUEA EL SCROLL DEL BODY (se fuerza en el script con document.body.style.overflow = 'hidden')
   Y SE ASEGURA QUE NADIE PUEDA HACER SCROLL PRINCIPAL QUE OCUPE EL LUGAR DE LA SIDEBAR FIJA.
*/

html,
body {
    /* Para asegurar que el scroll no pase a body aunque el panel principal crece */
    overflow: hidden !important;
    height: 100vh !important;
    height: 100dvh !important;
}

.sidebar-enhanced {
    /* Transiciones suaves y naturales */
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Asegurar que la sidebar mantenga su posición */
.sidebar-enhanced :deep([data-sidebar="sidebar"]) {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    height: 100dvh !important;
    z-index: 50 !important;
    overflow: hidden;
}

/* Scroll interno preparado para contenido largo */
.sidebar-enhanced :deep(.sidebar-content) {
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Scrollbar personalizado para el contenido interno */
.scrollbar-thin {
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
}

.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
    transition: background 0.2s ease;
}

.scrollbar-thin:hover::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.4);
}

/* Mejoras para el scroll interno */
.sidebar-enhanced :deep(.sidebar-content > div) {
    scroll-behavior: smooth;
    overscroll-behavior: contain;
}

/* Asegurar que el header y footer no se desplacen */
.sidebar-enhanced :deep(.sidebar-header) {
    position: relative;
    flex-shrink: 0;
    z-index: 10;
}

.sidebar-enhanced :deep(.sidebar-footer) {
    position: relative;
    flex-shrink: 0;
    z-index: 10;
}

/* Estructura de layout fija */
.sidebar-enhanced :deep([data-sidebar="sidebar"]) {
    display: flex !important;
    flex-direction: column !important;
    height: 100vh !important;
    height: 100dvh !important;
}

/* Prevenir que el contenido se desborde */
.sidebar-enhanced :deep(.sidebar-content) {
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

/* Asegurar que la sidebar esté siempre visible */
.sidebar-enhanced {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    z-index: 50 !important;
    height: 100vh !important;
    height: 100dvh !important;
    width: var(--sidebar-width) !important;
}

/* Micro-animaciones para elementos interactivos */
.sidebar-enhanced :deep(.sidebar-menu-button) {
    position: relative;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-enhanced :deep(.sidebar-menu-button:hover) {
    transform: translateX(2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.sidebar-enhanced :deep(.sidebar-menu-button:active) {
    transform: translateX(1px) scale(0.98);
}

/* Efecto de ondas en los botones */
.sidebar-enhanced :deep(.sidebar-menu-button::before) {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.3s ease, height 0.3s ease;
    pointer-events: none;
}

.sidebar-enhanced :deep(.sidebar-menu-button:active::before) {
    width: 100px;
    height: 100px;
}

/* Mejoras tipográficas sutiles */
.sidebar-enhanced :deep(.sidebar-group-label) {
    font-weight: 600;
    letter-spacing: 0.025em;
    text-transform: uppercase;
    font-size: 0.75rem;
    opacity: 0.8;
}

/* Efecto de parallax sutil en scroll */
.sidebar-enhanced :deep(.sidebar-content) {
    scroll-behavior: smooth;
}

/* Mejoras en el estado colapsado */
.sidebar-enhanced[data-state="collapsed"] :deep(.sidebar-menu-button) {
    justify-content: center;
}

.sidebar-enhanced[data-state="collapsed"] :deep(.sidebar-menu-button:hover) {
    transform: scale(1.1);
}

/* Efectos de profundidad */
.sidebar-enhanced :deep(.sidebar-header) {
    position: relative;
}

.sidebar-enhanced :deep(.sidebar-header::after) {
    content: '';
    position: absolute;
    bottom: 0;
    left: 1rem;
    right: 1rem;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
}

/* Animaciones de entrada */
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

.sidebar-enhanced :deep(.sidebar-menu-item) {
    animation: slideInFromLeft 0.3s ease-out;
    animation-fill-mode: both;
}

.sidebar-enhanced :deep(.sidebar-menu-item:nth-child(1)) {
    animation-delay: 0.1s;
}

.sidebar-enhanced :deep(.sidebar-menu-item:nth-child(2)) {
    animation-delay: 0.2s;
}

.sidebar-enhanced :deep(.sidebar-menu-item:nth-child(3)) {
    animation-delay: 0.3s;
}

.sidebar-enhanced :deep(.sidebar-menu-item:nth-child(4)) {
    animation-delay: 0.4s;
}

/* Efectos de focus mejorados */
.sidebar-enhanced :deep(.sidebar-menu-button:focus-visible) {
    outline: 2px solid rgba(59, 130, 246, 0.5);
    outline-offset: 2px;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

/* Mejoras en modo oscuro */
@media (prefers-color-scheme: dark) {
    .sidebar-enhanced :deep(.sidebar-menu-button:hover) {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }
}
</style>
