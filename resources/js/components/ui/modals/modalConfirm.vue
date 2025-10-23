<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'

const props = defineProps<{
  modelValue: boolean
  loading?: boolean
  persistent?: boolean  // Si true: no se puede cerrar haciendo click fuera ni esc.
  widthClass?: string
}>()

const emits = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const isOpen = ref(props.modelValue)
const dialogRef = ref<HTMLDivElement | null>(null)

watch(() => props.modelValue, async (val) => {
  isOpen.value = val
  handleBodyScroll(val)
  // Auto focus en primer focusable
  if (val) await nextTick(() => {
    const dialog = dialogRef.value
    if (!dialog) return
    const focusable = dialog.querySelector<HTMLElement>(
      'button, [href], input:not([type=hidden]), select, textarea, [tabindex]:not([tabindex="-1"])'
    )
    focusable?.focus()
  })
})

function closeModal() {
  if (props.persistent) return
  isOpen.value = false
  emits('update:modelValue', false)
  emits('cancel')
  handleBodyScroll(false)
}

function handleOverlay(e: MouseEvent) {
  if (e.target === e.currentTarget && !props.persistent) {
    closeModal()
  }
}

function onEsc(e: KeyboardEvent) {
  if (e.key === 'Escape' && isOpen.value && !props.persistent) {
    e.stopPropagation()
    closeModal()
  }
  // Focus trap
  if (e.key === 'Tab' && isOpen.value) {
    trapTabFocus(e)
  }
}

function handleBodyScroll(lock: boolean) {
  if (typeof document === "undefined") return
  document.body.style.overflow = lock ? "hidden" : ""
}

function trapTabFocus(e: KeyboardEvent) {
  const dialog = dialogRef.value
  if (!dialog) return
  const focusableEls = dialog.querySelectorAll<HTMLElement>(
    'button, [href], input:not([type=hidden]), select, textarea, [tabindex]:not([tabindex="-1"])'
  )
  const focusable = Array.from(focusableEls).filter(el => !el.hasAttribute('disabled'))
  if (!focusable.length) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]

  if (!e.shiftKey && document.activeElement === last) {
    first.focus()
    e.preventDefault()
  }
  if (e.shiftKey && document.activeElement === first) {
    last.focus()
    e.preventDefault()
  }
}

function emitConfirm() {
  emits('confirm')
}
function emitCancel() {
  closeModal()
}

onMounted(() => {
  if (isOpen.value) {
    handleBodyScroll(true)
  }
  window.addEventListener('keydown', onEsc, true)
})
onBeforeUnmount(() => {
  handleBodyScroll(false)
  window.removeEventListener('keydown', onEsc, true)
})
</script>

<template>
  <transition name="fade-modal">
    <div v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 dark:bg-black/70 backdrop-blur-sm transition-all"
      :aria-modal="true" :role="'dialog'" aria-label="Confirmar acción" tabindex="-1" @click="handleOverlay"
      style="animation: fade-bg 0.26s cubic-bezier(.33,.75,.68,1.03)">
      <transition name="modal-bounce-improved">
        <div ref="dialogRef"
          class="relative mx-2 w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500"
          :class="widthClass ? widthClass : 'max-w-sm'" tabindex="-1">
          <form class="relative rounded-2xl shadow-2xl bg-white dark:bg-gray-900
            p-3 px-1 sm:p-7 sm:px-8 border border-slate-200/70 dark:border-slate-700/60 ring-1 ring-black/7.5"
            @submit.prevent @click.stop>
            <!-- Icono (custom o default) -->
            <div class="flex flex-col items-center mb-4">
              <slot name="icon">
                <!-- Icono por defecto (puedes personalizar o dejar vacío) -->
              </slot>
              <p class="text-lg font-semibold mb-2 text-center">
                <slot name="title">
                  ¿Estás seguro?
                </slot>
              </p>
              <p class="text-sm text-gray-700 dark:text-gray-300 text-center mb-2">
                <slot name="description">
                  ¿Seguro que deseas continuar con esta acción?
                </slot>
              </p>
            </div>
            <div class="flex justify-end gap-2 mt-1">
              <button type="button" class="btn btn-sm btn-secondary" @click="emitCancel" :disabled="loading">
                <slot name="cancel">
                  Cancelar
                </slot>
              </button>
              <button type="button" class="btn btn-sm btn-danger" @click="emitConfirm" :disabled="loading">
                <slot name="confirm">
                  {{ loading ? 'Eliminando...' : 'Eliminar' }}
                </slot>
              </button>
            </div>
          </form>
        </div>
      </transition>
    </div>
  </transition>
</template>

<style scoped>
.fade-modal-enter-active,
.fade-modal-leave-active {
  transition: opacity .26s cubic-bezier(.33, .75, .68, 1.03);
}

.fade-modal-enter-from,
.fade-modal-leave-to {
  opacity: 0;
}

.modal-bounce-improved-enter-active {
  animation: bounce-in-modal 0.34s cubic-bezier(.4, 1.5, .7, 1) both;
}

.modal-bounce-improved-leave-active {
  animation: bounce-out-modal 0.19s cubic-bezier(.83, 0, .35, 1.05) forwards;
}

@keyframes bounce-in-modal {
  0% {
    transform: scale(.96) translateY(60px);
    opacity: .6;
  }

  60% {
    transform: scale(1.04) translateY(-8px);
    opacity: 1;
  }

  100% {
    transform: scale(1.0) translateY(0);
    opacity: 1;
  }
}

@keyframes bounce-out-modal {
  0% {
    transform: scale(1.0);
    opacity: 1;
  }

  85% {
    transform: scale(.96) translateY(14px);
    opacity: 0;
  }

  100% {
    transform: scale(.95) translateY(40px);
    opacity: 0;
  }
}

@keyframes fade-bg {
  0% {
    background-color: rgba(0, 0, 0, 0);
  }

  100% {
    background-color: rgba(0, 0, 0, 0.60);
  }
}
</style>
