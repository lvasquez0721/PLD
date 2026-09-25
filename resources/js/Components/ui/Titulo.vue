<template>
  <div class="titulo-component" :class="[
    center ? 'text-center' : 'text-left',
    uppercase ? 'uppercase' : '',
    sizeClass,
    weightClass,
    colorClass,
    'transition-colors duration-200',
    tight ? 'leading-tight' : '',
    tracking ? 'tracking-wide' : '',
    'microtype',
    shadowed ? 'titulo-shadow' : '',
    spacing ? 'titulo-spacing' : '',
    icon ? 'titulo-with-icon' : '',
  ]" :aria-label="title || undefined" :title="title || undefined" :tabindex="tabindex" :role="role"
    data-testid="titulo">
    <span v-if="icon" class="titulo-icon" aria-hidden="true">
      <component :is="icon" v-bind="iconProps" />
    </span>
    <slot>{{ title }}</slot>
    <span v-if="decorative && !center" class="titulo-decorative-left" aria-hidden="true" />
    <span v-if="decorative && center" class="titulo-decorative-center" aria-hidden="true" />
  </div>
</template>

<script setup lang="ts">
import { computed, type Component } from 'vue';

// Mejora: más opciones y microdetalles
const props = defineProps<{
  title?: string
  center?: boolean
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  weight?: 'thin' | 'light' | 'regular' | 'medium' | 'semibold' | 'bold' | 'extrabold' | 'black'
  color?: 'default' | 'primary' | 'secondary' | 'muted' | 'danger' | 'success' | 'warning'
  uppercase?: boolean
  tight?: boolean
  tracking?: boolean
  shadowed?: boolean    // Nuevo: suave sombra para separar sobre backgrounds
  spacing?: boolean     // Nuevo: mayor letter-spacing
  decorative?: boolean  // Nuevo: adorno sutil visual
  role?: string         // Mejor accesibilidad
  tabindex?: number     // Para focus manual si se usa como pseudo-título interactivo
  icon?: Component | null // Nuevo: icono a la izquierda (puede ser componente)
  iconProps?: Record<string, any> // Props opcionales que se pasen al icono
}>();

const sizeClass = computed(() => {
  switch (props.size) {
    case 'xs': return 'text-xs md:text-sm';
    case 'sm': return 'text-base md:text-lg';
    case 'md': return 'text-xl md:text-2xl';
    case 'lg': return 'text-2xl md:text-3xl';
    case 'xl': return 'text-3xl md:text-4xl';
    case '2xl': return 'text-4xl md:text-5xl';
    default: return 'text-xl md:text-2xl';
  }
});

const weightClass = computed(() => {
  switch (props.weight) {
    case 'thin': return 'font-thin';
    case 'light': return 'font-light';
    case 'regular': return 'font-normal';
    case 'medium': return 'font-medium';
    case 'semibold': return 'font-semibold';
    case 'bold': return 'font-bold';
    case 'extrabold': return 'font-extrabold';
    case 'black': return 'font-black';
    default: return 'font-bold';
  }
});

const colorClass = computed(() => {
  switch (props.color) {
    case 'primary': return 'text-blue-700 dark:text-blue-300';
    case 'secondary': return 'text-green-700 dark:text-green-300';
    case 'muted': return 'text-gray-400 dark:text-gray-500';
    case 'danger': return 'text-red-500 dark:text-red-400';
    case 'success': return 'text-green-600 dark:text-green-400';
    case 'warning': return 'text-amber-500 dark:text-amber-400';
    case 'default':
    default: return 'text-gray-900 dark:text-white';
  }
});
</script>

<style scoped>
.titulo-component {
  margin-bottom: 0.75em;
  letter-spacing: 0.018em;
  /* Microajuste óptico */
  /* Microdetalles sutiles: */
  text-shadow:
    0.02em 0.04em 0 rgba(16, 23, 36, 0.06),
    0 0.01em 0 rgba(16, 23, 36, 0.01);
  /* Capa adicional apenas visible */
  transition: color 0.2s, text-shadow 0.3s;
  user-select: text;
  /* Advanced antialiasing y subpixel rendering */
  -webkit-font-smoothing: antialiased;
  text-rendering: optimizeLegibility;
  /* Microtipografía avanzada: */
  -webkit-font-feature-settings: "liga", "kern", "onum", "pnum";
  font-feature-settings: "liga", "kern", "onum", "pnum";
  font-kerning: normal;
  /* Ajuste de altura de línea visual */
  line-height: 1.14;
  font-variant-ligatures: contextual common-ligatures;
  /* Subpixel rendering para nitidez */
  display: flex;
  align-items: center;
  gap: 0.47em;
}

.titulo-with-icon {
  /* adicional para icon */
  padding-left: 0.04em;
}

/* Icono del titulo */
.titulo-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-right: 0.40em;
  /* El tamaño ideal depende del tamaño del texto */
  font-size: 1.1em;
  /* hereda color del texto */
  color: inherit;
  line-height: 1;
  vertical-align: middle;
}

.titulo-component:focus {
  outline: none;
  box-shadow: 0 0 0 2px #2677ff42, 0 0 0 4px #0002;
}

/*
Opcional: sombra para contexto visual
*/
.titulo-shadow {
  text-shadow:
    0 2px 9px rgba(0, 0, 0, 0.045),
    0.02em 0.04em 0 rgba(16, 23, 36, 0.13);
}

/*
Opcional: Spacing (tracking extra)
*/
.titulo-spacing {
  letter-spacing: 0.065em !important;
  word-spacing: 0.13em !important;
}

.microtype {
  word-spacing: 0.04em;
  hyphens: auto;
  hanging-punctuation: first last;
  /* punctuation-trim no soportado ampliamente, pero queda */
  punctuation-trim: start end;
  /* Suavizado sobrepasa el antialiasing */
  -webkit-text-stroke-width: 0.15px;
  -webkit-text-stroke-color: rgba(45, 68, 95, 0.03);
  /* directwrite mejora sutil (solo Chrome y Edge) */
  font-smooth: always;
}

/* Detalle decorativo sutil (ejemplo: línea o icono pequeño) */
.titulo-decorative-left {
  display: inline-block;
  height: 1.05em;
  width: 0.15em;
  margin-right: 0.6em;
  vertical-align: middle;
  border-radius: 2px;
  background: linear-gradient(180deg, #2b5db9 60%, #e5eaf7 100%);
  opacity: .45;
  content: '';
  box-shadow: 0 0 4px 0 #2b5db96c;
  transition: opacity .3s;
}

.titulo-decorative-center {
  display: block;
  height: 4px;
  width: 38px;
  margin: 0.24em auto 0 auto;
  border-radius: 3px;
  background: linear-gradient(90deg, #2d86f9 10%, #00e7ba 100%);
  opacity: .42;
  content: '';
  box-shadow: 0 2px 6px 0 #28b9ae44;
  transition: opacity .3s;
}

@media (max-width: 600px) {
  .titulo-decorative-center {
    width: 26px;
    height: 3px;
  }
}

@media (prefers-color-scheme: dark) {
  .titulo-decorative-left {
    background: linear-gradient(180deg, #8ff0ff 70%, #3467e5 100%);
  }

  .titulo-decorative-center {
    background: linear-gradient(90deg, #1cb0f5 20%, #00dcc4 85%);
  }
}
</style>
