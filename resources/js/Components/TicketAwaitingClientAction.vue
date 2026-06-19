<script setup>
import { ref } from 'vue'
import Button from './Ui/Button.vue'
import { useAwaitingClientTriage } from '../composables/useAwaitingClientTriage'

const props = defineProps({
    ticket: { type: Object, required: true },
    isParked: { type: Boolean, default: false },
    compact: { type: Boolean, default: true },
})

const emit = defineEmits(['changed'])

const triage = useAwaitingClientTriage()
const busy = ref(false)

async function toggleParked() {
    busy.value = true
    try {
        if (props.isParked) {
            await triage.unpark(props.ticket.vendor_id)
        } else {
            await triage.park(
                props.ticket.vendor_id,
                props.ticket.ticket_number,
                null,
                props.ticket.last_comment_at ?? null,
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
        :color="isParked ? 'amber' : 'gray'"
        :variant="compact ? 'ghost' : 'soft'"
        :size="compact ? 'xs' : 'md'"
        :icon="isParked ? 'heroicons:arrow-uturn-left' : 'heroicons:clock'"
        :loading="busy"
        @click.stop="toggleParked"
    >
        {{ isParked ? 'Unpark' : 'Awaiting client' }}
    </Button>
</template>
