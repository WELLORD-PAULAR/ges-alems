<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
</script>

<template>
    <Head title="Enrollment Submitted" />

    <PublicLayout>
        <main class="mx-auto flex min-h-[70vh] max-w-3xl items-center justify-center px-4 py-12">
            <section class="w-full rounded-xl border border-slate-200 bg-slate-100 p-8 text-center shadow-sm sm:p-12">
                <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl font-bold text-green-700">
                    ?
                </div>

                <h1 class="mb-4 text-2xl font-bold text-red-700 sm:text-3xl">
                    Enrollment Submitted Successfully!
                </h1>

                <p class="mb-4 text-slate-900">
                    Thank you for submitting your enrollment application.
                    Your information has been received and is now awaiting
                    verification by the school.
                </p>

                <p class="mb-8 text-sm text-slate-700">
                    Please keep your enrollment information available in case
                    the school needs to verify any details.
                </p>

                <Link
                    href="/"
                    class="inline-flex items-center justify-center rounded-md bg-red-700 px-6 py-3 font-semibold text-white transition hover:bg-red-800"
                >
                    Return to Home
                </Link>
            </section>
        </main>
    </PublicLayout>
</template>
