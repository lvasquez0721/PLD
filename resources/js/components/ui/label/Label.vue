<script setup lang="ts">
// Componente Label avanzado, con microdetalles para expertos en UI/UX accesible.
// Hereda de reka-ui pero ajustado a convenciones estrictas de diseño de sistemas.
//
// - Flex superior y gap consistente.
// - Altura y peso óptimos para microcopy visibles.
//
// Accesibilidad avanzada:
//  * select-none y pointer-events para interacción predecible.
//  * peer-* para sincronizar con inputs deshabilitados (auto).
//  * group-data-[disabled=true] para parent fieldsets controlados.
//  * High-contrast y antialiased para micro-legibilidad (opcional).
//
// Égida visual oculta:
//  * transition-invisible para transiciones de opacidad suaves en focus/disabled.
//  * Marca data-slot="label" para tooling o test automáticos.
//  * Soporta override total via class (tailwind, custom, etc).
import { cn } from '@/lib/utils'
import { Label, type LabelProps } from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'

const props = defineProps<LabelProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  // Remueve class para aplicar sólo en raíz
  const { class: _, ...delegated } = props
  return delegated
})
</script>

<template>
  <Label data-slot="label" v-bind="delegatedProps" :class="cn(
    // Flexibilidad robusta en layouts: flex-row+gap para iconografía+texto
    'inline-flex items-center gap-2',
    // Legibilidad máxima en microcopy/form labels
    'text-sm font-medium leading-none',
    // Selección protegida (evita seleccionar label en doble click rápido)
    'select-none',
    // Interacción visual reactiva: cambio de color/opacity según state
    'transition-opacity duration-150',
    // Acceso peer y fieldset controlados por clases y aria
    'group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50',
    'peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
    // Mejor soporte HiDPI (en modo oscuro y claro)
    'antialiased',
    // Permite overrides terminales
    props.class,
  )" aria-hidden="false" role="label">
    <slot />
  </Label>
</template>
