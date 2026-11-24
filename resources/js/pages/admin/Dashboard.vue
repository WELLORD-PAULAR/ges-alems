<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">
                Admin Dashboard
            </h1>

            <p class="text-muted-foreground">
                Welcome to the GEMS-AMES Admin Dashboard.
            </p>
        </div>

        <div class="rounded-lg border p-6">
            <h2 class="text-lg font-semibold">
                Administration
            </h2>

            <p class="mt-2 text-sm text-muted-foreground">
                Admin-only dashboard. Modules will be added in later
                slices.
            </p>
        </div>
    </div>
</template>