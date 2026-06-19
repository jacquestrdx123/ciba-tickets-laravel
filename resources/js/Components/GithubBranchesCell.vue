<script setup>
import { computed } from 'vue'
import Badge from './Ui/Badge.vue'
import { githubBranchListForDisplay, githubBranchBadgeColor, githubBranchBadgeTitle } from '../utils/githubBranch'

const props = defineProps({
    ticket: { type: Object, default: null },
    branches: { type: Array, default: null },
    compact: { type: Boolean, default: false },
})

const branchList = computed(() => {
    if (props.branches?.length) {
        return props.branches.map((b) => ({
            name: b.name,
            is_default: b.is_default ?? false,
            merged: true,
            openPr: false,
        }))
    }
    return props.ticket ? githubBranchListForDisplay(props.ticket) : []
})

const primary = computed(() => props.ticket?.github_branch_name ?? branchList.value[0]?.name ?? null)
const hasSynced = computed(() => props.ticket?.synced_at != null || branchList.value.length > 0)

const badgeColors = {
    emerald: 'emerald',
    red: 'red',
    amber: 'amber',
    gray: 'gray',
}
</script>

<template>
    <span
        v-if="ticket?.github_error"
        class="text-amber-600 dark:text-amber-400"
        :title="ticket.github_error"
    >
        Error
    </span>
    <ul
        v-else-if="branchList.length"
        class="list-none space-y-0.5 p-0"
        :class="compact ? 'max-w-[14rem]' : ''"
    >
        <li v-for="branch in branchList" :key="branch.name">
            <Badge
                :color="badgeColors[githubBranchBadgeColor(branch, primary)] ?? 'gray'"
                size="xs"
                class="max-w-full truncate font-mono"
                :title="githubBranchBadgeTitle(branch, primary)"
            >
                {{ branch.name }}
            </Badge>
        </li>
    </ul>
    <span v-else-if="hasSynced" class="text-xs text-gray-500 dark:text-gray-400">
        No branch
    </span>
    <span v-else class="text-xs text-gray-400">—</span>
</template>
