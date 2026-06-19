<script setup>
import { useForm } from '@inertiajs/vue3'
import Icon from '../Components/Ui/Icon.vue'
import Button from '../Components/Ui/Button.vue'

const form = useForm({ password: '' })

function submit() {
    form.post('/login')
}
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-x-hidden bg-slate-50 px-4 dark:bg-gray-950">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-[420px] bg-gradient-to-b from-primary-500/[0.07] via-violet-500/[0.04] to-transparent dark:from-primary-400/[0.08] dark:via-violet-500/[0.05]"
            aria-hidden="true"
        />
        <div
            class="relative w-full max-w-sm rounded-2xl border border-gray-200/80 bg-white/90 p-8 shadow-xl shadow-gray-900/5 backdrop-blur-sm dark:border-gray-800/80 dark:bg-gray-900/90"
        >
            <div class="mb-6 flex items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary-500 to-violet-600 text-white shadow-md shadow-primary-500/25"
                    aria-hidden="true"
                >
                    <Icon name="heroicons:ticket" class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Sign in
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Vendor tickets workspace
                    </p>
                </div>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Password
                    </label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        placeholder="Enter password"
                        :disabled="form.processing"
                        required
                        autofocus
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
                        :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': form.errors.password }"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                        {{ form.errors.password }}
                    </p>
                </div>

                <Button
                    type="submit"
                    block
                    :loading="form.processing"
                    :disabled="!form.password.trim()"
                >
                    Sign in
                </Button>
            </form>
        </div>
    </div>
</template>
