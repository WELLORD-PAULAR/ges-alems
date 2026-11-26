<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import SectionController from '@/actions/App/Http/Controllers/Admin/SectionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/sections';

type SectionRow = {
    id: number;
    name: string;
    grade_level: string;
    grade_label: string;
    school_year: string;
    enrollments_count: number;
    assignments_count: number;
};

type GradeOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    sections: SectionRow[];
    grades: GradeOption[];
    schoolYears: string[];
    currentSchoolYear: string;
    filter: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sections',
                href: index(),
            },
        ],
    },
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const editing = ref<SectionRow | null>(null);
const formKey = ref(0);

// Remounting the form puts the dropdowns back to their defaults.
function resetForm(): void {
    editing.value = null;
    formKey.value++;
}

function inUse(section: SectionRow): boolean {
    return section.enrollments_count > 0 || section.assignments_count > 0;
}

function changeFilter(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    router.get(
        SectionController.index.url(),
        { school_year: value },
        { preserveScroll: true, replace: true },
    );
}

function remove(section: SectionRow): void {
    if (!window.confirm(`Delete ${section.name} (${section.school_year})?`)) {
        return;
    }

    router.delete(SectionController.destroy.url(section.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Sections" />

    <div class="grid gap-6 p-6 lg:grid-cols-[22rem_1fr]">
        <section class="space-y-4">
            <Heading
                variant="small"
                :title="editing ? 'Edit section' : 'Add section'"
                :description="
                    editing
                        ? `Editing ${editing.name}`
                        : 'A section belongs to one grade level in one school year.'
                "
            />

            <Form
                :key="`${editing ? `edit-${editing.id}` : 'create'}-${formKey}`"
                v-bind="
                    editing
                        ? SectionController.update.form(editing.id)
                        : SectionController.store.form()
                "
                :options="{ preserveScroll: true }"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="resetForm"
            >
                <div class="grid gap-2">
                    <Label for="name">Section name</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="editing?.name ?? ''"
                        required
                        maxlength="50"
                        placeholder="e.g. 3-Peace"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="grade_level">Grade level</Label>
                    <select
                        id="grade_level"
                        name="grade_level"
                        required
                        :class="selectClass"
                    >
                        <option
                            v-for="grade in grades"
                            :key="grade.value"
                            :value="grade.value"
                            :selected="editing?.grade_level === grade.value"
                        >
                            {{ grade.label }}
                        </option>
                    </select>
                    <InputError :message="errors.grade_level" />
                </div>

                <div class="grid gap-2">
                    <Label for="school_year">School year</Label>
                    <select
                        id="school_year"
                        name="school_year"
                        required
                        :class="selectClass"
                    >
                        <option
                            v-for="year in schoolYears"
                            :key="year"
                            :value="year"
                            :selected="
                                editing
                                    ? editing.school_year === year
                                    : year === currentSchoolYear
                            "
                        >
                            {{ year }}
                        </option>
                    </select>
                    <InputError :message="errors.school_year" />
                    <p
                        v-if="editing && inUse(editing)"
                        class="text-xs text-muted-foreground"
                    >
                        The grade level and school year are locked while
                        students or assignments use this section. You can
                        still rename it.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="save-section-button"
                    >
                        {{ editing ? 'Update section' : 'Add section' }}
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
            <div class="flex flex-wrap items-end justify-between gap-3">
                <Heading
                    variant="small"
                    title="Sections"
                    description="Kindergarten first, then Grade 1 to Grade 6."
                />
                <div class="grid gap-1">
                    <Label for="filter">School year</Label>
                    <select
                        id="filter"
                        :value="props.filter"
                        :class="`${selectClass} w-40`"
                        @change="changeFilter"
                    >
                        <option value="all">All years</option>
                        <option
                            v-for="year in schoolYears"
                            :key="year"
                            :value="year"
                        >
                            {{ year }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Grade</th>
                            <th class="px-4 py-2 font-medium">Section</th>
                            <th class="px-4 py-2 font-medium">School year</th>
                            <th class="px-4 py-2 font-medium">In use</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="sections.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                No sections for this school year.
                            </td>
                        </tr>
                        <tr
                            v-for="section in sections"
                            :key="section.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2">
                                {{ section.grade_label }}
                            </td>
                            <td class="px-4 py-2">{{ section.name }}</td>
                            <td class="px-4 py-2">
                                {{ section.school_year }}
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-1">
                                    <Badge variant="secondary">
                                        {{ section.enrollments_count }}
                                        students
                                    </Badge>
                                    <Badge
                                        v-if="section.assignments_count > 0"
                                        variant="outline"
                                    >
                                        {{ section.assignments_count }}
                                        assignments
                                    </Badge>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="editing = section"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        :disabled="inUse(section)"
                                        :title="
                                            inUse(section)
                                                ? 'Has students or assignments, so it cannot be deleted'
                                                : undefined
                                        "
                                        @click="remove(section)"
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
