<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '../Components/AppLayout.vue'
import Badge from '../Components/Ui/Badge.vue'
import Button from '../Components/Ui/Button.vue'
import Card from '../Components/Ui/Card.vue'
import Icon from '../Components/Ui/Icon.vue'
import { useAwaitingClientTriage } from '../composables/useAwaitingClientTriage'
import { useVendorTicketsFullSync } from '../composables/useVendorTicketsFullSync'
import { TEAM_LAST_COMMENT_DISPLAY_NAMES, isLastCommentFromTeam } from '../utils/teamLastComment'
import {
    withTriage,
    isTicketInAttentionQueue,
    isTicketAwaitingClient,
    shouldShowInParkedQueue,
    parkedRowFromRecord,
    hasNewCommentsSinceParked,
    matchesTicketSearch,
} from '../utils/ticketTriage'
import { downloadCsv, ticketsToCsv } from '../utils/csvExport'
import TicketAwaitingClientAction from '../Components/TicketAwaitingClientAction.vue'
import TicketCategoryBadge from '../Components/TicketCategoryBadge.vue'
import GithubBranchesCell from '../Components/GithubBranchesCell.vue'

const triage = useAwaitingClientTriage()
const sync = useVendorTicketsFullSync()

const loading = ref(true)
const allTickets = ref([])
const categories = ref([])
const search = ref('')
const categoryFilter = ref('all')
const showParked = ref(false)

async function loadTickets() {
    const res = await axios.get('/api/tickets')
    allTickets.value = res.data?.tickets ?? []
}

async function loadCategories() {
    const res = await axios.get('/api/ticket-categories')
    categories.value = res.data ?? []
}

async function load() {
    loading.value = true
    try {
        await Promise.all([loadTickets(), loadCategories(), triage.load()])
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

async function handleSync() {
    await sync.syncAll()
    await loadTickets()
}

const localById = computed(() => new Map(allTickets.value.map((t) => [t.vendor_id, t])))

const ticketsWithTriage = computed(() =>
    allTickets.value.map((t) => withTriage(t, triage.awaitingClientById.value, 'vendor_id'))
)

const attentionQueue = computed(() =>
    ticketsWithTriage.value.filter((t) => isTicketInAttentionQueue(t))
)

const parkedQueue = computed(() =>
    triage.records.value
        .filter((record) => shouldShowInParkedQueue(record, localById.value.get(record.ticket_id)))
        .map((record) => parkedRowFromRecord(record, localById.value.get(record.ticket_id)))
)

const parkedWithNewActivity = computed(() =>
    parkedQueue.value.filter((row) => hasNewCommentsSinceParked(row))
)

const activeQueue = computed(() => (showParked.value ? parkedQueue.value : attentionQueue.value))

function matchesCategoryFilter(ticket) {
    if (categoryFilter.value === 'all') return true
    if (categoryFilter.value === 'none') return !ticket.category
    return ticket.category?.id === Number(categoryFilter.value)
}

const filteredRows = computed(() => {
    let rows = activeQueue.value.filter(matchesCategoryFilter)
    const q = search.value.trim().toLowerCase()
    if (q) rows = rows.filter((t) => matchesTicketSearch(t, q))
    return rows
})

const categoryFilterOptions = computed(() => {
    const inQueue = activeQueue.value
    const counts = new Map()

    for (const ticket of inQueue) {
        const key = ticket.category?.id ?? 'none'
        counts.set(key, (counts.get(key) ?? 0) + 1)
    }

    const options = [
        { value: 'all', label: 'All categories', count: inQueue.length },
        { value: 'none', label: 'Uncategorized', count: counts.get('none') ?? 0 },
    ]

    for (const category of categories.value) {
        options.push({
            value: String(category.id),
            label: category.name,
            count: counts.get(category.id) ?? 0,
        })
    }

    return options
})

const stats = computed(() => {
    const total = allTickets.value.length
    const weLast = allTickets.value.filter((t) => isLastCommentFromTeam(t.last_comment_author)).length
    const unknown = allTickets.value.filter(
        (t) => t.last_comment_author == null || !String(t.last_comment_author).trim(),
    ).length
    const needs = attentionQueue.value.length
    const parked = parkedQueue.value.length
    const parkedNew = parkedWithNewActivity.value.length
    const pct = total > 0 ? Math.round((needs / total) * 100) : 0
    const withBranch = allTickets.value.filter((t) => t.github_branch_exists).length
    const githubBranchPct = total > 0 ? withBranch / total : 0
    return { total, weLast, unknown, needs, parked, parkedNew, pct, githubBranchPct }
})

const statCards = computed(() => [
    {
        key: 'total',
        label: 'Indexed tickets',
        value: stats.value.total,
        hint: 'In server cache',
        icon: 'heroicons:circle-stack',
        tint: 'from-slate-500/15 to-slate-600/5 ring-slate-500/10 text-slate-600 dark:text-slate-400',
        iconBg: 'bg-slate-500/10 text-slate-600 dark:bg-slate-400/15 dark:text-slate-300',
    },
    {
        key: 'weLast',
        label: 'We replied last',
        value: stats.value.weLast,
        hint: 'Your team owns the thread',
        icon: 'heroicons:chat-bubble-left-right',
        tint: 'from-emerald-500/15 to-emerald-600/5 ring-emerald-500/15 text-emerald-700 dark:text-emerald-300',
        iconBg: 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-300',
    },
    {
        key: 'unknown',
        label: 'Reply author unknown',
        value: stats.value.unknown,
        hint: 'Sync detail to resolve',
        icon: 'heroicons:question-mark-circle',
        tint: 'from-amber-500/15 to-orange-500/5 ring-amber-500/20 text-amber-800 dark:text-amber-200',
        iconBg: 'bg-amber-500/10 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300',
    },
    {
        key: 'needs',
        label: 'Needs follow-up',
        value: stats.value.needs,
        hint: `${stats.value.pct}% of cache · excludes parked`,
        icon: 'heroicons:bell-alert',
        tint: 'from-primary-500/20 to-violet-500/10 ring-primary-500/20 text-primary-800 dark:text-primary-200',
        iconBg: 'bg-primary-500/10 text-primary-600 dark:bg-primary-400/15 dark:text-primary-300',
    },
    {
        key: 'parked',
        label: 'Awaiting client',
        value: stats.value.parked,
        hint:
            stats.value.parkedNew > 0
                ? `${stats.value.parkedNew} with new activity`
                : 'Shared team list',
        icon: 'heroicons:clock',
        tint: 'from-sky-500/15 to-sky-600/5 ring-sky-500/15 text-sky-700 dark:text-sky-300',
        iconBg: 'bg-sky-500/10 text-sky-600 dark:bg-sky-400/15 dark:text-sky-300',
    },
    {
        key: 'github_branch_pct',
        label: 'Branches synced',
        value: `${Math.round(stats.value.githubBranchPct * 100)}%`,
        hint: 'Tickets with a GitHub branch',
        icon: 'heroicons:code-bracket-square',
        tint: 'from-indigo-500/15 to-indigo-600/5 ring-indigo-500/15 text-indigo-700 dark:text-indigo-300',
        iconBg: 'bg-indigo-500/10 text-indigo-600 dark:bg-indigo-400/15 dark:text-indigo-300',
    },
])

function exportQueueToCsv() {
    if (!filteredRows.value.length) return
    const stamp = new Date().toISOString().slice(0, 10)
    const prefix = showParked.value ? 'tickets-awaiting-client' : 'tickets-awaiting-response'
    downloadCsv(ticketsToCsv(filteredRows.value), `${prefix}-${stamp}.csv`)
}

function priorityBadgeColor(priority) {
    const p = String(priority ?? '').toLowerCase()
    if (p.includes('critical') || p.includes('urgent') || p.includes('p1')) return 'red'
    if (p.includes('high') || p.includes('p2')) return 'amber'
    if (p.includes('medium') || p.includes('normal') || p.includes('p3')) return 'yellow'
    if (p.includes('low') || p.includes('p4')) return 'green'
    return 'gray'
}

function authorInitials(author) {
    if (!author) return '?'
    return String(author)
        .split(/\s+/)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase()
}

function openTicket(vendorId) {
    router.visit(`/tickets/${vendorId}`)
}
</script>

<template>
    <AppLayout>
        <div class="space-y-10">
            <section
                class="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm shadow-gray-200/40 dark:border-gray-800 dark:bg-gray-900 dark:shadow-none sm:p-8"
            >
                <div
                    class="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-gradient-to-br from-primary-400/25 to-violet-500/20 blur-3xl dark:from-primary-500/20 dark:to-violet-600/25"
                    aria-hidden="true"
                />
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl space-y-3">
                        <div class="inline-flex items-center gap-2">
                            <Badge color="rose" size="xs" class="font-semibold uppercase tracking-wide">
                                Attention queue
                            </Badge>
                            <span class="text-xs font-medium text-gray-400 dark:text-gray-500">
                                SLA-style triage · parked list shared with team
                            </span>
                        </div>
                        <h1 class="text-balance text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            Tickets waiting on your team
                        </h1>
                        <p class="max-w-2xl text-pretty text-base leading-relaxed text-gray-600 dark:text-gray-400">
                            Surfaced when the latest public comment was
                            <strong class="font-semibold text-gray-800 dark:text-gray-200">not</strong>
                            from {{ TEAM_LAST_COMMENT_DISPLAY_NAMES.join(' or ') }}, or the last commenter is still unknown after a shallow sync.
                            Mark tickets as
                            <strong class="font-semibold text-gray-800 dark:text-gray-200">awaiting client</strong>
                            when you've replied and are waiting on them — even if their staff spoke last.
                            Refresh data with
                            <button
                                type="button"
                                class="font-semibold text-primary-600 underline decoration-primary-500/40 underline-offset-2 hover:text-primary-500 dark:text-primary-400"
                                @click="handleSync"
                            >
                                Sync
                            </button>.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-3">
                        <Button
                            color="gray"
                            variant="soft"
                            icon="heroicons:arrow-path-rounded-square"
                            size="lg"
                            :loading="sync.syncing.value"
                            @click="handleSync"
                        >
                            {{ sync.syncing.value ? 'Syncing…' : 'Sync tickets' }}
                        </Button>
                        <Button
                            color="gray"
                            variant="soft"
                            icon="heroicons:arrow-path"
                            size="lg"
                            :loading="loading"
                            @click="load"
                        >
                            Refresh queue
                        </Button>
                    </div>
                </div>
            </section>

            <div v-if="sync.error.value" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-300">
                {{ sync.error.value }}
            </div>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                <article
                    v-for="card in statCards"
                    :key="card.key"
                    class="group relative overflow-hidden rounded-xl border border-gray-200/90 bg-white p-5 shadow-sm ring-1 shadow-gray-200/30 ring-black/[0.02] transition-shadow hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:shadow-none dark:ring-white/[0.04]"
                >
                    <div
                        class="pointer-events-none absolute inset-0 opacity-80 transition-opacity group-hover:opacity-100"
                        :class="`bg-gradient-to-br ${card.tint}`"
                        aria-hidden="true"
                    />
                    <div class="relative flex items-start gap-4">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset ring-black/5 dark:ring-white/10"
                            :class="card.iconBg"
                        >
                            <Icon :name="card.icon" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ card.label }}
                            </div>
                            <div class="mt-1 text-3xl font-bold tabular-nums tracking-tight text-gray-950 dark:text-white">
                                {{ card.value }}
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ card.hint }}</div>
                        </div>
                    </div>
                </article>
            </section>

            <Card :padding="false">
                <template #header>
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                Queue
                            </div>
                            <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                {{ showParked ? 'Awaiting client' : 'Awaiting response' }}
                            </h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                {{ filteredRows.length }} ticket{{ filteredRows.length === 1 ? '' : 's' }} in view
                                <span v-if="search.trim() || categoryFilter !== 'all'">· filtered</span>
                                <span v-if="showParked">· shared with team</span>
                                <span v-if="showParked && stats.parkedNew > 0">
                                    · {{ stats.parkedNew }} with new comments since parked
                                </span>
                            </p>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center">
                            <Button
                                :color="showParked ? 'sky' : 'gray'"
                                :variant="showParked ? 'soft' : 'outline'"
                                :icon="showParked ? 'heroicons:queue-list' : 'heroicons:clock'"
                                size="md"
                                class="shrink-0"
                                @click="showParked = !showParked"
                            >
                                {{ showParked ? 'Back to attention queue' : `Show parked (${stats.parked}${stats.parkedNew > 0 ? ` · ${stats.parkedNew} new` : ''})` }}
                            </Button>
                            <div class="relative min-w-[11rem] shrink-0 sm:max-w-[14rem]">
                                <Icon
                                    name="heroicons:tag"
                                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                />
                                <select
                                    v-model="categoryFilter"
                                    class="w-full appearance-none rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-8 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                >
                                    <option
                                        v-for="option in categoryFilterOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }} ({{ option.count }})
                                    </option>
                                </select>
                                <Icon
                                    name="heroicons:chevron-down"
                                    class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                />
                            </div>
                            <div class="relative min-w-[12rem] flex-1">
                                <Icon
                                    name="heroicons:magnifying-glass"
                                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                />
                                <input
                                    v-model="search"
                                    type="search"
                                    placeholder="Search ticket #, subject, author…"
                                    class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                                />
                            </div>
                            <Button
                                color="gray"
                                variant="soft"
                                icon="heroicons:arrow-down-tray"
                                size="md"
                                class="shrink-0"
                                :disabled="!filteredRows.length || loading"
                                @click="exportQueueToCsv"
                            >
                                Export CSV
                            </Button>
                        </div>
                    </div>
                </template>

                <div class="p-4 sm:p-6">
                    <div v-if="loading" class="flex flex-col items-center justify-center py-16 text-center">
                        <Icon name="svg-spinners:ring-resize" class="h-8 w-8 text-gray-400" />
                        <p class="mt-4 text-sm text-gray-500">Loading tickets…</p>
                    </div>

                    <div v-else-if="!filteredRows.length" class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-800/80">
                            <Icon
                                :name="showParked ? 'heroicons:clock' : 'heroicons:check-circle'"
                                class="h-10 w-10"
                                :class="showParked ? 'text-sky-500' : 'text-emerald-500'"
                            />
                        </div>
                        <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">
                            <span v-if="!stats.total && !showParked">No tickets yet — click Sync to fetch.</span>
                            <span v-else-if="showParked && !parkedQueue.length">No tickets parked as awaiting client.</span>
                            <span v-else-if="!showParked && !attentionQueue.length">Queue is clear — your team has the latest reply everywhere we know.</span>
                            <span v-else-if="categoryFilter !== 'all' && !search.trim()">No tickets in this category.</span>
                            <span v-else>No tickets match your search.</span>
                        </p>
                        <p class="mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                            <span v-if="showParked">Park tickets from the attention queue when you've replied and are waiting on the client.</span>
                            <span v-else>Run a full sync to populate tickets, then return here for triage.</span>
                        </p>
                    </div>

                    <div v-else class="relative overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                        <table class="min-w-full border-separate border-spacing-0 text-sm">
                            <thead class="relative z-[1]">
                                <tr>
                                    <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ticket</th>
                                    <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Branch</th>
                                    <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Commits</th>
                                    <th class="min-w-[12rem] max-w-md px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Subject</th>
                                    <th class="min-w-[10rem] max-w-xs px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Category</th>
                                    <th class="w-[7.5rem] whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Priority</th>
                                    <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Updated</th>
                                    <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Last reply</th>
                                    <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Last activity</th>
                                    <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Triage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="ticket in filteredRows"
                                    :key="ticket.vendor_id"
                                    class="cursor-pointer transition-colors hover:bg-gray-50/90 dark:hover:bg-gray-900/60"
                                    @click="openTicket(ticket.vendor_id)"
                                >
                                    <td class="border-b border-gray-100 px-4 py-3.5 align-middle dark:border-gray-800/80">
                                        <div class="flex flex-col gap-1.5">
                                            <span class="group/link inline-flex items-center gap-2">
                                                <span class="rounded-md bg-gray-950/[0.04] px-2 py-0.5 font-mono text-sm font-semibold text-primary-600 tabular-nums ring-1 ring-inset ring-gray-950/[0.06] transition group-hover/link:bg-primary-500/10 group-hover/link:text-primary-700 dark:bg-white/[0.06] dark:text-primary-400 dark:ring-white/10 dark:group-hover/link:bg-primary-500/15">
                                                    {{ ticket.ticket_number }}
                                                </span>
                                                <Icon
                                                    name="heroicons:arrow-top-right-on-square"
                                                    class="h-4 w-4 text-gray-300 opacity-0 transition group-hover/link:opacity-100 dark:text-gray-600"
                                                />
                                            </span>
                                            <Badge
                                                v-if="showParked && hasNewCommentsSinceParked(ticket)"
                                                color="rose"
                                                size="xs"
                                                class="w-fit font-semibold"
                                            >
                                                New since parked
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="border-b border-gray-100 px-4 py-3.5 align-middle dark:border-gray-800/80" @click.stop>
                                        <GithubBranchesCell :ticket="ticket" compact />
                                    </td>
                                    <td class="border-b border-gray-100 px-4 py-3.5 align-middle tabular-nums text-gray-700 dark:border-gray-800/80 dark:text-gray-300">
                                        {{ ticket.github_commit_count != null ? ticket.github_commit_count : '—' }}
                                    </td>
                                    <td class="border-b border-gray-100 px-4 py-3.5 align-middle dark:border-gray-800/80">
                                        <span class="line-clamp-2 whitespace-normal font-medium text-gray-900 dark:text-gray-100">
                                            {{ ticket.subject || '—' }}
                                        </span>
                                    </td>
                                    <td class="border-b border-gray-100 px-4 py-3.5 align-middle dark:border-gray-800/80">
                                        <TicketCategoryBadge :category="ticket.category" />
                                    </td>
                                    <td class="border-b border-gray-100 px-4 py-3.5 align-middle dark:border-gray-800/80">
                                        <Badge
                                            v-if="ticket.priority"
                                            :color="priorityBadgeColor(ticket.priority)"
                                            class="capitalize tabular-nums"
                                        >
                                            {{ ticket.priority }}
                                        </Badge>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="border-b border-gray-100 px-4 py-3.5 align-middle tabular-nums text-gray-600 dark:border-gray-800/80 dark:text-gray-400">
                                        {{ ticket.updated_at ?? '—' }}
                                    </td>
                                    <td class="border-b border-gray-100 px-4 py-3.5 align-middle dark:border-gray-800/80">
                                        <div class="flex min-w-0 flex-col gap-1.5">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-semibold ring-2 ring-white dark:ring-gray-950"
                                                    :class="
                                                        !ticket.last_comment_author
                                                            ? 'from-amber-200 to-amber-100 text-amber-900 dark:from-amber-900 dark:to-amber-800 dark:text-amber-100'
                                                            : 'from-gray-200 to-gray-100 text-gray-800 dark:from-gray-700 dark:to-gray-800 dark:text-gray-100'
                                                    "
                                                >
                                                    {{ authorInitials(ticket.last_comment_author) }}
                                                </span>
                                                <span
                                                    v-if="!ticket.last_comment_author"
                                                    class="font-medium text-amber-700 dark:text-amber-400"
                                                >
                                                    Unknown
                                                </span>
                                                <span v-else class="max-w-[10rem] truncate text-gray-800 dark:text-gray-200">
                                                    {{ ticket.last_comment_author }}
                                                </span>
                                            </div>
                                            <Badge
                                                v-if="isTicketAwaitingClient(ticket)"
                                                color="sky"
                                                size="xs"
                                                class="w-fit font-semibold"
                                            >
                                                Awaiting client
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="border-b border-gray-100 px-4 py-3.5 align-middle dark:border-gray-800/80">
                                        <div class="flex flex-col gap-1">
                                            <span class="tabular-nums text-gray-600 dark:text-gray-400">
                                                {{ ticket.last_comment_at ?? '—' }}
                                            </span>
                                            <span
                                                v-if="showParked && !ticket.last_comment_at"
                                                class="text-xs text-gray-400 dark:text-gray-500"
                                            >
                                                Sync to detect activity
                                            </span>
                                        </div>
                                    </td>
                                    <td class="border-b border-gray-100 px-4 py-3.5 align-middle dark:border-gray-800/80" @click.stop>
                                        <TicketAwaitingClientAction
                                            :ticket="ticket"
                                            :is-parked="!!ticket._triage?.isAwaitingClient"
                                            @changed="load"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p
                        v-if="!loading && filteredRows.length"
                        class="mt-6 border-t border-gray-100 pt-4 text-center text-xs text-gray-400 dark:border-gray-800 dark:text-gray-500"
                    >
                        <span v-if="showParked">
                            Parked tickets are shared with the team ·
                            <span v-if="stats.parkedNew > 0">rose badge = new comments since you parked · </span>
                            run sync to refresh last activity timestamps
                        </span>
                        <span v-else>
                            Showing tickets where the vendor's customer or an unknown actor spoke last · park false positives as awaiting client
                        </span>
                    </p>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
