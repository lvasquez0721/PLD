<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Titulo from '../Titulo.vue';

// Mejorado: Accesibilidad y flexibilidad en props
const props = defineProps<{
  modelValue: boolean;
  title?: string;
  subtitle?: string;
  widthClass?: string; // e.g. "max-w-lg"
  persistent?: boolean; // si true, el backdrop no cierra el modal
  hideCloseIcon?: boolean; // Nuevo: permite ocultar el botón de cerrar
}>();

const emit = defineEmits<{
  (e: 'close'): void,
  (e: 'update:modelValue', value: boolean): void
}>();

const isOpen = ref(props.modelValue);
const dialogRef = ref<HTMLDivElement | null>(null);

watch(() => props.modelValue, async (val) => {
  isOpen.value = val;
  handleBodyScroll(val);
  // Mejora UX: focus automático dentro del modal al abrir
  if (val) await nextTick(() => {
    // Busca primer elemento focusable dentro del modal
    const dialog = dialogRef.value;
    if (!dialog) return;
    const focusable = dialog.querySelector<HTMLElement>(
      'button, [href], input:not([type=hidden]), select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    focusable?.focus();
  });
});

// Gestión cierre
function closeModal() {
  if (props.persistent) return;
  isOpen.value = false;
  emit('update:modelValue', false);
  emit('close');
  handleBodyScroll(false);
}

// Soporta backdrop/persistent y mejora ux con animación mejorada
function handleOverlay(e: MouseEvent) {
  if (e.target === e.currentTarget && !props.persistent) {
    closeModal();
  }
}

// Accesibilidad: escape cierra solo si permitido y mejora con focus trap
function onEsc(e: KeyboardEvent) {
  if (e.key === 'Escape' && isOpen.value && !props.persistent) {
    e.stopPropagation();
    closeModal();
  }
  // Optional: Tab focus trap
  if (e.key === 'Tab' && isOpen.value) {
    trapTabFocus(e);
  }
}

// UX: evitar scroll de fondo cuando abierto
function handleBodyScroll(lock: boolean) {
  if (typeof document === "undefined") return;
  document.body.style.overflow = lock ? "hidden" : "";
}

// --- Focus trap para mejor UX y accesibilidad ---
function trapTabFocus(e: KeyboardEvent) {
  const dialog = dialogRef.value;
  if (!dialog) return;
  const focusableEls = dialog.querySelectorAll<HTMLElement>(
    'button, [href], input:not([type=hidden]), select, textarea, [tabindex]:not([tabindex="-1"])'
  );
  const focusable = Array.from(focusableEls).filter(el => !el.hasAttribute('disabled'));
  if (!focusable.length) return;

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (!e.shiftKey && document.activeElement === last) {
    first.focus();
    e.preventDefault();
  }
  if (e.shiftKey && document.activeElement === first) {
    last.focus();
    e.preventDefault();
  }
}

onMounted(() => {
  if (isOpen.value) {
    handleBodyScroll(true);
  }
  window.addEventListener('keydown', onEsc, true);
});
onBeforeUnmount(() => {
  handleBodyScroll(false);
  window.removeEventListener('keydown', onEsc, true);
});
</script>

<template>
  <transition name="fade-modal">
    <div v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 dark:bg-black/70 backdrop-blur-sm transition-all"
      :aria-modal="true" :role="'dialog'" aria-label="Modal" tabindex="-1" @click="handleOverlay"
      style="animation: fade-bg 0.26s cubic-bezier(.33,.75,.68,1.03)">
      <transition name="modal-bounce-improved">
        <!-- Referencia para focus trap -->
        <div ref="dialogRef" class="relative mx-2 w-full
            focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500"
          :class="widthClass ? widthClass : 'max-w-lg'" tabindex="-1">
          <form class="relative rounded-2xl shadow-2xl bg-white dark:bg-zinc-900
            p-3 px-1 sm:p-7 sm:px-8 border border-slate-200/70 dark:border-slate-700/60
            ring-1 ring-black/7.5 modal-inner-form-fix" @submit.prevent @click.stop>
            <!-- HEADER: título y botón cerrar alineados horizontalmente y centrados verticalmente -->
            <div class="flex items-center justify-between gap-4 mb-5 min-h-[2.5rem]">
              <!-- El título siempre aparece a la misma altura del botón de cerrar -->
              <div class="flex-1 min-w-0">
                <Titulo v-if="title" :title="title" :size="'md'" :weight="'bold'" :color="'default'" />
              </div>
              <button v-if="!persistent && !hideCloseIcon" type="button" @click="closeModal"
                class="ml-2 shrink-0 rounded-lg p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800/80 text-gray-500 dark:text-gray-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 transition"
                aria-label="Cerrar modal">
                <span class="sr-only">Cerrar modal</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <p v-if="subtitle" class="text-xs text-muted-foreground mt-1 mb-5 sm:text-sm">
              {{ subtitle }}
            </p>
            <!-- Formulario o contenido -->
            <div class="custom-scroll modal-form-content-xfix"
              style="max-height: 58vh; overflow-y: auto; scrollbar-width: thin;">
              <slot />
            </div>
          </form>
        </div>
      </transition>
    </div>
  </transition>
</template>

<style scoped>
/* Entradas y salidas más suaves, más “presencia” UX */
.fade-modal-enter-active,
.fade-modal-leave-active {
  transition: opacity .26s cubic-bezier(.33, .75, .68, 1.03);
}

.fade-modal-enter-from,
.fade-modal-leave-to {
  opacity: 0;
}

/* Modal con rebote más elegante */
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

/* Fondo difuminado ligeramente más marcado para focus visual UX */
@keyframes fade-bg {
  0% {
    background-color: rgba(0, 0, 0, 0);
  }

  100% {
    background-color: rgba(0, 0, 0, 0.60);
  }
}

/* Scrollbar minimalista para contenido */
.custom-scroll::-webkit-scrollbar {
  width: 6px;
  background: transparent;
}

.custom-scroll::-webkit-scrollbar-thumb {
  background: #d3d8e2;
  border-radius: 3px;
}

.dark .custom-scroll::-webkit-scrollbar-thumb {
  background: #262e38;
}

/* Botón de cerrar con foco visible para accesibilidad */
button:focus-visible {
  outline: 2px solid #2563eb;
  outline-offset: 2px;
}

/* Añade recorte lateral al contenido del formulario del modal */
.modal-form-content-xfix {
  padding-left: 5px !important;
  padding-right: 5px !important;
}

/* Puedes ajustar el relleno general del form si hace falta reducir todavía más los bordes laterales */
.modal-inner-form-fix {
  padding-left: 0.25rem !important;
  padding-right: 0.25rem !important;
}

@media (min-width: 640px) {
  .modal-inner-form-fix {
    padding-left: 1.5rem !important;
    padding-right: 1.5rem !important;
  }

  .modal-form-content-xfix {
    padding-left: 0.5rem !important;
    padding-right: 0.5rem !important;
  }
}
</style>
