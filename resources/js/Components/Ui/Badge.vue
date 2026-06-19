<script setup>
import { computed } from 'vue'

const props = defineProps({
    color: { type: String, default: 'gray' },
    variant: { type: String, default: 'subtle' },
    size: { type: String, default: 'sm' },
})

const colorClasses = {
    gray: 'bg-gray-500/10 text-gray-600 ring-gray-500/20 dark:text-gray-300',
    rose: 'bg-rose-500/10 text-rose-700 ring-rose-500/20 dark:text-rose-300',
    sky: 'bg-sky-500/10 text-sky-700 ring-sky-500/20 dark:text-sky-300',
    emerald: 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20 dark:text-emerald-300',
    amber: 'bg-amber-500/10 text-amber-800 ring-amber-500/20 dark:text-amber-200',
    yellow: 'bg-yellow-500/10 text-yellow-800 ring-yellow-500/20 dark:text-yellow-200',
    red: 'bg-red-500/10 text-red-700 ring-red-500/20 dark:text-red-300',
    green: 'bg-green-500/10 text-green-700 ring-green-500/20 dark:text-green-300',
    blue: 'bg-blue-500/10 text-blue-700 ring-blue-500/20 dark:text-blue-300',
    indigo: 'bg-indigo-500/10 text-indigo-700 ring-indigo-500/20 dark:text-indigo-300',
    primary: 'bg-primary-500/10 text-primary-700 ring-primary-500/20 dark:text-primary-300',
}

const sizeClasses = {
    xs: 'px-1.5 py-0.5 text-xs',
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm',
}

const classes = computed(() => [
    'inline-flex items-center gap-1 rounded-md font-medium ring-1 ring-inset',
    colorClasses[props.color] ?? colorClasses.gray,
    sizeClasses[props.size] ?? sizeClasses.sm,
])
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
