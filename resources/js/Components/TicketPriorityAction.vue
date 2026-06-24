<script setup>
import { ref } from 'vue'
import Button from './Ui/Button.vue'
import { usePriorityTriage } from '../composables/usePriorityTriage'

const props = defineProps({
    ticket: { type: Object, required: true },
    isPriority: { type: Boolean, default: false },
    compact: { type: Boolean, default: true },
})

const emit = defineEmits(['changed'])

const triage = usePriorityTriage()
const busy = ref(false)

async function togglePriority() {
    busy.value = true
    try {
        if (props.isPriority) {
            await triage.deprioritize(props.ticket.vendor_id)
        } else {
            await triage.prioritize(
                props.ticket.vendor_id,
                props.ticket.ticket_number,
            )
        }
        emit('changed')
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <Button
        :color="isPriority ? 'gray' : 'rose'"
        :variant="compact ? 'outline' : 'soft'"
        :size="compact ? 'xs' : 'md'"
        :icon="isPriority ? 'heroicons:star-solid' : 'heroicons:star'"
        :loading="busy"
        @click.stop="togglePriority"
    >
        {{ isPriority ? 'Deprioritize' : 'Prioritize' }}
    </Button>
</template>
