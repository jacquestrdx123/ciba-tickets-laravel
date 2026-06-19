<script setup>
import { usePage } from '@inertiajs/vue3'
import Icon from './Ui/Icon.vue'
import Button from './Ui/Button.vue'

const page = usePage()

function navClass(href) {
    const path = page.url.split('?')[0]
    const active = href === '/dashboard'
        ? path === '/dashboard' || path.startsWith('/tickets/')
        : path === href
    return active
        ? 'bg-gray-900 text-white shadow-sm ring-1 ring-gray-900/10 dark:bg-white dark:text-gray-900 dark:ring-white/20'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white'
}
</script>

<template>
    <div class="relative min-h-screen overflow-x-hidden bg-slate-50 dark:bg-gray-950">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-[420px] bg-gradient-to-b from-primary-500/[0.07] via-violet-500/[0.04] to-transparent dark:from-primary-400/[0.08] dark:via-violet-500/[0.05]"
            aria-hidden="true"
        />
        <header
            class="sticky top-0 z-40 border-b border-gray-200/80 bg-white/75 backdrop-blur-md dark:border-gray-800/80 dark:bg-gray-950/75"
        >
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary-500 to-violet-600 text-white shadow-md shadow-primary-500/25"
                        aria-hidden="true"
                    >
                        <Icon name="heroicons:ticket" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 leading-tight">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                            Vendor tickets
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Server workspace · MySQL
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <nav class="flex flex-wrap items-center gap-1.5 rounded-xl bg-gray-100/90 p-1 dark:bg-gray-900/90">
                        <a
                            href="/dashboard"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="navClass('/dashboard')"
                        >
                            <Icon name="heroicons:queue-list" class="h-4 w-4 opacity-80" />
                            Dashboard
                        </a>
                    </nav>
                    <form method="POST" action="/logout">
                        <input type="hidden" name="_token" :value="page.props.csrf_token" />
                        <Button type="submit" variant="ghost" color="gray" size="sm">
                            Sign out
                        </Button>
                    </form>
                </div>
            </div>
        </header>

        <main class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
