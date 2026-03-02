<script setup lang="ts">
import { ref, onMounted } from 'vue'

defineProps<{
  modelValue: string
  id?: string
  name?: string
  required?: boolean
  disabled?: boolean
  classInput?: string
}>()

const emit = defineEmits(['update:modelValue'])

const isDark = ref(false)
const focused = ref(false)

function onInput(e: Event) {
  const el = e.target as HTMLInputElement
  emit('update:modelValue', el.value)
}

onMounted(() => {
  const checkTheme = () => {
    isDark.value = document.documentElement.classList.contains('dark')
  }
  checkTheme()
  const observer = new MutationObserver(checkTheme)
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
})
</script>

<template>
  <div class="relative">
    <input
      type="date"
      :id="id"
      :name="name"
      :required="required"
      :disabled="disabled"
      :value="modelValue"
      @input="onInput"
      @focus="focused = true"
      @blur="focused = false"
      :class="[
        'w-full px-4 py-3.5 border rounded-[14px] text-[14px] font-light tracking-[0.003em] transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1)',
        isDark
          ? (focused ? 'custom-dark-date-focused' : 'custom-dark-date')
          : (focused ? 'custom-light-date-focused' : 'custom-light-date'),
        classInput
      ]"
    />
  </div>
  </template>

<style scoped>
input[type="date"]:focus-visible {
  outline: 2px solid rgb(59 130 246 / 0.3);
  outline-offset: 2px;
}

.custom-light-date {
  background-color: rgba(255, 255, 255, 0.95) !important;
  color: #1e293b !important;
  border: 1px solid rgba(226, 232, 240, 0.7);
}
.custom-light-date:hover {
  border-color: rgba(226, 232, 240, 0.85);
  background-color: #fff;
}
.custom-light-date-focused {
  background-color: #fff !important;
  color: #0f172a !important;
  border: 1px solid rgba(147, 197, 253, 0.5);
  box-shadow: 0 3px 12px rgba(59, 130, 246, 0.07);
  transform: scale(1.005);
}

.custom-dark-date {
  background-color: rgba(31, 31, 31, 0.60) !important;
  color: #fafbfc !important;
  border: 1px solid rgba(38, 38, 38, 0.65);
}
.custom-dark-date:hover {
  border-color: rgba(163, 163, 163, 0.32);
  background-color: rgba(31, 31, 31, 0.85);
}
.custom-dark-date-focused {
  background-color: rgba(23, 23, 23, 0.95) !important;
  color: #fafbfc !important;
  border: 1px solid rgba(147, 197, 253, 0.5);
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.15);
  transform: scale(1.005);
}

input[type="date"]::-webkit-inner-spin-button,
input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(0);
}
.custom-dark-date::-webkit-calendar-picker-indicator,
.custom-dark-date-focused::-webkit-calendar-picker-indicator {
  filter: invert(1);
}
.custom-dark-date::-moz-calendar-picker-indicator,
.custom-dark-date-focused::-moz-calendar-picker-indicator {
  filter: invert(1);
}
</style>
