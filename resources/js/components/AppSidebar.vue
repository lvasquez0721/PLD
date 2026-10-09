<script setup lang="ts">
import NavMain, { type NavGroup } from '@/components/NavMain.vue';
import NavAppearance from '@/components/NavAppearance.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Bell,
    BookOpen,
    Download,
    FileText,
    Gavel,
    LayoutGrid,
    ListX,
    MailWarning,
    ScrollText,
    Settings,
    Shield,
    UserRound,
    Users,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const { state } = useSidebar();
const isCollapsed = computed(() => state.value === 'collapsed');

// Reloj ambiental: se conserva por decisión de producto, pero refinado.
// Sin lift en hover, ancho fijo para no mover el layout, oculto a lectores.
const currentTime = ref(new Date());
let timeInterval: number | null = null;

onMounted(() => {
    timeInterval = window.setInterval(() => {
        currentTime.value = new Date();
    }, 30000);
});

onUnmounted(() => {
    if (timeInterval) window.clearInterval(timeInterval);
});

const formattedTime = computed(() =>
    currentTime.value.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }),
);

// Roles del usuario autenticado (compartidos vía HandleInertiaRequests).
// El rol Depurador solo debe ver el módulo de Logs de Endpoints.
const userRoles = computed<string[]>(() => {
    const auth = page.props.auth as unknown as { roles?: unknown };
    return Array.isArray(auth?.roles) ? (auth.roles as string[]) : [];
});
const isDepurador = computed(() => userRoles.value.includes('Depurador'));

const allNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Usuarios', href: '/usuarios', icon: Users },
    { title: 'Clientes', href: '/clientes', icon: UserRound },
    { title: 'Perfil Transaccional', href: '/perfil-transaccional', icon: BookOpen },
    { title: 'Perfil Transaccional - Cliente', href: '/perfil-transaccional-cliente', icon: BookOpen },
    { title: 'Módulo de Alertas', href: '/alertas', icon: Bell },
    { title: 'Buzón de Preocupantes', href: '/buzon-preocupantes', icon: MailWarning },
    { title: 'Lista Negra CNSF', href: '/lista-negra', icon: ListX },
    { title: 'Reporte de Operaciones', href: '/reporte-operaciones', icon: FileText },
    { title: 'Parametría PLD', href: '/parametria-pld', icon: Settings },
    { title: 'Configuración de Cumplimiento', href: '/configuracion-cumplimiento', icon: Shield },
    { title: 'Listas UIF', href: '/listas-uif', icon: Gavel },
    { title: 'Exportar Layout', href: '/exportar-layout', icon: Download },
    { title: 'Reporteador PLD', href: '/reporteador-pld', icon: BarChart3 },
    { title: 'Logs de Endpoints', href: '/logs', icon: ScrollText },
];

function pick(titles: string[]): NavItem[] {
    return allNavItems.filter((item) => titles.includes(item.title));
}

// Jerarquía por dominio PLD: escaneo por contexto, no lista plana infinita.
const navGroups = computed<NavGroup[]>(() => {
    if (isDepurador.value) {
        return [{ label: 'Sistema', items: pick(['Logs de Endpoints']) }];
    }
    return [
        { label: '', items: pick(['Dashboard']) },
        { label: 'Operación', items: pick(['Usuarios', 'Clientes']) },
        {
            label: 'Análisis',
            items: pick([
                'Módulo de Alertas',
                'Buzón de Preocupantes',
                'Perfil Transaccional',
                'Perfil Transaccional - Cliente',
            ]),
        },
        {
            label: 'Cumplimiento',
            items: pick([
                'Lista Negra CNSF',
                'Listas UIF',
                'Parametría PLD',
                'Configuración de Cumplimiento',
                'Reporte de Operaciones',
                'Reporteador PLD',
                'Exportar Layout',
            ]),
        },
        { label: 'Sistema', items: pick(['Logs de Endpoints']) },
    ];
});

// El logo del Depurador apunta a /logs en lugar del dashboard.
const logoHref = computed(() => (isDepurador.value ? '/logs' : dashboard()));
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="sidebar-sensorial">
        <SidebarHeader class="flex-shrink-0 px-2 pt-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="rounded-xl transition-[background-color] duration-150 ease-out hover:bg-sidebar-accent/60 focus-visible:bg-sidebar-accent/60 active:scale-[0.98]"
                    >
                        <Link
                            :href="logoHref"
                            class="flex w-full items-center gap-2.5 px-2 py-1.5 focus:outline-none focus-visible:ring-1 focus-visible:ring-sidebar-ring/60"
                            :class="{ 'justify-center': isCollapsed }"
                        >
                            <AppLogo />
                            <span
                                v-if="!isCollapsed"
                                aria-hidden="true"
                                class="ml-auto w-[38px] text-right font-mono text-[11px] tabular-nums text-sidebar-foreground/50"
                            >
                                {{ formattedTime }}
                            </span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <div aria-hidden="true" class="sidenav-hairline mx-2 mt-2 h-px" />
        </SidebarHeader>

        <SidebarContent class="flex-1 overflow-hidden">
            <div class="sidenav-scroll h-full overflow-y-auto overflow-x-hidden px-0 py-2">
                <NavMain :groups="navGroups" />
            </div>
        </SidebarContent>

        <SidebarFooter class="flex-shrink-0 px-2 pb-3">
            <div aria-hidden="true" class="sidenav-hairline mx-2 mb-2 h-px" />
            <NavAppearance />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

<style scoped>
/* La posición fija la gobierna shadcn (variant inset); aquí solo el tacto. */
.sidebar-sensorial {
    transition: box-shadow 200ms ease-out;
}

/* Respiración contenida: sombra solo como respuesta al tacto, no permanente */
.sidebar-sensorial:hover {
    box-shadow: 8px 0 24px -16px hsl(0 0% 0% / 0.18);
}

.dark .sidebar-sensorial:hover {
    box-shadow: 8px 0 24px -16px hsl(0 0% 0% / 0.55);
}

/* En colapsado el logo es protagonista: solo marca, sin texto */
.sidebar-sensorial :deep([data-state='collapsed'] .app-logo-text),
.sidebar-sensorial :deep([data-state='collapsed'] .app-logo-sub) {
    display: none;
}
</style>
