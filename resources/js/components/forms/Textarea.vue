<script setup lang="ts">
import { defineProps, defineEmits, useSlots } from 'vue';

const props = defineProps<{
  modelValue: string
  label?: strings
  placeholder?: string
  id?: string
  name?: string
  rows?: number | string
  required?: boolean
  classTextarea?: string
  classLabel?: string
  disabled?: boolean
}>();

const emit = defineEmits(['update:modelValue']);
const slots = useSlots();

function onInput(event: Event) {
  const textarea = event.target as HTMLTextAreaElement;
  emit('update:modelValue', textarea.value);
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
      <span v-if="required" class="text-red-600 ml-1">*</span>
    </label>
    <!-- Textarea within styled wrapper (follows Input.vue) -->
    <div
      class="flex w-full relative border border-neutral-200 dark:border-neutral-800/50 rounded-xl 
            group-focus-within:border-neutral-400 dark:group-focus-within:border-neutral-700 
            group-focus-within:ring-1 group-focus-within:ring-neutral-500/40 dark:group-focus-within:ring-neutral-400/30
            bg-gradient-to-b from-neutral-50 to-neutral-100 dark:from-neutral-950 dark:to-neutral-900
            shadow-[0_1.5px_8px_0_rgba(0,0,0,0.04)] dark:shadow-[0_2px_15px_0_rgba(0,0,0,0.12)] 
            transition-all duration-250 ease-out"
      style="padding: 0;"
    >
      <textarea
        :id="id"
        :name="name"
        :rows="rows || 4"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        @input="onInput"
        :class="[
          'block w-full bg-transparent border-0 outline-none resize-none',
          'text-neutral-900 dark:text-neutral-100',
          'placeholder:text-neutral-400 dark:placeholder:text-neutral-600',
          'text-[15px] font-normal',
          'focus:outline-none',
          'transition-all duration-250 ease-out',
          'rounded-xl',
          'p-4',
          'disabled:bg-transparent dark:disabled:bg-transparent disabled:opacity-60',
          classTextarea
        ]"
        :style="{
          minHeight: '3.25rem',
        }"
      ></textarea>
      <!-- Hover/focus overlay -->
      <span
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 rounded-xl group-hover:shadow-[0_4px_18px_0_rgba(15,23,42,0.04)] group-hover:bg-neutral-800/2 dark:group-hover:bg-neutral-800/4 transition-all duration-200 group-focus-within:shadow-[0_5px_20px_0_rgba(0,0,0,0.10)]"
      />
    </div>
  </div>
</template>