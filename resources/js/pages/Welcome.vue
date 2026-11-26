
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({
    layout: PublicLayout,
});
</script>

<template>
    <Head title="Home" />

    <section class="flex min-h-[65vh] items-center">
        <div class="grid w-full items-center gap-12 lg:grid-cols-2">
            <div class="space-y-6">
                <span
                    class="inline-flex rounded-full bg-primary/10 px-4 py-2 text-sm font-medium text-primary"
                >
                    GES-ALEMS
                </span>

                <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">
                    Welcome to the
                    <span class="text-primary">Enrollment Management System</span>
                </h1>

                <p class="max-w-xl text-lg leading-relaxed text-muted-foreground">
                    A convenient way to submit student enrollment information
                    and manage school enrollment records.
                </p>

                <div class="flex flex-wrap gap-3">
                    <Link
                        href="/enroll"
                        class="inline-flex items-center rounded-md bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        Enroll a Student
                    </Link>

                    <Link
                        href="/login"
                        class="inline-flex items-center rounded-md border border-black bg-black px-6 py-3 text-sm font-semibold text-white transition hover:bg-gray-800 hover:text-white"
                    >
                        Staff Login
                    </Link>
                </div>
            </div>

            <div
                class="rounded-2xl border border-border bg-white p-8 shadow-sm"
            >
                <h2 class="mb-6 text-xl font-semibold">
                    What can you do here?
                </h2>

                <div class="space-y-6">
                    <div class="flex gap-4">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 font-bold text-primary"
                        >
                            01
                        </div>
                        <div>
                            <h3 class="font-semibold">Student Enrollment</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Submit student information through the online
                                enrollment form.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 font-bold text-primary"
                        >
                            02
                        </div>
                        <div>
                            <h3 class="font-semibold">Enrollment Processing</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Submitted enrollment records can be reviewed
                                and processed by authorized staff.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 font-bold text-primary"
                        >
                            03
                        </div>
                        <div>
                            <h3 class="font-semibold">School Management</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Authorized users can access the system's
                                school management features.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
