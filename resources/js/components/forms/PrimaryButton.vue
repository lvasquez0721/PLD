<script setup lang="ts">
withDefaults(defineProps<{
  label?: string
  loadingLabel?: string
  disabled?: boolean
  loading?: boolean
  icon?: any
  type?: 'button' | 'submit' | 'reset'
}>(), {
  loadingLabel: 'Cargando...',
  type: 'button'
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="px-7 py-3.5 bg-gradient-to-br from-blue-400/90 to-blue-500/90 text-white/95 text-[14px] font-medium tracking-[0.008em] rounded-[14px]
    shadow-[0_3px_12px_rgba(59,130,246,0.13)] hover:shadow-[0_5px_18px_rgba(59,130,246,0.18)]
    hover:from-blue-500/90 hover:to-blue-600/90
    disabled:from-slate-300/80 disabled:to-slate-400/80 dark:disabled:from-neutral-800/80 dark:disabled:to-neutral-800/70 disabled:shadow-none disabled:cursor-not-allowed
    transition-all duration-700 cubic-bezier(0.25,0.1,0.25,1) transform hover:scale-[1.015] hover:-translate-y-[0.5px] active:translate-y-0 active:scale-100
    focus:outline-none focus:ring-2 focus:ring-blue-400/25 dark:focus:ring-neutral-600/50 focus:ring-offset-2 focus:ring-offset-[#f8fafc]/50 dark:focus:ring-offset-black/30"
  >
    <div class="flex items-center gap-2.5">
      <template v-if="loading">
        <div class="w-4 h-4 border-[2px] border-white/25 border-t-white/95 rounded-full animate-spin"></div>
        <span>{{ loadingLabel }}</span>
      </template>
      <template v-else>
        <component v-if="icon" :is="icon" :size="15" :stroke-width="2" class="transition-all duration-500" />
        <span v-if="label">{{ label }}</span>
        <slot v-else />
      </template>
    </div>
  </button>
</template>

<style scoped>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.animate-spin {
  animation: spin 1.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}
</style>
