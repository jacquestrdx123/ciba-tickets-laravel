import { ref, computed } from 'vue'
import axios from 'axios'

export function useAwaitingClientTriage() {
    const records = ref([])
    const loading = ref(false)

    const awaitingClientById = computed(
        () => new Map(records.value.map((r) => [r.ticket_id, r]))
    )

    async function load() {
        loading.value = true
        try {
            const res = await axios.get('/api/triage/awaiting-client')
            records.value = res.data?.records ?? []
        } finally {
            loading.value = false
        }
    }

    async function park(ticketId, ticketNumber, note = null, lastCommentAt = null) {
        const res = await axios.post('/api/triage/awaiting-client', {
            ticket_id: ticketId,
            ticket_number: ticketNumber,
            note,
            last_comment_at: lastCommentAt,
        })
        await load()
        return res.data
    }

    async function unpark(ticketId) {
        await axios.delete(`/api/triage/awaiting-client/${ticketId}`)
        await load()
    }

    return { records, loading, awaitingClientById, load, park, unpark }
}
