<script setup lang="ts">
import { defineProps, defineEmits, useSlots } from 'vue';

// Flowbite icons mapping (use short names like icon="mail")
const flowbiteIcons: Record<string, string> = {
  'mail': 'svg-mail',
  'user': 'svg-user',
  'lock': 'svg-lock',
  'eye': 'svg-eye',
  'eye-off': 'svg-eye-off',
  'search': 'svg-search',
  'phone': 'svg-phone',
  'calendar': 'svg-calendar',
  'check': 'svg-check',
  'x': 'svg-x',
  'dollar': 'svg-dollar',
  'percent': 'svg-percent',
  'alert': 'svg-alert',
  'chart': 'svg-chart',
  'building': 'svg-building',
  'users': 'svg-users',
  'settings': 'svg-settings',
  // Agrega más según necesites
};

// SVG icon definitions (puedes agregar o quitar según tus necesidades)
const svgIcons: Record<string, string> = {
  'svg-mail': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2 6l8 5 8-5M2 6V5a2 2 0 012-2h12a2 2 0 012 2v1M2 6v8a2 2 0 002 2h12a2 2 0 002-2V6" /></svg>`,
  'svg-user': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10 12a5 5 0 100-10 5 5 0 000 10zm-7 7a9 9 0 1118 0H3z" /></svg>`,
  'svg-lock': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9V5a3 3 0 016 0v4M7 9V5a3 3 0 10-6 0v4m3 2v4m0 0v2m0-2h2m-2 0H3m10 4h.01" /></svg>`,
  'svg-eye': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 10C3.732 6.943 6.386 5 10 5c3.614 0 6.268 1.943 7.542 5-1.274 3.057-3.928 5-7.542 5-3.614 0-6.268-1.943-7.542-5z"/><circle cx="10" cy="10" r="2.5"/></svg>`,
  'svg-eye-off': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A9.956 9.956 0 0110 20c-5.523 0-10-4.477-10-10 0-2.386.835-4.574 2.225-6.33m15.65 0A9.955 9.955 0 0120 10c0 5.523-4.477 10-10 10a9.956 9.956 0 01-3.875-.825m.463-7.375A4.978 4.978 0 015 10c0-2.21 1.433-4.083 3.344-4.743M8 8h.01M12 12h-.01" /></svg>`,
  'svg-search': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor"><circle cx="9" cy="9" r="7"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6"/></svg>`,
  'svg-phone': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.003 5.884l2.122-.85A2.001 2.001 0 017.107 7.8l-.85 2.122a16.017 16.017 0 006.36 6.36l2.122-.85A2.001 2.001 0 0117.965 15.875l-.85-2.122A16.017 16.017 0 002.003 5.884z" /></svg>`,
  'svg-calendar': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor"><rect width="18" height="15" x="1" y="4" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M1 10h18" /></svg>`,
  'svg-check': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l4 4 6-8" /></svg>`,
  'svg-x': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l8 8M6 14L14 6" /></svg>`,
  'svg-dollar': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>`,
  'svg-percent': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m9 9 6 6m0-6L9 15M9 5.5a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm6 11a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z" /></svg>`,
  'svg-alert': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>`,
  'svg-chart': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>`,
  'svg-building': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" /></svg>`,
  'svg-users': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>`,
  'svg-settings': `<svg xmlns="http://www.w3.org/2000/svg" fill="none" class="w-5 h-5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-7.5 7.5V21m0-16.5V3m-6.035 3.965-1.06-1.06m14.192 14.192-1.06-1.06m-14.192 0 1.06-1.06m14.192-14.192 1.06-1.06" /></svg>`,
};

type InputValue = string | number;

// defineProps no se puede renombrar ni envolver, así que usamos directamente
const props = defineProps<{
  modelValue: InputValue
  label?: string
  type?: string
  placeholder?: string
  id?: string
  required?: boolean
  classInput?: string
  classLabel?: string
  disabled?: boolean
  value?: InputValue
  icon?: string // string name for the icon
  prefix?: string | undefined
  suffix?: string | undefined
}>();

const emit = defineEmits(['update:modelValue']);
const slots = useSlots();

/**
 * Handler para soportar strings, enteros, y flotantes con dos decimales.
 * Si el tipo es "number", convierte el valor a número, y si es flotante lo redondea a dos decimales.
 */
function onInput(event: Event) {
  const input = event.target as HTMLInputElement;
  let val: InputValue = input.value;
  // Solo intentar parsear si es number
  if ((props.type === 'number' || props.type === 'int' || props.type === 'float') && val !== '') {
    // For "int" tipo, solo enteros
    if (props.type === 'int') {
      val = parseInt(val, 10);
      // Si es NaN vuelve a string vació
      if (isNaN(val)) val = '';
    } else if (props.type === 'number' || props.type === 'float') {
      val = parseFloat(val);
      if (!isNaN(val) && props.type === 'float') {
        // Redondear a dos decimales si es un flotante
        val = Math.round((val as number) * 100) / 100;
      }
      if (isNaN(val)) val = '';
    }
  }
  emit('update:modelValue', val);
}
</script>

<template>
  <div class="mb-7">
    <!-- Label -->
    <label
      v-if="label"
      :for="id"
      :class="[
        'block mb-2 text-[15px] font-semibold tracking-tight select-none transition-colors duration-200',
        'text-neutral-900 dark:text-neutral-50',
        classLabel
      ]"
    >
      {{ label }}
    </label>
    <!-- Input wrapper with prefix/suffix "inside" input -->
    <div class="relative flex items-stretch w-full group">
      <!-- Special outer border wrapper for correct focus border with prefix/suffix -->
      <div
        class="flex w-full relative border border-neutral-200 dark:border-neutral-800/50 rounded-xl
              group-focus-within:border-neutral-400 dark:group-focus-within:border-neutral-700
              group-focus-within:ring-1 group-focus-within:ring-neutral-500/40 dark:group-focus-within:ring-neutral-400/30
              bg-gradient-to-b from-neutral-50 to-neutral-100 dark:from-neutral-950 dark:to-neutral-900
              shadow-[0_1.5px_8px_0_rgba(0,0,0,0.04)] dark:shadow-[0_2px_15px_0_rgba(0,0,0,0.12)]
              transition-all duration-250 ease-out"
        :class="[
          (prefix || slots.prefix) && (suffix || slots.suffix)
            ? 'rounded-xl'
            : (prefix || slots.prefix)
              ? 'rounded-xl'
              : (suffix || slots.suffix)
                ? 'rounded-xl'
                : 'rounded-xl'
        ]"
        style="padding: 0;"
      >
        <!-- Prefix -->
        <span
          v-if="prefix || slots.prefix"
          class="flex items-center px-3 rounded-l-xl bg-neutral-100 dark:bg-neutral-900/40 text-base text-neutral-400 dark:text-neutral-500 transition-colors duration-200 select-none"
          :style="{
            zIndex: 2,
            borderTopRightRadius: 0,
            borderBottomRightRadius: 0,
            borderRight: '1px solid var(--tw-border-opacity, 1) #e5e7eb',
            borderLeft: 'none'
          }"
        >
          <slot name="prefix">
            <span v-if="prefix">{{ prefix }}</span>
          </slot>
        </span>

        <!-- Flowbite Icono (render as inline SVG if the icon string is present and mapped) -->
        <span
          v-if="icon || slots.icon"
          class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center z-10 text-base text-neutral-400 dark:text-neutral-500 transition-colors duration-200 pointer-events-none"
          aria-hidden="true"
        >
          <slot name="icon">
            <span v-if="icon && flowbiteIcons[icon] && svgIcons[flowbiteIcons[icon]]" v-html="svgIcons[flowbiteIcons[icon]]"></span>
          </slot>
        </span>

        <!-- Input -->
        <input
          :id="id"
          :type="type || 'text'"
          :value="value !== undefined ? value : modelValue"
          @input="onInput"
          :placeholder="placeholder"
          :required="required"
          :disabled="disabled"
          :readonly="disabled"
          :class="[
            (prefix || slots.prefix)
              ? 'rounded-none rounded-r-xl border-l-0'
              : (suffix || slots.suffix)
                ? 'rounded-none rounded-l-xl border-r-0'
                : (prefix || slots.prefix) && (suffix || slots.suffix)
                  ? 'rounded-none border-l-0 border-r-0'
                  : 'rounded-xl',
            (prefix || slots.prefix)
              ? 'pl-4'
              : (icon || slots.icon)
                ? 'pl-12'
                : 'pl-4',
            (suffix || slots.suffix)
              ? 'pr-4'
              : 'pr-4',
            'bg-transparent', // Remove input background to inherit from wrapper
            'border-0',
            'text-neutral-900 dark:text-neutral-100',
            'placeholder:text-neutral-400 dark:placeholder:text-neutral-600',
            'text-[15px] font-normal',
            'focus:outline-none',
            'transition-all duration-250 ease-out',
            'block w-full py-3',
            'disabled:bg-transparent dark:disabled:bg-transparent disabled:opacity-60',
            'select-auto',
            classInput
          ]"
          :aria-describedby="suffix || slots.suffix ? id + '-suffix' : undefined"
          :step="(type === 'float') ? '0.01' : undefined"
          :inputmode="(type === 'int' || type === 'number') ? 'numeric' : (type === 'float' ? 'decimal' : undefined)"
          :style="{
            // Si hay prefix, elimina borde-izquierda y radius izq.
            ...(prefix || slots.prefix ? { borderTopLeftRadius: 0, borderBottomLeftRadius: 0, borderLeftWidth: '0px' } : {}),
            // Si hay suffix, elimina borde-derecha y radius der.
            ...(suffix || slots.suffix ? { borderTopRightRadius: 0, borderBottomRightRadius: 0, borderRightWidth: '0px' } : {})
          }"
        />

        <!-- Suffix -->
        <span
          v-if="suffix || slots.suffix"
          :id="id + '-suffix'"
          class="flex items-center px-3 rounded-r-xl bg-neutral-100 dark:bg-neutral-900/40 text-base text-neutral-400 dark:text-neutral-500 transition-colors duration-200 select-none"
          :style="{
            zIndex: 2,
            borderTopLeftRadius: 0,
            borderBottomLeftRadius: 0,
            borderLeft: '1px solid var(--tw-border-opacity, 1) #e5e7eb',
            borderRight: 'none'
          }"
        >
          <slot name="suffix">
            <span v-if="suffix">{{ suffix }}</span>
          </slot>
        </span>
        <!-- Hover/focus subtle overlay interaction -->
        <span
          aria-hidden="true"
          class="pointer-events-none absolute inset-0 rounded-xl group-hover:shadow-[0_4px_18px_0_rgba(15,23,42,0.04)] group-hover:bg-neutral-800/2 dark:group-hover:bg-neutral-800/4 transition-all duration-200 group-focus-within:shadow-[0_5px_20px_0_rgba(0,0,0,0.10)]"
        />
      </div>
    </div>
  </div>
</template>
