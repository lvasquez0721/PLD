<template>
  <button ref="buttonRef" :class="[
    'custom-btn',
    'bg-blue-600',
    'hover:bg-blue-700',
    'active:bg-blue-800',
    'focus-visible:ring-2',
    'focus-visible:ring-offset-2',
    'focus-visible:ring-blue-400',
    'transition-all',
    'duration-300',
    'ease-[cubic-bezier(0.4,0,0.2,1)]',
    'shadow-[0_2px_10px_rgba(47,82,138,0.16)]',
    'text-white',
    'font-medium',
    'rounded-[2rem]',         // Aumenta suavidad del border radius
    'px-4',
    'py-2.5',
    'tracking-tight',
    'relative',
    'overflow-hidden',
    'group',
    'before:absolute',
    'before:inset-0',
    'before:bg-gradient-to-r',
    'before:from-transparent',
    'before:via-white/8',
    'before:to-transparent',
    'before:translate-x-[-100%]',
    'before:transition-transform',
    'before:duration-700',
    'hover:before:translate-x-[100%]',
    'hover:shadow-[0_4px_20px_rgba(47,82,138,0.25)]',
    'hover:scale-[1.02]',
    'active:scale-[0.98]',
    'active:shadow-[0_1px_5px_rgba(47,82,138,0.3)]',
    customTextClass
  ]" v-bind="$attrs" @mouseenter="handleMouseEnter" @mouseleave="handleMouseLeave" @mousedown="handleMouseDown"
    @mouseup="handleMouseUp">
    <!-- Capa de brillo principal -->
    <span class="btn-text relative z-10">
      <slot />
    </span>

    <!-- Efecto de partículas sutiles (solo visible en hover) -->
    <div
      class="particles-container absolute inset-0 pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-500">
      <span class="particle particle-1"></span>
      <span class="particle particle-2"></span>
      <span class="particle particle-3"></span>
    </div>

    <!-- Gradiente de profundidad (solo para ojos expertos) -->
    <span class="absolute inset-0 pointer-events-none select-none opacity-[0.03]" aria-hidden="true">
      <span
        class="block w-full h-full bg-gradient-to-br from-white/25 via-blue-300/8 to-blue-950/20 blur-[1px] rounded-[2rem] animate-micro-glow"></span>
    </span>

    <!-- Borde interno sutil -->
    <span class="absolute inset-0 rounded-[2rem] border border-white/5 pointer-events-none"></span>

    <!-- Efecto de ondas al hacer click -->
    <span class="ripple-effect absolute inset-0 pointer-events-none opacity-0"></span>
  </button>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

// Permitimos personalización mínima del texto por prop, aunque no es visible
const props = defineProps<{
  textClass?: string
  variant?: 'primary' | 'secondary' | 'ghost'
  size?: 'sm' | 'md' | 'lg'
}>();

const customTextClass = props.textClass || '';
const buttonRef = ref<HTMLButtonElement>()

// Estado para micro-interacciones
let hoverStartTime = 0
let isPressed = false
let animationFrameId: number | null = null

// Efectos de micro-interacción avanzados
const handleMouseEnter = () => {
  hoverStartTime = Date.now()
  if (buttonRef.value) {
    buttonRef.value.style.transform = 'translateZ(0)' // Activa aceleración por hardware
  }
}

const handleMouseLeave = () => {
  const hoverDuration = Date.now() - hoverStartTime
  if (buttonRef.value && hoverDuration < 100) {
    // Efecto de "rechazo" si el hover es muy rápido
    buttonRef.value.style.transform = 'scale(0.99) translateZ(0)'
    setTimeout(() => {
      if (buttonRef.value) {
        buttonRef.value.style.transform = ''
      }
    }, 150)
  }
}

const handleMouseDown = (event: MouseEvent) => {
  isPressed = true
  createRippleEffect(event)

  // Micro-vibración sutil (si está disponible)
  if ('vibrate' in navigator) {
    navigator.vibrate(10)
  }
}

const handleMouseUp = () => {
  isPressed = false
}

// Efecto ripple personalizado
const createRippleEffect = (event: MouseEvent) => {
  if (!buttonRef.value) return

  const rect = buttonRef.value.getBoundingClientRect()
  const size = Math.max(rect.width, rect.height)
  const x = event.clientX - rect.left - size / 2
  const y = event.clientY - rect.top - size / 2

  const ripple = buttonRef.value.querySelector('.ripple-effect') as HTMLElement
  if (ripple) {
    ripple.style.width = ripple.style.height = size + 'px'
    ripple.style.left = x + 'px'
    ripple.style.top = y + 'px'
    ripple.style.opacity = '1'

    // Reset después de la animación
    setTimeout(() => {
      ripple.style.opacity = '0'
      ripple.style.width = ripple.style.height = '0'
    }, 600)
  }
}

// Efecto de parallax sutil en movimiento del mouse
const handleMouseMove = (event: MouseEvent) => {
  if (!buttonRef.value || !isPressed) return

  const rect = buttonRef.value.getBoundingClientRect()
  const x = (event.clientX - rect.left) / rect.width
  const y = (event.clientY - rect.top) / rect.height

  const tiltX = (y - 0.5) * 2
  const tiltY = (x - 0.5) * 2

  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId)
  }

  animationFrameId = requestAnimationFrame(() => {
    if (buttonRef.value) {
      buttonRef.value.style.transform = `perspective(1000px) rotateX(${tiltX * 2}deg) rotateY(${tiltY * 2}deg) translateZ(0)`
    }
  })
}

// Cleanup
onMounted(() => {
  if (buttonRef.value) {
    buttonRef.value.addEventListener('mousemove', handleMouseMove)
  }
})

onUnmounted(() => {
  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId)
  }
})
</script>

<style scoped>
.custom-btn {
  /* Sistema de capas avanzado para expertos */
  will-change: transform, box-shadow, filter;
  backface-visibility: hidden;
  transform-style: preserve-3d;

  /* Microdetalle: border inferior sutil con gradiente dinámico */
  box-shadow:
    0 1.5px 0 0 rgba(27, 53, 120, 0.08) inset,
    0 0 0 1px rgba(255, 255, 255, 0.03),
    0 2px 8px rgba(0, 0, 0, 0.04);

  letter-spacing: -0.008em;
  font-weight: 500;

  /* Gradiente multicapa para profundidad */
  background-image:
    linear-gradient(135deg, rgba(255, 255, 255, 0.04) 0%, rgba(43, 102, 204, 0.12) 50%, rgba(27, 53, 120, 0.08) 100%),
    linear-gradient(45deg, rgba(255, 255, 255, 0.02) 0%, transparent 100%);

  /* Efecto de textura sutil */
  background-size: 100% 100%, 200% 200%;
  background-position: 0 0, 0 0;

  /* Añadir un poco más de border-radius */
  border-radius: 2rem;

  /* Transiciones multicapa */
  transition:
    all 0.3s cubic-bezier(0.4, 0, 0.2, 1),
    background-position 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* Estados de interacción avanzados */
.custom-btn:hover {
  background-image:
    linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(43, 102, 204, 0.18) 50%, rgba(27, 53, 120, 0.12) 100%),
    linear-gradient(45deg, rgba(255, 255, 255, 0.04) 0%, transparent 100%);
  background-position: 0 0, -100% -100%;

  /* Efecto de elevación sutil */
  filter: brightness(1.02) contrast(1.01);
}

.custom-btn:active {
  background-image:
    linear-gradient(135deg, rgba(255, 255, 255, 0.02) 0%, rgba(43, 102, 204, 0.08) 50%, rgba(27, 53, 120, 0.06) 100%);
  filter: brightness(0.98) contrast(0.99);
}

/* Modo oscuro con efectos especializados */
@media (prefers-color-scheme: dark) {
  .custom-btn {
    background-image:
      linear-gradient(135deg, rgba(41, 92, 192, 0.08) 0%, rgba(36, 40, 62, 0.20) 50%, rgba(20, 25, 45, 0.15) 100%),
      linear-gradient(45deg, rgba(31, 204, 255, 0.03) 0%, transparent 100%);
    box-shadow:
      0 1.5px 0 0 rgba(31, 204, 255, 0.10) inset,
      0 0 0 1px rgba(31, 204, 255, 0.05),
      0 2px 12px rgba(0, 0, 0, 0.3);
  }

  .custom-btn:hover {
    background-image:
      linear-gradient(135deg, rgba(41, 92, 192, 0.12) 0%, rgba(36, 40, 62, 0.25) 50%, rgba(20, 25, 45, 0.20) 100%),
      linear-gradient(45deg, rgba(31, 204, 255, 0.05) 0%, transparent 100%);
  }
}

/* Texto con efectos tipográficos avanzados */
.btn-text {
  position: relative;
  z-index: 10;
  display: inline-block;

  /* Optimización de renderizado */
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
  font-variant-ligatures: contextual;

  /* Sistema de sombras multicapa */
  text-shadow:
    0 1px 0 rgba(255, 255, 255, 0.15),
    0 0.5px 0 rgba(255, 255, 255, 0.08),
    0 0 0 rgba(27, 53, 120, 0.06),
    0 0 8px rgba(255, 255, 255, 0.02);

  /* Efecto de profundidad tipográfica */
  filter: drop-shadow(0 0 1px rgba(255, 255, 255, 0.1));

  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.custom-btn:hover .btn-text {
  text-shadow:
    0 1px 0 rgba(255, 255, 255, 0.20),
    0 0.5px 0 rgba(255, 255, 255, 0.12),
    0 0 0 rgba(27, 53, 120, 0.08),
    0 0 12px rgba(255, 255, 255, 0.04);
}

.custom-btn:active .btn-text {
  text-shadow:
    0 1px 2px rgba(0, 0, 0, 0.15),
    0 0.5px 1px rgba(0, 0, 0, 0.08),
    0 0 0 rgba(27, 53, 120, 0.04);
  transform: translateY(0.5px);
}

/* Sistema de partículas sutiles */
.particles-container {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
}

.particle {
  position: absolute;
  width: 2px;
  height: 2px;
  background: rgba(255, 255, 255, 0.4);
  border-radius: 50%;
  animation: particle-float 3s ease-in-out infinite;
}

.particle-1 {
  top: 20%;
  left: 15%;
  animation-delay: 0s;
  animation-duration: 2.8s;
}

.particle-2 {
  top: 60%;
  right: 20%;
  animation-delay: 0.8s;
  animation-duration: 3.2s;
}

.particle-3 {
  bottom: 25%;
  left: 60%;
  animation-delay: 1.6s;
  animation-duration: 2.6s;
}

@keyframes particle-float {

  0%,
  100% {
    opacity: 0;
    transform: translateY(0) scale(0.8);
  }

  50% {
    opacity: 1;
    transform: translateY(-8px) scale(1.2);
  }
}

/* Efecto ripple personalizado */
.ripple-effect {
  background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
  border-radius: 50%;
  transform: scale(0);
  animation: ripple-expand 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  pointer-events: none;
}

@keyframes ripple-expand {
  to {
    transform: scale(1);
    opacity: 0;
  }
}

/* Animaciones de micro-glow avanzadas */
@keyframes micro-glow {
  0% {
    opacity: 0.03;
    transform: translateY(-2px) scale(1.01);
  }

  33% {
    opacity: 0.06;
    transform: translateY(1px) scale(1.005);
  }

  66% {
    opacity: 0.04;
    transform: translateY(-1px) scale(1.008);
  }

  100% {
    opacity: 0.03;
    transform: translateY(-2px) scale(1.01);
  }
}

.animate-micro-glow {
  animation: micro-glow 8s cubic-bezier(0.68, 0.02, 0.33, 1) infinite;
}

/* Efectos de focus avanzados para accesibilidad */
.custom-btn:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 2px rgba(59, 130, 246, 0.5),
    0 0 0 4px rgba(59, 130, 246, 0.1),
    0 1.5px 0 0 rgba(27, 53, 120, 0.08) inset;
}

/* Optimizaciones de rendimiento */
.custom-btn * {
  backface-visibility: hidden;
  transform-style: preserve-3d;
}

/* Efectos de presión táctil para dispositivos móviles */
@media (hover: none) and (pointer: coarse) {
  .custom-btn:active {
    transform: scale(0.96);
    transition: transform 0.1s ease-out;
  }
}

/* Reducción de movimiento para usuarios sensibles */
@media (prefers-reduced-motion: reduce) {

  .custom-btn,
  .btn-text,
  .particle,
  .ripple-effect,
  .animate-micro-glow {
    animation: none;
    transition: none;
  }

  .custom-btn:hover {
    transform: none;
  }
}
</style>
