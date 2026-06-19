<script setup>
defineProps({
    padding: { type: Boolean, default: true },
})
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm shadow-gray-200/40 dark:border-gray-800 dark:bg-gray-900 dark:shadow-none">
        <div v-if="$slots.header" class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <slot name="header" />
        </div>
        <div :class="padding ? 'p-4 sm:p-6' : ''">
            <slot />
        </div>
    </div>
</template>
