<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { ChevronsUpDown, Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

const { appearance, updateAppearance } = useAppearance();
const { isMobile, state } = useSidebar();
const isCollapsed = computed(() => state.value === 'collapsed');

const options = [
    { value: 'light', label: 'Claro', description: 'Tema claro', Icon: Sun },
    { value: 'dark', label: 'Oscuro', description: 'Tema oscuro', Icon: Moon },
    {
        value: 'system',
        label: 'Sistema',
        description: 'Sigue al sistema',
        Icon: Monitor,
    },
] as const;

const CurrentIcon = computed(
    () => options.find((o) => o.value === appearance.value)?.Icon ?? Monitor,
);

const currentLabel = computed(
    () => options.find((o) => o.value === appearance.value)?.label ?? 'Sistema',
);
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        tooltip="Cambiar tema"
                        data-test="appearance-menu-button"
                        class="rounded-xl border border-sidebar-border/60 bg-sidebar-accent/40 px-2 transition-[background-color] duration-150 ease-out hover:bg-sidebar-accent/70 focus-visible:bg-sidebar-accent/70 active:scale-[0.99] data-[state=open]:bg-sidebar-accent/70"
                    >
                        <component
                            :is="CurrentIcon"
                            class="size-4 shrink-0 opacity-70"
                        />
                        <span
                            v-if="!isCollapsed"
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-medium">Apariencia</span>
                            <span
                                class="truncate text-xs text-sidebar-foreground/60"
                            >
                                {{ currentLabel }}
                            </span>
                        </span>
                        <ChevronsUpDown
                            v-if="!isCollapsed"
                            class="ml-auto size-3.5 shrink-0 opacity-40"
                        />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="font-normal">
                        <span class="text-xs font-medium">Tema</span>
                        <span class="block text-xs text-muted-foreground">
                            Claro, oscuro o según el sistema
                        </span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuRadioGroup
                        :model-value="appearance"
                        @update:model-value="
                            (v) =>
                                updateAppearance(
                                    v as 'light' | 'dark' | 'system',
                                )
                        "
                    >
                        <DropdownMenuRadioItem
                            v-for="option in options"
                            :key="option.value"
                            :value="option.value"
                            data-test="appearance-option"
                            :data-value="option.value"
                            class="cursor-pointer py-2"
                        >
                            <component
                                :is="option.Icon"
                                class="size-4 shrink-0 opacity-60"
                            />
                            <span class="grid flex-1 text-left leading-tight">
                                <span class="text-sm font-medium">
                                    {{ option.label }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ option.description }}
                                </span>
                            </span>
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
