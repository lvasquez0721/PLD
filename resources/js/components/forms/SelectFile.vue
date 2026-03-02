<script setup lang="ts">
import { ref, computed, watch } from 'vue';

type FilesInputType = File[];

const props = defineProps<{
  label?: string
  id?: string
  name?: string
  required?: boolean
  disabled?: boolean
  accept?: string
  classLabel?: string
  classInput?: string
  modelValue?: File[]
}>();

const emit = defineEmits(['update:modelValue']);

const files = ref<FilesInputType>(props.modelValue ?? []);
const dragActive = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

watch(
  () => props.modelValue,
  (newVal) => {
    files.value = Array.isArray(newVal) ? newVal : [];
  }
);

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  if (input.files && input.files.length > 0) {
    const selected = Array.from(input.files);
    const existing = files.value.slice();
    const merged = mergeFiles(existing, selected);
    files.value = merged;
    emit('update:modelValue', merged);
  }
  if (inputRef.value) inputRef.value.value = '';
}

function onDrop(e: DragEvent) {
  e.preventDefault();
  dragActive.value = false;
  if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
    const dropped = Array.from(e.dataTransfer.files);
    const existing = files.value.slice();
    const merged = mergeFiles(existing, dropped);
    files.value = merged;
    emit('update:modelValue', merged);
  }
}

function onDragOver(e: DragEvent) {
  e.preventDefault();
  dragActive.value = true;
}

function onDragLeave(e: DragEvent) {
  e.preventDefault();
  dragActive.value = false;
}

const hasFiles = computed(() => files.value.length > 0);

function isImage(file: File) {
  return file.type.startsWith('image/');
}

function isPdf(file: File) {
  return file.type === 'application/pdf';
}

function objectUrl(file: File) {
  return URL.createObjectURL(file);
}

function mergeFiles(current: File[], incoming: File[]) {
  const map = new Map<string, File>();
  for (const f of current) map.set(keyOf(f), f);
  for (const f of incoming) map.set(keyOf(f), f);
  return Array.from(map.values());
}

function keyOf(f: File) {
  return `${f.name}:${f.size}:${f.type}:${(f as any).lastModified ?? ''}`;
}

function removeFile(file: File) {
  const targetKey = keyOf(file);
  const next = files.value.filter(f => keyOf(f) !== targetKey);
  files.value = next;
  emit('update:modelValue', next);
}
</script>

<template>
  <div class="mb-7 w-full">
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
    <!-- Drag + drop zone -->
    <div
      class="relative rounded-xl flex flex-col items-center justify-center border-2 border-dashed transition-colors duration-150 bg-gradient-to-b from-neutral-50 to-neutral-100 dark:from-neutral-950 dark:to-neutral-900 shadow-sm"
      :class="[
        dragActive
          ? 'border-blue-400 bg-blue-50/70 dark:border-blue-600/40 dark:bg-blue-900/20'
          : 'border-neutral-200 dark:border-neutral-700'
      ]"
      @drop="onDrop"
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @dragend="onDragLeave"
      tabindex="0"
      style="min-height: 7rem; padding: 1.25rem;"
    >
      <input
        ref="inputRef"
        type="file"
        :id="id"
        :name="name"
        :accept="accept"
        :required="required"
        :disabled="disabled"
        :multiple="true"
        @change="onFileChange"
        class="absolute inset-0 opacity-0 cursor-pointer z-10"
        :class="classInput"
        tabindex="-1"
        autocomplete="off"
      />
      <div class="flex flex-col items-center justify-center pointer-events-none select-none text-center w-full">
        <template v-if="!hasFiles">
          <svg class="mx-auto mb-2 text-slate-400 dark:text-neutral-400" width="34" height="34" viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.6" d="M4.6 15.3V17A2.7 2.7 0 007.3 19.7h9.4A2.7 2.7 0 0019.3 17v-1.7M12 4.3V15m0 0l3.2-3.2M12 15L8.8 11.8"/></svg>
          <span class="block text-[14px] font-medium text-slate-600 dark:text-neutral-200">Arrastra archivo aquí o haz clic para seleccionar</span>
          <span v-if="accept" class="block text-xs text-slate-400 dark:text-neutral-400 mt-1">Tipos aceptados: {{ accept }}</span>
          <span class="block text-xs text-slate-400 dark:text-neutral-400 mt-2">No hay archivo seleccionado actualmente.</span>
        </template>
        <template v-else></template>
      </div>
    </div>
    <div v-if="hasFiles" class="mt-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 w-full">
        <div v-for="f in files" :key="f.name" class="flex items-center gap-3 bg-slate-100/70 dark:bg-neutral-800 px-3 py-2 rounded">
          <div class="shrink-0 w-12 h-12 rounded overflow-hidden bg-white/70 dark:bg-neutral-900/70 flex items-center justify-center">
            <img v-if="isImage(f)" :src="objectUrl(f)" alt="" class="w-full h-full object-cover" />
            <div v-else class="text-slate-500 dark:text-neutral-300 text-xs">{{ isPdf(f) ? 'PDF' : (f.type || 'Archivo') }}</div>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-[13px] font-medium text-slate-700 dark:text-neutral-100 truncate">{{ f.name }}</div>
            <div class="text-[12px] text-slate-500 dark:text-neutral-400">{{ Math.round(f.size / 1024) }} KB</div>
          </div>
          <button type="button" @click="removeFile(f)" class="text-red-600 dark:text-red-400 text-[12px] font-semibold">Eliminar</button>
        </div>
      </div>
    </div>
  </div>
</template>
