<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'

const props = defineProps<{
  modelValue: boolean,              // Control de visibilidad externa
  message: string,                  // El mensaje del toast
  type?: 'success' | 'warning' | 'error', // Tipo del toast
  duration?: number,                // Tiempo en ms antes de ocultar auto
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

const visible = ref(props.modelValue)

watch(() => props.modelValue, (v) => {
  visible.value = v
  if (v) setupTimeout()
})

// Mostrar automáticamente y ocultar tras X tiempo
let timeout: any = null
function setupTimeout() {
  clearTimeout(timeout)
  if (props.duration !== 0 && visible.value) {
    timeout = setTimeout(() => {
      visible.value = false
      emit('update:modelValue', false)
    }, props.duration ?? 3000)
  }
}

function close() {
  visible.value = false
  emit('update:modelValue', false)
}

watch(visible, (v) => {
  if (v) setupTimeout()
})

// Iconos para cada tipo, minimalistas y editables
const icon = computed(() => {
  switch (props.type) {
    case 'success':
      return `<svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>`
    case 'error':
      return `<svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 8l8 8M16 8l-8 8" /></svg>`
    case 'warning':
      return `<svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3C7.03 3 3 7.03 3 12s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9z"/></svg>`
    default:
      return ''
  }
})

const toastClasses = computed(() => {
  switch (props.type) {
    case 'success':
      return "border-green-500 bg-green-50 text-green-800"
    case 'error':
      return "border-red-500 bg-red-50 text-red-800"
    case 'warning':
      return "border-yellow-500 bg-yellow-50 text-yellow-900"
    default:
      return "border-gray-300 bg-white text-gray-900"
  }
})
</script>

<template>
  <transition name="toast-fade">
    <div v-if="visible"
      class="fixed top-5 right-5 z-[100] min-w-[270px] max-w-xs rounded-lg border shadow-lg p-4 flex items-center gap-3 transition-all"
      :class="toastClasses" role="alert" aria-live="polite">
      <!-- icon -->
      <span v-if="icon" class="shrink-0" v-html="icon"></span>
      <span class="flex-1 break-words text-[15px] font-medium">{{ message }}</span>
      <button class="ml-2 text-xl/none text-gray-400 hover:text-gray-600 focus:outline-none" aria-label="Cerrar"
        @click="close" tabindex="0">&times;</button>
    </div>
  </transition>
</template>

<style scoped>
.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: opacity .24s cubic-bezier(.32, .75, .79, 1.05), transform .21s;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
  transform: translateY(-22px) scale(.98);
}
</style>
