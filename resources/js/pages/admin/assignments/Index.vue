<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AssignmentController from '@/actions/App/Http/Controllers/Admin/AssignmentController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/assignments';

type AssignmentRow = {
    id: number;
    teacher_id: number;
    subject_id: number;
    section_id: number;
    teacher_name: string;
    subject_code: string;
    subject_name: string;
    section_name: string;
    grade_label: string;
    school_year: string;
    term: number;
    term_label: string;
};

type Filters = {
    school_year: string;
    term: number | null;
    teacher_id: number | null;
    section_id: number | null;
    subject_id: number | null;
};

type TermOption = { value: number; label: string };
type TeacherOption = { id: number; full_name: string };
type SubjectOption = {
    id: number;
    code: string;
    name: string;
    offered_term: number | null;
};
type SectionOption = {
    id: number;
    name: string;
    grade_level: string;
    grade_label: string;
    school_year: string;
};

const props = defineProps<{
    assignments: AssignmentRow[];
    filters: Filters;
    schoolYears: string[];
    terms: TermOption[];
    teachers: TeacherOption[];
    subjects: SubjectOption[];
    sections: SectionOption[];
    teacherTotal: number | null;
    sectionTotal: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Assignments',
                href: index(),
            },
        ],
    },
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const editing = ref<AssignmentRow | null>(null);
const formKey = ref(0);
// The form's year and term decide which sections and subjects it offers.
const formYear = ref(props.filters.school_year);
const formTerm = ref('');

const formSections = computed(() =>
    props.sections.filter((section) => section.school_year === formYear.value),
);

const formSubjects = computed(() =>
    props.subjects.filter(
        (subject) =>
            formTerm.value === '' ||
            subject.offered_term === null ||
            String(subject.offered_term) === formTerm.value,
    ),
);

const filterSections = computed(() =>
    props.sections.filter(
        (section) => section.school_year === props.filters.school_year,
    ),
);

function startEdit(row: AssignmentRow): void {
    editing.value = row;
    formYear.value = row.school_year;
    formTerm.value = String(row.term);
}

function resetForm(): void {
    editing.value = null;
    formYear.value = props.filters.school_year;
    formTerm.value = '';
    formKey.value++;
}

function applyFilters(
    changes: Partial<Record<keyof Filters, string>>,
): void {
    const merged: Record<string, string> = {
        school_year: props.filters.school_year,
    };

    for (const key of ['term', 'teacher_id', 'section_id', 'subject_id'] as const) {
        const current = props.filters[key];

        if (current !== null) {
            merged[key] = String(current);
        }
    }

    // Sections belong to one school year, so a new year clears that filter.
    if (changes.school_year !== undefined) {
        delete merged.section_id;
    }

    for (const [key, value] of Object.entries(changes)) {
        if (value === undefined || value === '') {
            delete merged[key];
        } else {
            merged[key] = value;
        }
    }

    router.get(AssignmentController.index.url(), merged, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onFilter(key: keyof Filters, event: Event): void {
    applyFilters({ [key]: (event.target as HTMLSelectElement).value });
}

function clearFilters(): void {
    router.get(
        AssignmentController.index.url(),
        { school_year: props.filters.school_year },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function remove(row: AssignmentRow): void {
    if (
        !window.confirm(
            `Delete ${row.teacher_name}'s ${row.subject_name} assignment for ${row.section_name}?`,
        )
    ) {
        return;
    }

    router.delete(AssignmentController.destroy.url(row.id), {
        preserveScroll: true,
    });
}

function removeForTeacher(): void {
    const teacher = props.teachers.find(
        (option) => option.id === props.filters.teacher_id,
    );

    if (!teacher || props.filters.teacher_id === null) {
        return;
    }

    if (
        !window.confirm(
            `Delete all ${props.teacherTotal ?? 0} assignment(s) of ${teacher.full_name} in ${props.filters.school_year}? Other school years are not affected.`,
        )
    ) {
        return;
    }

    router.delete(
        AssignmentController.destroyForTeacher.url(props.filters.teacher_id, {
            query: { school_year: props.filters.school_year },
        }),
        { preserveScroll: true },
    );
}

function removeForSection(): void {
    const section = props.sections.find(
        (option) => option.id === props.filters.section_id,
    );

    if (!section || props.filters.section_id === null) {
        return;
    }

    if (
        !window.confirm(
            `Delete all ${props.sectionTotal ?? 0} assignment(s) of ${section.name} (${section.school_year})?`,
        )
    ) {
        return;
    }

    router.delete(
        AssignmentController.destroyForSection.url(props.filters.section_id),
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Assignments" />

    <div class="grid gap-6 p-6 lg:grid-cols-[24rem_1fr]">
        <section class="space-y-4">
            <Heading
                variant="small"
                :title="editing ? 'Edit assignment' : 'Add assignment'"
                :description="
                    editing
                        ? 'Changing an assignment replaces all of its details.'
                        : 'A teacher teaches one subject to one section in one term.'
                "
            />

            <Form
                :key="`${editing ? `edit-${editing.id}` : 'create'}-${formKey}`"
                v-bind="
                    editing
                        ? AssignmentController.update.form(editing.id)
                        : AssignmentController.store.form()
                "
                :options="{ preserveScroll: true }"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="resetForm"
            >
                <InputError :message="errors.assignment" />

                <div class="grid gap-2">
                    <Label for="teacher_id">Teacher</Label>
                    <select
                        id="teacher_id"
                        name="teacher_id"
                        required
                        :class="selectClass"
                    >
                        <option value="">Select a teacher</option>
                        <option
                            v-for="teacher in teachers"
                            :key="teacher.id"
                            :value="teacher.id"
                            :selected="editing?.teacher_id === teacher.id"
                        >
                            {{ teacher.full_name }}
                        </option>
                    </select>
                    <InputError :message="errors.teacher_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="school_year">School year</Label>
                    <select
                        id="school_year"
                        name="school_year"
                        required
                        :class="selectClass"
                        @change="
                            formYear = ($event.target as HTMLSelectElement)
                                .value
                        "
                    >
                        <option
                            v-for="year in schoolYears"
                            :key="year"
                            :value="year"
                            :selected="year === formYear"
                        >
                            {{ year }}
                        </option>
                    </select>
                    <InputError :message="errors.school_year" />
                </div>

                <div class="grid gap-2">
                    <Label for="term">Term</Label>
                    <select
                        id="term"
                        name="term"
                        required
                        :class="selectClass"
                        @change="
                            formTerm = ($event.target as HTMLSelectElement)
                                .value
                        "
                    >
                        <option value="" :selected="formTerm === ''">
                            Select a term
                        </option>
                        <option
                            v-for="term in terms"
                            :key="term.value"
                            :value="term.value"
                            :selected="String(term.value) === formTerm"
                        >
                            {{ term.label }}
                        </option>
                    </select>
                    <InputError :message="errors.term" />
                </div>

                <div class="grid gap-2">
                    <Label for="section_id">Section</Label>
                    <select
                        id="section_id"
                        name="section_id"
                        required
                        :class="selectClass"
                    >
                        <option value="">Select a section</option>
                        <option
                            v-for="section in formSections"
                            :key="section.id"
                            :value="section.id"
                            :selected="editing?.section_id === section.id"
                        >
                            {{ section.name }} ({{ section.grade_label }})
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        Only sections of {{ formYear }} are listed.
                    </p>
                    <InputError :message="errors.section_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="subject_id">Subject</Label>
                    <select
                        id="subject_id"
                        name="subject_id"
                        required
                        :class="selectClass"
                    >
                        <option value="">Select a subject</option>
                        <option
                            v-for="subject in formSubjects"
                            :key="subject.id"
                            :value="subject.id"
                            :selected="editing?.subject_id === subject.id"
                        >
                            {{ subject.code }} - {{ subject.name }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        Pick a term first to see only the subjects offered in
                        it.
                    </p>
                    <InputError :message="errors.subject_id" />
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="save-assignment-button"
                    >
                        {{ editing ? 'Update assignment' : 'Add assignment' }}
                    </Button>
                    <Button
                        v-if="editing"
                        type="button"
                        variant="outline"
                        @click="resetForm"
                    >
                        Cancel
                    </Button>
                </div>
            </Form>
        </section>

        <section class="min-w-0 space-y-4">
            <Heading
                variant="small"
                :title="`Assignments for ${filters.school_year}`"
                description="Narrow the list, or pick a teacher or section to delete all of theirs at once."
            />

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div class="grid gap-1">
                    <Label for="f_year">School year</Label>
                    <select
                        id="f_year"
                        :value="filters.school_year"
                        :class="selectClass"
                        @change="onFilter('school_year', $event)"
                    >
                        <option
                            v-for="year in schoolYears"
                            :key="year"
                            :value="year"
                        >
                            {{ year }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-1">
                    <Label for="f_term">Term</Label>
                    <select
                        id="f_term"
                        :value="filters.term === null ? '' : String(filters.term)"
                        :class="selectClass"
                        @change="onFilter('term', $event)"
                    >
                        <option value="">All terms</option>
                        <option
                            v-for="term in terms"
                            :key="term.value"
                            :value="String(term.value)"
                        >
                            {{ term.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-1">
                    <Label for="f_teacher">Teacher</Label>
                    <select
                        id="f_teacher"
                        :value="
                            filters.teacher_id === null
                                ? ''
                                : String(filters.teacher_id)
                        "
                        :class="selectClass"
                        @change="onFilter('teacher_id', $event)"
                    >
                        <option value="">All teachers</option>
                        <option
                            v-for="teacher in teachers"
                            :key="teacher.id"
                            :value="String(teacher.id)"
                        >
                            {{ teacher.full_name }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-1">
                    <Label for="f_section">Section</Label>
                    <select
                        id="f_section"
                        :value="
                            filters.section_id === null
                                ? ''
                                : String(filters.section_id)
                        "
                        :class="selectClass"
                        @change="onFilter('section_id', $event)"
                    >
                        <option value="">All sections</option>
                        <option
                            v-for="section in filterSections"
                            :key="section.id"
                            :value="String(section.id)"
                        >
                            {{ section.name }} ({{ section.grade_label }})
                        </option>
                    </select>
                </div>

                <div class="grid gap-1">
                    <Label for="f_subject">Subject</Label>
                    <select
                        id="f_subject"
                        :value="
                            filters.subject_id === null
                                ? ''
                                : String(filters.subject_id)
                        "
                        :class="selectClass"
                        @change="onFilter('subject_id', $event)"
                    >
                        <option value="">All subjects</option>
                        <option
                            v-for="subject in subjects"
                            :key="subject.id"
                            :value="String(subject.id)"
                        >
                            {{ subject.code }} - {{ subject.name }}
                        </option>
                    </select>
                </div>

                <div class="flex items-end">
                    <Button type="button" variant="outline" @click="clearFilters">
                        Reset filters
                    </Button>
                </div>
            </div>

            <div
                v-if="teacherTotal !== null || sectionTotal !== null"
                class="flex flex-wrap items-center gap-3 rounded-lg border border-dashed p-3 text-sm"
            >
                <Button
                    v-if="teacherTotal !== null"
                    type="button"
                    size="sm"
                    variant="destructive"
                    :disabled="teacherTotal === 0"
                    @click="removeForTeacher"
                >
                    Delete this teacher's {{ teacherTotal }} assignment(s) in
                    {{ filters.school_year }}
                </Button>
                <Button
                    v-if="sectionTotal !== null"
                    type="button"
                    size="sm"
                    variant="destructive"
                    :disabled="sectionTotal === 0"
                    @click="removeForSection"
                >
                    Delete this section's {{ sectionTotal }} assignment(s)
                </Button>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Term</th>
                            <th class="px-4 py-2 font-medium">Section</th>
                            <th class="px-4 py-2 font-medium">Subject</th>
                            <th class="px-4 py-2 font-medium">Teacher</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="assignments.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                No assignments match these filters.
                            </td>
                        </tr>
                        <tr
                            v-for="row in assignments"
                            :key="row.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2">
                                <Badge variant="secondary">
                                    {{ row.term_label }}
                                </Badge>
                            </td>
                            <td class="px-4 py-2">
                                {{ row.section_name }}
                                <span class="text-muted-foreground">
                                    ({{ row.grade_label }})
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <span class="font-mono">{{
                                    row.subject_code
                                }}</span>
                                {{ row.subject_name }}
                            </td>
                            <td class="px-4 py-2">{{ row.teacher_name }}</td>
                            <td class="px-4 py-2">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="startEdit(row)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        @click="remove(row)"
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
