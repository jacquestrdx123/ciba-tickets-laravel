import { ref } from 'vue'
import axios from 'axios'

export function useGithubBranchSync() {
    const syncing = ref(false)
    const error = ref(null)

    async function syncAll() {
        syncing.value = true
        error.value = null
        try {
            const res = await axios.post('/api/github/branches/sync')
            return res.data
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'GitHub branch sync failed'
        } finally {
            syncing.value = false
        }
    }

    return { syncing, error, syncAll }
}
