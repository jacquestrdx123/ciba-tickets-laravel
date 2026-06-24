import { ref, computed } from 'vue'
import axios from 'axios'

export function usePriorityTriage() {
    const records = ref([])
    const loading = ref(false)

    const priorityById = computed(
        () => new Map(records.value.map((r) => [r.ticket_id, r]))
    )

    async function load() {
        loading.value = true
        try {
            const res = await axios.get('/api/triage/priority')
            records.value = res.data?.records ?? []
        } finally {
            loading.value = false
        }
    }

    async function prioritize(ticketId, ticketNumber, note = null) {
        const res = await axios.post('/api/triage/priority', {
            ticket_id: ticketId,
            ticket_number: ticketNumber,
            note,
        })
        await load()
        return res.data
    }

    async function deprioritize(ticketId) {
        await axios.delete(`/api/triage/priority/${ticketId}`)
        await load()
    }

    return { records, loading, priorityById, load, prioritize, deprioritize }
}
