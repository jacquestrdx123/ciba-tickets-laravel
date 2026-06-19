import { ref } from 'vue'
import axios from 'axios'

const TERMINAL_STATUSES = new Set(['completed', 'failed', 'idle'])

async function pollSyncStatus(maxAttempts = 120, intervalMs = 2000) {
    for (let i = 0; i < maxAttempts; i++) {
        const res = await axios.get('/api/tickets/sync/status')
        const { status, synced_at: syncedAt, error: syncError } = res.data ?? {}

        if (status === 'completed') {
            return res.data
        }

        if (status === 'failed') {
            throw new Error(syncError ?? 'Sync failed')
        }

        if (TERMINAL_STATUSES.has(status) && status !== 'queued' && status !== 'running') {
            break
        }

        await new Promise((resolve) => setTimeout(resolve, intervalMs))
    }

    throw new Error('Sync timed out waiting for the job to finish')
}

export function useVendorTicketsFullSync() {
    const syncing = ref(false)
    const error = ref(null)
    const lastSyncAt = ref(null)

    async function syncAll() {
        syncing.value = true
        error.value = null
        try {
            const res = await axios.post('/api/tickets/sync')

            if (res.status === 202 || res.data?.queued || res.status === 409) {
                const result = await pollSyncStatus()
                lastSyncAt.value = new Date(result?.synced_at ?? Date.now())
                return result
            }

            lastSyncAt.value = new Date(res.data?.synced_at ?? Date.now())
            return res.data
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Sync failed'
        } finally {
            syncing.value = false
        }
    }

    return { syncing, error, lastSyncAt, syncAll }
}
