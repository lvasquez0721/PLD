<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img :class="className" v-bind="$attrs"
        src="https://lh4.googleusercontent.com/proxy/7Z8aqb7VSssxM_9Pyml0MNdmJqHqmWmAc9fgmRsIqYQjPH7-_U-2oi7WVJBYIfNjVW-82MbirmDE3ZRqGuhF6hJpMRXzKbaVnSgA28op-OOl"
        alt="Logo" loading="lazy" draggable="false" style="object-fit: contain; width: 40px; height: 40px;" />
</template>
