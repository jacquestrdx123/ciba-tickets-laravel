<script setup>
import { Icon } from '@iconify/vue'

defineProps({
    name: { type: String, required: true },
})
</script>

<template>
    <Icon :icon="name" class="inline-block shrink-0" aria-hidden="true" />
</template>
