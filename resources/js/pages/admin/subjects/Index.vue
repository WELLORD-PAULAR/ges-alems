<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import SubjectController from '@/actions/App/Http/Controllers/Admin/SubjectController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/subjects';

type SubjectRow = {
    id: number;
    code: string;
    name: string;
    offered_term: number | null;
    term_label: string;
    assignments_count: number;
};

type TermOption = {
    value: number;
    label: string;
};

defineProps<{
    subjects: SubjectRow[];
    terms: TermOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Subjects',
                href: index(),
            },
        ],
    },
});

const editing = ref<SubjectRow | null>(null);

function remove(subject: SubjectRow): void {
    if (!window.confirm(`Delete ${subject.name} (${subject.code})?`)) {
        return;
    }

    router.delete(SubjectController.destroy.url(subject.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Subjects" />

    <div class="grid gap-6 p-6 lg:grid-cols-[22rem_1fr]">
        <section class="space-y-4">
            <Heading
                variant="small"
                :title="editing ? 'Edit subject' : 'Add subject'"
                :description="
                    editing
                        ? `Editing ${editing.name}`
                        : 'Codes must be unique. Leave the term blank to offer the subject every term.'
                "
            />

            <Form
                :key="editing ? `edit-${editing.id}` : 'create'"
                v-bind="
                    editing
                        ? SubjectController.update.form(editing.id)
                        : SubjectController.store.form()
                "
                reset-on-success
                :options="{ preserveScroll: true }"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="editing = null"
            >
                <div class="grid gap-2">
                    <Label for="code">Subject code</Label>
                    <Input
                        id="code"
                        name="code"
                        :default-value="editing?.code ?? ''"
                        required
                        maxlength="30"
                        placeholder="e.g. ENG"
                    />
                    <InputError :message="errors.code" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Subject name</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="editing?.name ?? ''"
                        required
                        maxlength="100"
                        placeholder="e.g. English"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="offered_term">Offered term</Label>
                    <select
                        id="offered_term"
                        name="offered_term"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    >
                        <option value="">All terms (year-round)</option>
                        <option
                            v-for="term in terms"
                            :key="term.value"
                            :value="term.value"
                            :selected="editing?.offered_term === term.value"
                        >
                            {{ term.label }}
                        </option>
                    </select>
                    <InputError :message="errors.offered_term" />
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="save-subject-button"
                    >
                        {{ editing ? 'Update subject' : 'Add subject' }}
                    </Button>
                    <Button
                        v-if="editing"
                        type="button"
                        variant="outline"
                        @click="editing = null"
                    >
                        Cancel
                    </Button>
                </div>
            </Form>
        </section>

        <section class="min-w-0 space-y-4">
            <Heading
                variant="small"
                title="Subjects"
                description="Academic subjects and the term each is offered in."
            />

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Code</th>
                            <th class="px-4 py-2 font-medium">Name</th>
                            <th class="px-4 py-2 font-medium">Term</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="subjects.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                No subjects yet. Add the first one.
                            </td>
                        </tr>
                        <tr
                            v-for="subject in subjects"
                            :key="subject.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2 font-mono">
                                {{ subject.code }}
                            </td>
                            <td class="px-4 py-2">{{ subject.name }}</td>
                            <td class="px-4 py-2">
                                <Badge
                                    :variant="
                                        subject.offered_term === null
                                            ? 'outline'
                                            : 'secondary'
                                    "
                                >
                                    {{ subject.term_label }}
                                </Badge>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="editing = subject"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        :disabled="subject.assignments_count > 0"
                                        :title="
                                            subject.assignments_count > 0
                                                ? 'Assigned to a teacher, so it cannot be deleted'
                                                : undefined
                                        "
                                        @click="remove(subject)"
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
