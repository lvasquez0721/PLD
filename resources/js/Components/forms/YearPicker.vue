<script setup lang="ts">
import { useSlots, ref, computed } from 'vue';

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
  // Agrega más según necesites
};

// SVG icon definitions
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
};

// defineProps no se puede renombrar ni envolver, así que usamos directamente
const props = withDefaults(defineProps<{
  modelValue: string | number
  label?: string
  placeholder?: string
  id?: string
  name?: string
  required?: boolean
  disabled?: boolean
  classInput?: string
  classLabel?: string
  value?: string | number
  icon?: string
  prefix?: string | undefined
  suffix?: string | undefined
  startYear?: number
  endYear?: number
}>(), {
  icon: 'calendar',
  startYear: new Date().getFullYear() - 100,
  endYear: new Date().getFullYear(),
});

const emit = defineEmits(['update:modelValue']);
const slots = useSlots();
const selectInput = ref<HTMLSelectElement | null>(null);

const years = computed(() => {
  const years = [];
  for (let year = props.endYear; year >= props.startYear; year--) {
    years.push(year);
  }
  return years;
});

function onInput(event: Event) {
  const input = event.target as HTMLSelectElement;
  emit('update:modelValue', input.value);
}

function handleWrapperClick() {
  if (props.disabled) return;

  if (selectInput.value) {
    selectInput.value.focus();
  }
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
              transition-all duration-250 ease-out cursor-pointer"
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
        @click="handleWrapperClick"
      >
        <!-- Prefix -->
        <span
          v-if="prefix || slots.prefix"
          class="flex items-center px-3 rounded-l-xl bg-neutral-100 dark:bg-neutral-900/40 text-base text-neutral-400 dark:text-neutral-500 transition-colors duration-200 select-none cursor-pointer"
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

        <!-- Flowbite Icono -->
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
        <select
          :id="id"
          :name="name"
          ref="selectInput"
          :value="value !== undefined ? value : modelValue"
          @change="onInput"
          :required="required"
          :disabled="disabled"
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
              : 'pr-10', // space for the dropdown arrow
            'bg-transparent',
            'border-0',
            'text-neutral-900 dark:text-neutral-100',
            'placeholder:text-neutral-400 dark:placeholder:text-neutral-600',
            'text-[15px] font-normal',
            'focus:outline-none',
            'transition-all duration-250 ease-out',
            'block w-full py-3',
            'disabled:bg-transparent dark:disabled:bg-transparent disabled:opacity-60',
            'select-auto',
            'cursor-pointer',
            'appearance-none', // remove default arrow
            classInput
          ]"
          :aria-describedby="suffix || slots.suffix ? id + '-suffix' : undefined"
          :style="{
            ...(prefix || slots.prefix ? { borderTopLeftRadius: 0, borderBottomLeftRadius: 0, borderLeftWidth: '0px' } : {}),
            ...(suffix || slots.suffix ? { borderTopRightRadius: 0, borderBottomRightRadius: 0, borderRightWidth: '0px' } : {})
          }"
        >
          <option v-if="placeholder" value="" disabled selected>{{ placeholder }}</option>
          <option v-for="year in years" :key="year" :value="year">
            {{ year }}
          </option>
        </select>

        <!-- Suffix -->
        <span
          v-if="suffix || slots.suffix"
          :id="id + '-suffix'"
          class="flex items-center px-3 rounded-r-xl bg-neutral-100 dark:bg-neutral-900/40 text-base text-neutral-400 dark:text-neutral-500 transition-colors duration-200 select-none cursor-pointer"
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
        <!-- Custom dropdown arrow -->
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-neutral-700 dark:text-neutral-300">
            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Add any specific styles for YearPicker here if needed */
</style>
