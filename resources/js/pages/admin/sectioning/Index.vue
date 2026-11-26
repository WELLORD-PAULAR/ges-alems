<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import SectioningController from '@/actions/App/Http/Controllers/Admin/SectioningController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/sectioning';

type StudentRow = {
    enrollment_id: number;
    full_name: string;
    lrn: string | null;
    verification: { value: string; label: string; tone: string };
    section_id: number | null;
};

type SectionOption = { id: number; name: string };
type GradeOption = { value: string; label: string };
type StatusOption = { value: string; label: string };

type BulkResult = {
    enrollment_id: number;
    ok: boolean;
    message: string | null;
};

type BulkResponse = {
    saved: number;
    failed: number;
    results: BulkResult[];
};

const props = defineProps<{
    schoolYear: string;
    schoolYears: string[];
    grade: string;
    status: string | null;
    grades: GradeOption[];
    statuses: StatusOption[];
    students: StudentRow[];
    sections: SectionOption[];
    gradeCounts: Record<string, number>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sectioning',
                href: index(),
            },
        ],
    },
});

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

// Changes that are chosen but not saved yet, by enrollment id. A null value
// means "remove from section". They survive switching tab or filter, so one
// "Save all" can cover several grades.
const pending = ref<Record<number, number | null>>({});
const rowErrors = ref<Record<number, string>>({});
const search = ref('');

const pendingCount = computed(() => Object.keys(pending.value).length);

const visibleStudents = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return props.students;
    }

    return props.students.filter(
        (student) =>
            student.full_name.toLowerCase().includes(term) ||
            (student.lrn ?? '').toLowerCase().includes(term),
    );
});

function go(changes: {
    school_year?: string;
    grade?: string;
    status?: string | null;
}): void {
    const query: Record<string, string> = {
        school_year: changes.school_year ?? props.schoolYear,
        grade: changes.grade ?? props.grade,
    };

    const status =
        changes.status === undefined ? props.status : changes.status;

    if (status) {
        query.status = status;
    }

    router.get(SectioningController.index.url(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onYearChange(event: Event): void {
    go({ school_year: (event.target as HTMLSelectElement).value });
}

function onStatusChange(event: Event): void {
    go({ status: (event.target as HTMLSelectElement).value || null });
}

function resetFilters(): void {
    search.value = '';
    go({ status: null });
}

function valueFor(student: StudentRow): string {
    const value =
        student.enrollment_id in pending.value
            ? pending.value[student.enrollment_id]
            : student.section_id;

    return value === null || value === undefined ? '' : String(value);
}

function onSectionChange(student: StudentRow, raw: string): void {
    const next = raw === '' ? null : Number(raw);

    delete rowErrors.value[student.enrollment_id];

    if (next === student.section_id) {
        delete pending.value[student.enrollment_id];

        return;
    }

    pending.value[student.enrollment_id] = next;
}

function discard(): void {
    pending.value = {};
    rowErrors.value = {};
}

function saveOne(student: StudentRow): void {
    const sectionId = pending.value[student.enrollment_id] ?? null;

    router.put(
        SectioningController.assign.url(student.enrollment_id),
        { section_id: sectionId },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                delete pending.value[student.enrollment_id];
                delete rowErrors.value[student.enrollment_id];
            },
            onError: (errors) => {
                rowErrors.value[student.enrollment_id] =
                    errors.section_id ?? 'Could not save this change.';
            },
        },
    );
}

const bulk = useHttp<
    { assignments: { enrollment_id: number; section_id: number | null }[] },
    BulkResponse
>({ assignments: [] });

async function saveAll(): Promise<void> {
    bulk.assignments = Object.entries(pending.value).map(
        ([enrollmentId, sectionId]) => ({
            enrollment_id: Number(enrollmentId),
            section_id: sectionId,
        }),
    );

    let response: BulkResponse | undefined;

    try {
        response = await bulk.submit(SectioningController.bulk());
    } catch {
        toast.error('Could not save the changes. Please try again.');

        return;
    }

    if (!response) {
        return;
    }

    for (const result of response.results) {
        if (result.ok) {
            delete pending.value[result.enrollment_id];
            delete rowErrors.value[result.enrollment_id];
        } else {
            rowErrors.value[result.enrollment_id] =
                result.message ?? 'Could not save this change.';
        }
    }

    if (response.failed === 0) {
        toast.success(`Saved ${response.saved} assignment(s).`);
    } else {
        toast.error(
            `Saved ${response.saved}, but ${response.failed} could not be saved. See the rows marked in red.`,
        );
    }

    router.reload({ only: ['students', 'gradeCounts'] });
}
</script>

<template>
    <Head title="Sectioning" />

    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <Heading
                variant="small"
                title="Sectioning"
                description="Place students in a section of their own grade level. Nothing is saved until you save it."
            />

            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1">
                    <Label for="school_year">School year</Label>
                    <select
                        id="school_year"
                        :value="schoolYear"
                        :class="selectClass"
                        @change="onYearChange"
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
                    <Label for="status">Status</Label>
                    <select
                        id="status"
                        :value="status ?? ''"
                        :class="selectClass"
                        @change="onStatusChange"
                    >
                        <option value="">All students</option>
                        <option
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-1">
                    <Label for="search">Search</Label>
                    <Input
                        id="search"
                        v-model="search"
                        type="search"
                        placeholder="Name or LRN"
                        class="w-52"
                    />
                </div>

                <Button type="button" variant="outline" @click="resetFilters">
                    Reset filters
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap gap-1 border-b">
            <button
                v-for="option in grades"
                :key="option.value"
                type="button"
                class="-mb-px border-b-2 px-4 py-2 text-sm font-medium"
                :class="
                    option.value === grade
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="go({ grade: option.value })"
            >
                {{ option.label }}
                <span class="ml-1 text-xs text-muted-foreground">
                    ({{ gradeCounts[option.value] ?? 0 }})
                </span>
            </button>
        </div>

        <p
            v-if="sections.length === 0"
            class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
        >
            There are no sections for this grade in {{ schoolYear }} yet. Add
            them on the Sections page first.
        </p>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Student</th>
                        <th class="px-4 py-2 font-medium">LRN</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Section</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Save
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="visibleStudents.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No students found for these filters.
                        </td>
                    </tr>
                    <tr
                        v-for="student in visibleStudents"
                        :key="student.enrollment_id"
                        class="border-t align-top"
                        :class="
                            rowErrors[student.enrollment_id]
                                ? 'bg-destructive/5'
                                : ''
                        "
                    >
                        <td class="px-4 py-2">{{ student.full_name }}</td>
                        <td class="px-4 py-2 font-mono">
                            {{ student.lrn ?? '-' }}
                        </td>
                        <td class="px-4 py-2">
                            <Badge variant="outline">
                                {{ student.verification.label }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2">
                            <select
                                :value="valueFor(student)"
                                :class="selectClass"
                                :aria-label="`Section for ${student.full_name}`"
                                @change="
                                    onSectionChange(
                                        student,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option value="">Unsectioned</option>
                                <option
                                    v-for="section in sections"
                                    :key="section.id"
                                    :value="String(section.id)"
                                >
                                    {{ section.name }}
                                </option>
                            </select>
                            <p
                                v-if="rowErrors[student.enrollment_id]"
                                class="mt-1 text-xs text-destructive"
                            >
                                {{ rowErrors[student.enrollment_id] }}
                            </p>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <Button
                                v-if="student.enrollment_id in pending"
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="saveOne(student)"
                            >
                                Save
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="sticky bottom-4 flex items-center justify-between gap-3 rounded-lg border bg-background p-3 shadow-sm"
        >
            <p class="text-sm text-muted-foreground">
                {{ pendingCount }} unsaved change(s)
            </p>
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="pendingCount === 0 || bulk.processing"
                    @click="discard"
                >
                    Discard
                </Button>
                <Button
                    type="button"
                    :disabled="pendingCount === 0 || bulk.processing"
                    data-test="save-all-button"
                    @click="saveAll"
                >
                    Save all ({{ pendingCount }})
                </Button>
            </div>
        </div>
    </div>
</template>
