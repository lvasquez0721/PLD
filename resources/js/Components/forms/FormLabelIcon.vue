<script setup lang="ts">
defineProps<{
  forId?: string
  label: string
  icon?: any
}>();
</script>

<template>
  <label
    :for="forId"
    class="block text-[11px] font-medium text-slate-600/85 dark:text-neutral-300/85 mb-3 tracking-[0.05em] uppercase flex items-center gap-2 transition-colors duration-500"
  >
    <component
      v-if="icon"
      :is="icon"
      :size="13"
      class="opacity-55 transition-opacity duration-500 dark:text-neutral-300/80"
    />
    <span class="leading-none">{{ label }}</span>
  </label>
</template>

<style scoped>
</style>
