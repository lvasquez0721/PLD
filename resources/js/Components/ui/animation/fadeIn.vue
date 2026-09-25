<template>
  <transition name="fade-in" mode="out-in" appear>
    <slot />
  </transition>
</template>

<script setup lang="ts">
// FadeIn mejora: Añade 'mode="out-in"' para una experiencia más limpia al cambiar contenido.
// 'appear' asegura transición al primer render. Transiciones más suaves y naturales.
</script>

<style scoped>
.fade-in-enter-active,
.fade-in-leave-active {
  transition:
    opacity 0.35s cubic-bezier(0.33, 1, 0.68, 1),
    transform 0.3s cubic-bezier(0.33, 1, 0.68, 1);
  will-change: opacity, transform;
}

.fade-in-enter-from,
.fade-in-leave-to {
  opacity: 0;
  transform: translateY(16px) scale(0.98);
  pointer-events: none;
}

.fade-in-enter-to,
.fade-in-leave-from {
  opacity: 1;
  transform: translateY(0) scale(1);
  pointer-events: auto;
}

/* Mejora accesibilidad (reduce motion) */
@media (prefers-reduced-motion: reduce) {

  .fade-in-enter-active,
  .fade-in-leave-active {
    transition: none !important;
  }
}
</style>
