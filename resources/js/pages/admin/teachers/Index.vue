<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import TeacherController from '@/actions/App/Http/Controllers/Admin/TeacherController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/teachers';

type TeacherRow = {
    id: number;
    full_name: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    employee_no: string | null;
    email: string;
    assignments_count: number;
};

type AccountOption = {
    id: number;
    name: string;
    email: string;
};

type AccountMode = 'existing' | 'new';

const props = defineProps<{
    teachers: TeacherRow[];
    accounts: AccountOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Teachers',
                href: index(),
            },
        ],
    },
});

function defaultMode(): AccountMode {
    return props.accounts.length > 0 ? 'existing' : 'new';
}

const editing = ref<TeacherRow | null>(null);
const mode = ref<AccountMode>(defaultMode());
const formKey = ref(0);

function resetForm(): void {
    editing.value = null;
    mode.value = defaultMode();
    formKey.value++;
}

function remove(teacher: TeacherRow): void {
    if (!window.confirm(`Delete ${teacher.full_name}?`)) {
        return;
    }

    router.delete(TeacherController.destroy.url(teacher.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Teachers" />

    <div class="grid gap-6 p-6 lg:grid-cols-[24rem_1fr]">
        <section class="space-y-4">
            <Heading
                variant="small"
                :title="editing ? 'Edit teacher' : 'Add teacher'"
                :description="
                    editing
                        ? `Editing ${editing.full_name}`
                        : 'Each teacher needs a login account with the Teacher role.'
                "
            />

            <Form
                :key="`${editing ? `edit-${editing.id}` : 'create'}-${formKey}`"
                v-bind="
                    editing
                        ? TeacherController.update.form(editing.id)
                        : TeacherController.store.form()
                "
                :options="{ preserveScroll: true }"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="resetForm"
            >
                <div class="grid gap-2">
                    <Label for="first_name">First name</Label>
                    <Input
                        id="first_name"
                        name="first_name"
                        :default-value="editing?.first_name ?? ''"
                        required
                        maxlength="50"
                    />
                    <InputError :message="errors.first_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="middle_name">Middle name</Label>
                    <Input
                        id="middle_name"
                        name="middle_name"
                        :default-value="editing?.middle_name ?? ''"
                        maxlength="50"
                    />
                    <InputError :message="errors.middle_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="last_name">Last name</Label>
                    <Input
                        id="last_name"
                        name="last_name"
                        :default-value="editing?.last_name ?? ''"
                        required
                        maxlength="50"
                    />
                    <InputError :message="errors.last_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="employee_no">Employee number</Label>
                    <Input
                        id="employee_no"
                        name="employee_no"
                        :default-value="editing?.employee_no ?? ''"
                        maxlength="30"
                        placeholder="Optional, must be unique"
                    />
                    <InputError :message="errors.employee_no" />
                </div>

                <p v-if="editing" class="text-sm text-muted-foreground">
                    Login account: {{ editing.email }}
                </p>

                <fieldset
                    v-else
                    class="space-y-3 rounded-lg border p-3"
                >
                    <legend class="px-1 text-sm font-medium">
                        Login account
                    </legend>

                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="radio"
                            name="account_mode"
                            value="existing"
                            :checked="mode === 'existing'"
                            @change="mode = 'existing'"
                        />
                        Link an existing teacher account
                    </label>

                    <div v-if="mode === 'existing'" class="grid gap-2 pl-6">
                        <Label for="user_id">Account</Label>
                        <select
                            id="user_id"
                            name="user_id"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        >
                            <option value="">Select an account</option>
                            <option
                                v-for="account in accounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.name }} ({{ account.email }})
                            </option>
                        </select>
                        <p
                            v-if="accounts.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            No unlinked teacher accounts. Create a login
                            instead.
                        </p>
                        <InputError :message="errors.user_id" />
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="radio"
                            name="account_mode"
                            value="new"
                            :checked="mode === 'new'"
                            @change="mode = 'new'"
                        />
                        Create a login now
                    </label>

                    <div v-if="mode === 'new'" class="grid gap-4 pl-6">
                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autocomplete="off"
                            />
                            <InputError :message="errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="password">Temporary password</Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                            />
                            <InputError :message="errors.password" />
                        </div>
                    </div>

                    <InputError :message="errors.account_mode" />
                </fieldset>

                <div class="flex items-center gap-2">
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="save-teacher-button"
                    >
                        {{ editing ? 'Update teacher' : 'Add teacher' }}
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
                title="Teachers"
                description="Sorted by last name, then first name."
            />

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Name</th>
                            <th class="px-4 py-2 font-medium">Employee #</th>
                            <th class="px-4 py-2 font-medium">Login</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="teachers.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                No teachers yet. Add the first one.
                            </td>
                        </tr>
                        <tr
                            v-for="teacher in teachers"
                            :key="teacher.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2">{{ teacher.full_name }}</td>
                            <td class="px-4 py-2 font-mono">
                                {{ teacher.employee_no ?? '-' }}
                            </td>
                            <td class="px-4 py-2">{{ teacher.email }}</td>
                            <td class="px-4 py-2">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="editing = teacher"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        :disabled="teacher.assignments_count > 0"
                                        :title="
                                            teacher.assignments_count > 0
                                                ? 'Has assignments, so it cannot be deleted'
                                                : undefined
                                        "
                                        @click="remove(teacher)"
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
