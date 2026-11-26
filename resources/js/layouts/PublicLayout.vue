
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8"
            >
                <Link href="/" class="flex items-center gap-3">
                    <div
                        class="flex size-11 items-center justify-center rounded-xl bg-red-700 text-lg font-bold text-white"
                    >
                        G
                    </div>

                    <div>
                        <p class="text-lg font-bold tracking-tight text-slate-900">
                            GES-ALEMS
                        </p>
                        <p class="text-xs text-slate-500">
                            Enrollment Management System
                        </p>
                    </div>
                </Link>

                <Link
                    href="/"
                    class="text-sm font-medium text-slate-600 transition hover:text-red-700"
                >
                    Home
                </Link>
            </div>
        </header>

        <main
            class="mx-auto min-h-[calc(100vh-145px)] max-w-7xl bg-slate-50 px-4 py-8 text-slate-900 sm:px-6 lg:px-8"
        >
            <slot />
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-center text-xs text-slate-500 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8 lg:text-left"
            >
                <p>GES-ALEMS &mdash; Enrollment Management System</p>
                <p>&copy; {{ new Date().getFullYear() }}. All rights reserved.</p>
            </div>
        </footer>
    </div>
</template>
