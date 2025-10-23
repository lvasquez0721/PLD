<script setup lang="ts">
// UI Input personalizado con detalles cuidados para consistencia y flexibilidad.
// Soporta tanto controlled (modelValue) como uncontrolled (defaultValue).
import type { HTMLAttributes, InputHTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { useVModel } from '@vueuse/core'

// Props extendidas para máxima integración, pero sólo las debidas se usan en el input
const props = defineProps<{
  /** Valor inicial para inputs uncontrolled (fallback) */
  defaultValue?: string | number
  /** v-model para binding reactivo */
  modelValue?: string | number
  /** Clases tailwind o personalizadas para el input */
  class?: HTMLAttributes['class']
  /** Spread extra attributes típicos de <input> */
  // eslint-disable-next-line vue/no-reserved-props
  [key: string]: any // Permite p.ej. type="password", autocomplete, etc.
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', payload: string | number): void
}>()

// Bind reactividad de v-model, con fallback a defaultValue si no estrictamente controlled.
// Se fuerza passive para evitar conflictos con forms reactivos
const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <!--
    Componente Input estilizado.
    Notas "solo para expertos":
    - Mantiene full compatibilidad con autofill (bg-transparent + bg-slate-50 en padre).
    - Focus-visible con anillo accesible.
    - aria-invalid styling marca el borde/halo destructivo solo en error.
    - File input hereda fuente y color para mejor integración en UI modernas.
    - Min-w-0 fuerza truncamiento si va en flex.
    - El width: 100% es forzado, igual que outline-none para control visual.
    - Solo v-model en la prop modelValue, delegando <Form> y otros controles.
  -->
  <input v-model="modelValue" v-bind="props" data-slot="input" :class="cn(
    // SX base: fondo, transición, border, shadow y compatibilidad con file input
    'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
    // Enfoque visible y uso del sistema de colores
    'focus-visible:border-blue-600 focus-visible:ring-blue-600/50 focus-visible:ring-[3px]',
    // Marcas para errores de validación vía aria-invalid
    'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
    // Permite override suave desde el prop class
    props.class,
  )" :value="undefined" autocomplete="off" spellcheck="false" autocapitalize="off">
</template>
