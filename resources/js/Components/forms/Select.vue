<script setup>
import { defineProps, defineEmits, useSlots } from 'vue';

const props = defineProps({
  id: {
    type: String,
    default: '',
  },
  name: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  options: {
    type: Array,
    required: true,
    // Example: [{ value: 'US', label: 'United States' }]
  },
  modelValue: {
    type: [String, Number],
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
  classSelect: {
    type: String,
    default: '',
  },
  classLabel: {
    type: String,
    default: '',
  }
});

const emit = defineEmits(['update:modelValue']);
const slots = useSlots();

function onChange(event) {
  emit('update:modelValue', event.target.value);
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
    <!-- Input wrapper style for border, bg, shadow, etc -->
    <div
      class="relative flex items-stretch w-full group"
    >
      <div
        class="flex w-full relative border border-neutral-200 dark:border-neutral-800/50 rounded-xl
              group-focus-within:border-neutral-400 dark:group-focus-within:border-neutral-700
              group-focus-within:ring-1 group-focus-within:ring-neutral-500/40 dark:group-focus-within:ring-neutral-400/30 
              bg-gradient-to-b from-neutral-50 to-neutral-100 dark:from-neutral-950 dark:to-neutral-900
              shadow-[0_1.5px_8px_0_rgba(0,0,0,0.04)] dark:shadow-[0_2px_15px_0_rgba(0,0,0,0.12)]
              transition-all duration-250 ease-out
        "
        style="padding: 0;"
      >
        <select
          :id="id"
          :name="name"
          :disabled="disabled"
          :required="required"
          :value="modelValue"
          @change="onChange"
          :class="[
            'block w-full appearance-none bg-transparent border-0 outline-none',
            'text-neutral-900 dark:text-neutral-100 text-[15px] font-normal',
            'pl-4 pr-10 py-3', // Espaciado interior a juego con el input
            'focus:outline-none',
            'transition-all duration-250 ease-out',
            'rounded-xl',
            'bg-transparent', // Hereda fondo del wrapper
            'disabled:bg-transparent dark:disabled:bg-transparent disabled:opacity-60',
            classSelect
          ]"
        >
          <option
            v-if="placeholder"
            disabled
            :selected="modelValue === ''"
            value=""
            class="text-neutral-400 dark:text-neutral-600"
          >
            {{ placeholder }}
          </option>
          <option
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            :selected="option.value === modelValue"
            class="text-neutral-900 dark:text-neutral-100"
          >
            {{ option.label }}
          </option>
        </select>
        <!-- Flecha de dropdown personalizada usando SVG -->
        <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 flex items-center z-10 text-base text-neutral-400 dark:text-neutral-500 transition-colors duration-200">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 8l4 4 4-4" />
          </svg>
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