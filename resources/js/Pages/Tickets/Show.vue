<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '../../Components/AppLayout.vue'
import Badge from '../../Components/Ui/Badge.vue'
import Button from '../../Components/Ui/Button.vue'
import Icon from '../../Components/Ui/Icon.vue'
import GithubBranchesCell from '../../Components/GithubBranchesCell.vue'

const props = defineProps({ ticketId: [String, Number] })

const ticket = ref(null)
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
    try {
        const res = await axios.get(`/api/tickets/${props.ticketId}`)
        ticket.value = res.data
    } catch (e) {
        error.value = e?.response?.data?.message ?? e.message ?? 'Failed to load ticket'
    } finally {
        loading.value = false
    }
})

function initials(name) {
    const s = String(name ?? '').trim()
    if (!s) return '?'
    return s
        .split(/\s+/)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase()
}

function priorityBadgeColor(priority) {
    const p = String(priority ?? '').toLowerCase()
    if (p.includes('critical') || p.includes('urgent') || p.includes('p1')) return 'red'
    if (p.includes('high') || p.includes('p2')) return 'amber'
    if (p.includes('medium') || p.includes('normal') || p.includes('p3')) return 'yellow'
    if (p.includes('low') || p.includes('p4')) return 'green'
    return 'gray'
}

function statusBadgeColor(status) {
    const s = String(status ?? '').toLowerCase()
    if (s.includes('close') || s.includes('resolved') || s.includes('done')) return 'gray'
    if (s.includes('open') || s.includes('new')) return 'green'
    if (s.includes('pending') || s.includes('wait') || s.includes('hold')) return 'amber'
    if (s.includes('progress')) return 'blue'
    if (s.includes('escalat')) return 'red'
    return 'blue'
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <Button
                variant="ghost"
                color="gray"
                size="sm"
                icon="heroicons:arrow-left"
                @click="router.visit('/dashboard')"
            >
                Dashboard
            </Button>

            <div v-if="loading" class="flex flex-col items-center justify-center py-16 text-center">
                <Icon name="svg-spinners:ring-resize" class="h-8 w-8 text-gray-400" />
                <p class="mt-4 text-sm text-gray-500">Loading ticket…</p>
            </div>

            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-300"
            >
                {{ error }}
            </div>

            <template v-else-if="ticket">
                <section
                    class="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm shadow-gray-200/40 dark:border-gray-800 dark:bg-gray-900 dark:shadow-none sm:p-8"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-gray-950/[0.04] px-2 py-0.5 font-mono text-sm font-semibold text-primary-600 tabular-nums ring-1 ring-inset ring-gray-950/[0.06] dark:bg-white/[0.06] dark:text-primary-400 dark:ring-white/10">
                                    {{ ticket.ticket_number }}
                                </span>
                                <Badge v-if="ticket.closed_on_customer_side" color="gray" class="font-semibold">
                                    Closed on customer side
                                </Badge>
                                <Badge v-if="ticket.status" :color="statusBadgeColor(ticket.status)" class="capitalize">
                                    {{ ticket.status }}
                                </Badge>
                                <Badge v-if="ticket.priority" :color="priorityBadgeColor(ticket.priority)" class="capitalize">
                                    {{ ticket.priority }}
                                </Badge>
                            </div>
                            <h1 class="text-balance text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                                {{ ticket.subject }}
                            </h1>
                            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                <span v-if="ticket.client_name">
                                    Client:
                                    <span class="text-gray-800 dark:text-gray-200">{{ ticket.client_name }}</span>
                                </span>
                                <span v-if="ticket.closed_on_customer_side_at">
                                    Closed on customer side:
                                    {{ ticket.closed_on_customer_side_at }}
                                </span>
                                <span v-if="ticket.created_at">Created: {{ ticket.created_at }}</span>
                                <span v-if="ticket.updated_at">Updated: {{ ticket.updated_at }}</span>
                                <span v-if="ticket.last_comment_at">Last activity: {{ ticket.last_comment_at }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm shadow-gray-200/40 dark:border-gray-800 dark:bg-gray-900 dark:shadow-none"
                >
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        GitHub Branches
                    </h2>
                    <GithubBranchesCell :ticket="ticket" />
                </section>

                <section
                    v-if="ticket.description"
                    class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm shadow-gray-200/40 dark:border-gray-800 dark:bg-gray-900 dark:shadow-none"
                >
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        Description
                    </h2>
                    <div class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        {{ ticket.description }}
                    </div>
                </section>

                <section v-if="ticket.comments?.length" class="space-y-4">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        Comments ({{ ticket.comments.length }})
                    </h2>
                    <article
                        v-for="(comment, i) in ticket.comments"
                        :key="i"
                        class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-200/40 dark:border-gray-800 dark:bg-gray-900 dark:shadow-none"
                    >
                        <div class="mb-3 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-gray-200 to-gray-100 text-xs font-semibold text-gray-800 ring-2 ring-white dark:from-gray-700 dark:to-gray-800 dark:text-gray-100 dark:ring-gray-950">
                                    {{ initials(comment.author ?? comment.author_name) }}
                                </span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ comment.author ?? comment.author_name ?? 'Unknown' }}
                                </span>
                            </div>
                            <span class="shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                {{ comment.created_at }}
                            </span>
                        </div>
                        <div class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                            {{ comment.body ?? comment.content }}
                        </div>
                    </article>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
