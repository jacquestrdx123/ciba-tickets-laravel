<script setup>
import { computed } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
    type: { type: String, default: 'button' },
    color: { type: String, default: 'primary' },
    variant: { type: String, default: 'solid' },
    size: { type: String, default: 'md' },
    icon: { type: String, default: null },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
})

const emit = defineEmits(['click'])

const variantClasses = {
    solid: {
        primary: 'bg-primary-600 text-white hover:bg-primary-500 shadow-sm',
        gray: 'bg-gray-800 text-white hover:bg-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600',
        sky: 'bg-sky-600 text-white hover:bg-sky-500',
        amber: 'bg-amber-600 text-white hover:bg-amber-500',
    },
    soft: {
        primary: 'bg-primary-500/10 text-primary-700 hover:bg-primary-500/20 dark:text-primary-300',
        gray: 'bg-gray-500/10 text-gray-700 hover:bg-gray-500/20 dark:text-gray-300',
        sky: 'bg-sky-500/10 text-sky-700 hover:bg-sky-500/20 dark:text-sky-300',
        amber: 'bg-amber-500/10 text-amber-800 hover:bg-amber-500/20 dark:text-amber-200',
    },
    outline: {
        primary: 'ring-1 ring-inset ring-primary-500/30 text-primary-700 hover:bg-primary-500/10 dark:text-primary-300',
        gray: 'ring-1 ring-inset ring-gray-300 text-gray-700 hover:bg-gray-50 dark:ring-gray-700 dark:text-gray-300 dark:hover:bg-gray-800',
        sky: 'ring-1 ring-inset ring-sky-500/30 text-sky-700 hover:bg-sky-500/10 dark:text-sky-300',
        amber: 'ring-1 ring-inset ring-amber-500/30 text-amber-800 hover:bg-amber-500/10 dark:text-amber-200',
    },
    ghost: {
        primary: 'text-primary-600 hover:bg-primary-500/10 dark:text-primary-400',
        gray: 'text-gray-600 hover:bg-gray-500/10 dark:text-gray-400',
        sky: 'text-sky-600 hover:bg-sky-500/10 dark:text-sky-400',
        amber: 'text-amber-700 hover:bg-amber-500/10 dark:text-amber-300',
    },
}

const sizeClasses = {
    xs: 'px-2 py-1 text-xs gap-1',
    sm: 'px-2.5 py-1.5 text-sm gap-1.5',
    md: 'px-3 py-2 text-sm gap-2',
    lg: 'px-4 py-2.5 text-sm gap-2',
}

const iconSizes = {
    xs: 'h-3.5 w-3.5',
    sm: 'h-4 w-4',
    md: 'h-4 w-4',
    lg: 'h-5 w-5',
}

const classes = computed(() => {
    const variants = variantClasses[props.variant] ?? variantClasses.solid
    const colorClass = variants[props.color] ?? variants.primary ?? variants.gray
    return [
        'inline-flex items-center justify-center rounded-lg font-medium transition-colors',
        'disabled:opacity-50 disabled:pointer-events-none',
        sizeClasses[props.size] ?? sizeClasses.md,
        colorClass,
        props.block ? 'w-full' : '',
    ]
})
</script>

<template>
    <button
        :type="type"
        :class="classes"
        :disabled="disabled || loading"
        @click="emit('click', $event)"
    >
        <Icon v-if="loading" name="svg-spinners:ring-resize" :class="iconSizes[size] ?? iconSizes.md" />
        <Icon v-else-if="icon" :name="icon" :class="iconSizes[size] ?? iconSizes.md" />
        <slot />
    </button>
</template>
