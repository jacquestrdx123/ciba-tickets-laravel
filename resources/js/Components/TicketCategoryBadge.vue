<script setup>
import { computed } from 'vue'

const props = defineProps({
    category: { type: Object, default: null },
    size: { type: String, default: 'sm' },
})

const sizeClasses = {
    xs: 'px-1.5 py-0.5 text-[11px]',
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm',
}

const style = computed(() => {
    const color = props.category?.color
    if (!color) return null

    return {
        backgroundColor: `${color}18`,
        color,
        boxShadow: `inset 0 0 0 1px ${color}40`,
    }
})

const classes = computed(() => [
    'inline-flex max-w-full items-center rounded-md font-medium',
    sizeClasses[props.size] ?? sizeClasses.sm,
])
</script>

<template>
    <span v-if="category?.name" :class="classes" :style="style">
        <span class="truncate">{{ category.name }}</span>
    </span>
    <span v-else class="text-gray-400">—</span>
</template>
