
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ref } from 'vue';

const currentStep = ref(1);
const totalSteps = 6;

function goNext(): void {
    if (currentStep.value < totalSteps) {
        currentStep.value++;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function goPrevious(): void {
    if (currentStep.value > 1) {
        currentStep.value--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

defineOptions({
    layout: PublicLayout,
});

interface Option {
    label: string;
    value: string;
}

interface LookupOption {
    id: number;
    name: string;
}

const props = defineProps<{
    gradeLevels: Option[];
    sexOptions: Option[];
    lookups: {
        motherTongues: LookupOption[];
        religions: LookupOption[];
        indigenousGroups: LookupOption[];
    };
}>();

// Searchable lookup options
const motherTongueOptions = ref<LookupOption[]>([
    ...props.lookups.motherTongues,
]);

const religionOptions = ref<LookupOption[]>([
    ...props.lookups.religions,
]);

const indigenousGroupOptions = ref<LookupOption[]>([
    ...props.lookups.indigenousGroups,
]);

const motherTongueSearch = ref('');
const religionSearch = ref('');
const indigenousGroupSearch = ref('');

// Fetch lookup options from Laravel
async function fetchLookup(
    type: 'mother-tongues' | 'religions' | 'indigenous-groups',
    search: string,
): Promise<LookupOption[]> {
    const response = await fetch(
        `/lookups/${type}?search=${encodeURIComponent(search)}`,
        {
            headers: {
                Accept: 'application/json',
            },
        },
    );

    if (!response.ok) {
        throw new Error(`Unable to load ${type}.`);
    }

    return (await response.json()) as LookupOption[];
}

// Preserve the currently selected option when search results change
async function updateLookupOptions(
    type: 'mother-tongues' | 'religions' | 'indigenous-groups',
    search: string,
    selectName: string,
    currentOptions: LookupOption[],
): Promise<LookupOption[]> {
    const results = await fetchLookup(type, search);

    const select = document.querySelector<HTMLSelectElement>(
        `select[name="${selectName}"]`,
    );

    const selectedId = select?.value;

    if (
        selectedId &&
        !results.some((option) => String(option.id) === selectedId)
    ) {
        const selectedOption = currentOptions.find(
            (option) => String(option.id) === selectedId,
        );

        if (selectedOption) {
            results.unshift(selectedOption);
        }
    }

    return results;
}

async function searchMotherTongues(): Promise<void> {
    try {
        motherTongueOptions.value = await updateLookupOptions(
            'mother-tongues',
            motherTongueSearch.value,
            'mother_tongue_id',
            motherTongueOptions.value,
        );
    } catch (error) {
        console.error(error);
    }
}

async function searchReligions(): Promise<void> {
    try {
        religionOptions.value = await updateLookupOptions(
            'religions',
            religionSearch.value,
            'religion_id',
            religionOptions.value,
        );
    } catch (error) {
        console.error(error);
    }
}

async function searchIndigenousGroups(): Promise<void> {
    try {
        indigenousGroupOptions.value = await updateLookupOptions(
            'indigenous-groups',
            indigenousGroupSearch.value,
            'indigenous_group_id',
            indigenousGroupOptions.value,
        );
    } catch (error) {
        console.error(error);
    }
}

// Automatically convert ordinary text fields to uppercase
function uppercaseTextFields(event: Event): void {
    const target = event.target;

    if (
        !(target instanceof HTMLInputElement) &&
        !(target instanceof HTMLTextAreaElement)
    ) {
        return;
    }

    if (
        target instanceof HTMLInputElement &&
        (
            target.name === 'lrn' ||
            target.name === 'website' ||
            target.type !== 'text'
        )
    ) {
        return;
    }

    const start = target.selectionStart;
    const end = target.selectionEnd;

    target.value = target.value.toLocaleUpperCase();

    if (start !== null && end !== null) {
        target.setSelectionRange(start, end);
    }
}

const fieldClass =
    'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-black shadow-sm placeholder:text-slate-400 focus-visible:ring-red-600';

const selectClass =
    'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-black shadow-sm focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600';

// Restrict LRN to 12 numeric characters
function sanitizeLrn(event: Event): void {
    const input = event.target as HTMLInputElement;
    input.value = input.value.replace(/\D/g, '').slice(0, 12);
}

// Copy current address into permanent address
function copyCurrentAddress(event: Event): void {
    const button = event.currentTarget as HTMLButtonElement;
    const form = button.closest('form');

    if (!form) {
        return;
    }

    const addressFields = [
        'house_number',
        'street_name',
        'barangay',
        'municipality',
        'province',
        'country',
        'zip_code',
        'address_status',
    ];

    addressFields.forEach((field) => {
        const current = form.querySelector<HTMLInputElement | HTMLSelectElement>(
            `[name="current_${field}"]`,
        );

        const permanent = form.querySelector<HTMLInputElement | HTMLSelectElement>(
            `[name="permanent_${field}"]`,
        );

        if (current && permanent) {
            permanent.value = current.value;

            permanent.dispatchEvent(
                new Event('input', { bubbles: true }),
            );

            permanent.dispatchEvent(
                new Event('change', { bubbles: true }),
            );
        }
    });
}
</script>

<template>
    <Head title="Student Enrollment" />

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-lg font-bold text-red-700">
                Student Enrollment
            </h1>

            <p class="mt-1 text-sm text-slate-900">
                Please complete the enrollment form. Fields marked with
                an asterisk (*) are required.
            </p>
        </div>

        <!-- Enrollment Progress -->
        <div class="mb-10">
            <p class="text-sm font-semibold leading-none text-slate-900">
                Step {{ currentStep }} of {{ totalSteps }}
            </p>

            <div class="mt-1 grid grid-cols-6 gap-1">
                <div
                    v-for="step in totalSteps"
                    :key="step"
                    class="h-2 rounded-full transition-colors duration-300"
                    :class="step <= currentStep ? 'bg-red-700' : 'bg-slate-300'"
                ></div>
            </div>
        </div>

        <Form
            action="/enroll"
            method="post"
            :options="{ preserveScroll: true }"
            class="space-y-8"
            v-slot="{ errors, processing }"
            @input="uppercaseTextFields"
        >
            <!-- 1. Enrollment Details -->
            <section
                v-show="currentStep === 1"
                class="rounded-xl border border-slate-200 bg-slate-100 p-6 text-slate-900 shadow-sm"
            >
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-red-700">
                        1. Enrollment Details
                    </h2>

                    <p class="mt-1 text-sm text-slate-900">
                        Provide the student's enrollment information.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <Label for="grade_level">
                            Grade Level <span class="text-red-600">*</span>
                        </Label>

                        <select
                            id="grade_level"
                            name="grade_level"
                            required
                            :class="selectClass"
                        >
                            <option value="" disabled selected>
                                Select grade level
                            </option>

                            <option
                                v-for="grade in gradeLevels"
                                :key="grade.value"
                                :value="grade.value"
                            >
                                {{ grade.label }}
                            </option>
                        </select>

                        <InputError :message="errors.grade_level" />
                    </div>

                    <div>
                        <Label for="lrn">
                            Learner Reference Number (LRN)
                            <span class="text-red-600">*</span>
                        </Label>

                        <Input
                            id="lrn"
                            name="lrn"
                            type="text"
                            inputmode="numeric"
                            maxlength="12"
                            placeholder="Enter 12-digit LRN"
                            required
                            :class="fieldClass"
                            @input="sanitizeLrn"
                        />

                        <p class="text-sm font-medium text-slate-900">
                            Enter exactly 12 digits.
                        </p>

                        <InputError :message="errors.lrn" />
                    </div>
                </div>
            </section>

            <!-- 2. Personal Information -->
            <section
                v-show="currentStep === 2"
                class="rounded-xl border border-slate-200 bg-slate-100 p-6 text-slate-900 shadow-sm"
            >
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-red-700">
                        2. Personal Information
                    </h2>

                    <p class="mt-1 text-sm text-slate-900">
                        Provide the student's personal details.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <Label for="first_name">
                            First Name <span class="text-red-600">*</span>
                        </Label>

                        <Input
                            id="first_name"
                            name="first_name"
                            type="text"
                            maxlength="50"
                            required
                            :class="fieldClass"
                        />

                        <InputError :message="errors.first_name" />
                    </div>

                    <div>
                        <Label for="middle_name">
                            Middle Name
                        </Label>

                        <Input
                            id="middle_name"
                            name="middle_name"
                            type="text"
                            maxlength="50"
                            :class="fieldClass"
                        />

                        <InputError :message="errors.middle_name" />
                    </div>

                    <div>
                        <Label for="last_name">
                            Last Name <span class="text-red-600">*</span>
                        </Label>

                        <Input
                            id="last_name"
                            name="last_name"
                            type="text"
                            maxlength="50"
                            required
                            :class="fieldClass"
                        />

                        <InputError :message="errors.last_name" />
                    </div>

                    <div>
                        <Label for="extension">
                            Name Extension
                        </Label>

                        <Input
                            id="extension"
                            name="extension"
                            type="text"
                            maxlength="10"
                            placeholder="e.g. Jr., III"
                            :class="fieldClass"
                        />

                        <InputError :message="errors.extension" />
                    </div>

                    <div>
                        <Label for="birth_date">
                            Date of Birth <span class="text-red-600">*</span>
                        </Label>

                        <Input
                            id="birth_date"
                            name="birth_date"
                            type="date"
                            required
                            :class="fieldClass"
                        />

                        <InputError :message="errors.birth_date" />
                    </div>

                    <div>
                        <Label for="sex">
                            Sex <span class="text-red-600">*</span>
                        </Label>

                        <select
                            id="sex"
                            name="sex"
                            required
                            :class="selectClass"
                        >
                            <option value="" disabled selected>
                                Select sex
                            </option>

                            <option
                                v-for="option in sexOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <InputError :message="errors.sex" />
                    </div>

                    <div class="md:col-span-2">
                        <Label for="place_of_birth">
                            Place of Birth
                        </Label>

                        <Input
                            id="place_of_birth"
                            name="place_of_birth"
                            type="text"
                            maxlength="100"
                            placeholder="Enter place of birth"
                            :class="fieldClass"
                        />

                        <InputError :message="errors.place_of_birth" />
                    </div>

                    <!-- Mother Tongue -->
                    <div>
                        <Label for="mother_tongue_search">
                            Mother Tongue
                        </Label>

                        <Input
                            id="mother_tongue_search"
                            v-model="motherTongueSearch"
                            type="text"
                            placeholder="Search mother tongue..."
                            :class="fieldClass"
                            @input="searchMotherTongues"
                        />

                        <select
                            id="mother_tongue_id"
                            name="mother_tongue_id"
                            :class="selectClass"
                        >
                            <option value="">
                                Select mother tongue
                            </option>

                            <option
                                v-for="item in motherTongueOptions"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }}
                            </option>
                        </select>

                        <p
                            v-if="motherTongueOptions.length === 0"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Mother tongue options are currently unavailable.
                        </p>

                        <InputError :message="errors.mother_tongue_id" />
                    </div>

                    <!-- Religion -->
                    <div>
                        <Label for="religion_search">
                            Religion
                        </Label>

                        <Input
                            id="religion_search"
                            v-model="religionSearch"
                            type="text"
                            placeholder="Search religion..."
                            :class="fieldClass"
                            @input="searchReligions"
                        />

                        <select
                            id="religion_id"
                            name="religion_id"
                            :class="selectClass"
                        >
                            <option value="">
                                Select religion
                            </option>

                            <option
                                v-for="item in religionOptions"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }}
                            </option>
                        </select>

                        <p
                            v-if="religionOptions.length === 0"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Religion options are currently unavailable.
                        </p>

                        <InputError :message="errors.religion_id" />
                    </div>

                    <!-- Indigenous Group -->
                    <div class="md:col-span-2">
                        <Label for="indigenous_group_search">
                            Indigenous Group
                        </Label>

                        <Input
                            id="indigenous_group_search"
                            v-model="indigenousGroupSearch"
                            type="text"
                            placeholder="Search indigenous group..."
                            :class="fieldClass"
                            @input="searchIndigenousGroups"
                        />

                        <select
                            id="indigenous_group_id"
                            name="indigenous_group_id"
                            :class="selectClass"
                        >
                            <option value="">
                                Select indigenous group
                            </option>

                            <option
                                v-for="item in indigenousGroupOptions"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }}
                            </option>
                        </select>

                        <p
                            v-if="indigenousGroupOptions.length === 0"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Indigenous group options are currently unavailable.
                        </p>

                        <InputError :message="errors.indigenous_group_id" />
                    </div>
                </div>
            </section>

            <!-- 3. Address Information -->
            <section
                v-show="currentStep === 3"
                class="rounded-xl border border-slate-200 bg-slate-100 p-6 text-slate-900 shadow-sm"
            >
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-red-700">
                        3. Address Information
                    </h2>

                    <p class="mt-1 text-sm text-slate-900">
                        Provide the student's current and permanent addresses.
                    </p>
                </div>

                <!-- Current Address -->
                <div class="mb-8">
                    <h3 class="text-lg font-bold text-red-700">
                        Current Address
                    </h3>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <Label for="current_house_number">
                                House Number
                            </Label>

                            <Input
                                id="current_house_number"
                                name="current_house_number"
                                maxlength="50"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.current_house_number" />
                        </div>

                        <div>
                            <Label for="current_street_name">
                                Street Name
                            </Label>

                            <Input
                                id="current_street_name"
                                name="current_street_name"
                                maxlength="100"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.current_street_name" />
                        </div>

                        <div>
                            <Label for="current_barangay">
                                Barangay
                            </Label>

                            <Input
                                id="current_barangay"
                                name="current_barangay"
                                maxlength="100"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.current_barangay" />
                        </div>

                        <div>
                            <Label for="current_municipality">
                                Municipality / City
                                <span class="text-red-600">*</span>
                            </Label>

                            <Input
                                id="current_municipality"
                                name="current_municipality"
                                maxlength="100"
                                required
                                :class="fieldClass"
                            />

                            <InputError :message="errors.current_municipality" />
                        </div>

                        <div>
                            <Label for="current_province">
                                Province <span class="text-red-600">*</span>
                            </Label>

                            <Input
                                id="current_province"
                                name="current_province"
                                maxlength="100"
                                required
                                :class="fieldClass"
                            />

                            <InputError :message="errors.current_province" />
                        </div>

                        <div>
                            <Label for="current_country">
                                Country
                            </Label>

                            <Input
                                id="current_country"
                                name="current_country"
                                value="Philippines"
                                maxlength="100"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.current_country" />
                        </div>

                        <div>
                            <Label for="current_zip_code">
                                ZIP Code
                            </Label>

                            <Input
                                id="current_zip_code"
                                name="current_zip_code"
                                maxlength="10"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.current_zip_code" />
                        </div>

                        <div>
                            <Label for="current_address_status">
                                Address Status
                            </Label>

                            <select
                                id="current_address_status"
                                name="current_address_status"
                                :class="selectClass"
                            >
                                <option value="">
                                    Select address status
                                </option>
                                <option value="OWNED">Owned</option>
                                <option value="RENTED">Rented</option>
                                <option value="SHARED">Shared</option>
                                <option value="OTHER">Other</option>
                            </select>

                            <InputError :message="errors.current_address_status" />
                        </div>
                    </div>
                </div>

                <!-- Permanent Address -->
                <div>
                    <div
                        class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                    >
                        <h3 class="text-lg font-bold text-red-700">
                            Permanent Address
                        </h3>

                        <Button
                            type="button"
                            variant="outline"
                            @click="copyCurrentAddress"
                        >
                            Copy Current Address
                        </Button>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <Label for="permanent_house_number">
                                House Number
                            </Label>

                            <Input
                                id="permanent_house_number"
                                name="permanent_house_number"
                                maxlength="50"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.permanent_house_number" />
                        </div>

                        <div>
                            <Label for="permanent_street_name">
                                Street Name
                            </Label>

                            <Input
                                id="permanent_street_name"
                                name="permanent_street_name"
                                maxlength="100"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.permanent_street_name" />
                        </div>

                        <div>
                            <Label for="permanent_barangay">
                                Barangay
                            </Label>

                            <Input
                                id="permanent_barangay"
                                name="permanent_barangay"
                                maxlength="100"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.permanent_barangay" />
                        </div>

                        <div>
                            <Label for="permanent_municipality">
                                Municipality / City
                            </Label>

                            <Input
                                id="permanent_municipality"
                                name="permanent_municipality"
                                maxlength="100"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.permanent_municipality" />
                        </div>

                        <div>
                            <Label for="permanent_province">
                                Province
                            </Label>

                            <Input
                                id="permanent_province"
                                name="permanent_province"
                                maxlength="100"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.permanent_province" />
                        </div>

                        <div>
                            <Label for="permanent_country">
                                Country
                            </Label>

                            <Input
                                id="permanent_country"
                                name="permanent_country"
                                value="Philippines"
                                maxlength="100"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.permanent_country" />
                        </div>

                        <div>
                            <Label for="permanent_zip_code">
                                ZIP Code
                            </Label>

                            <Input
                                id="permanent_zip_code"
                                name="permanent_zip_code"
                                maxlength="10"
                                :class="fieldClass"
                            />

                            <InputError :message="errors.permanent_zip_code" />
                        </div>

                        <div>
                            <Label for="permanent_address_status">
                                Address Status
                            </Label>

                            <select
                                id="permanent_address_status"
                                name="permanent_address_status"
                                :class="selectClass"
                            >
                                <option value="">
                                    Select address status
                                </option>
                                <option value="OWNED">Owned</option>
                                <option value="RENTED">Rented</option>
                                <option value="SHARED">Shared</option>
                                <option value="OTHER">Other</option>
                            </select>

                            <InputError :message="errors.permanent_address_status" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- 4. Parents and Guardian Information -->
            <section
                v-show="currentStep === 4"
                class="rounded-xl border border-slate-200 bg-slate-100 p-6 text-slate-900 shadow-sm"
            >
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-red-700">
                        4. Parents and Guardian Information
                    </h2>

                    <p class="mt-1 text-sm text-slate-900">
                        Provide the available information for the student's
                        father, mother, or guardian.
                    </p>

                    <p class="mt-2 text-sm text-slate-900">
                        At least one parent or guardian's first name and last
                        name must be provided.
                    </p>
                </div>

                <!-- Father -->
                <div class="mb-8">
                    <h3 class="mb-4 text-lg font-bold text-red-700">
                        Father's Information
                    </h3>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <Label for="father_first_name">First Name</Label>
                            <Input id="father_first_name" name="father_first_name" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.father_first_name" />
                        </div>

                        <div>
                            <Label for="father_middle_name">Middle Name</Label>
                            <Input id="father_middle_name" name="father_middle_name" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.father_middle_name" />
                        </div>

                        <div>
                            <Label for="father_last_name">Last Name</Label>
                            <Input id="father_last_name" name="father_last_name" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.father_last_name" />
                        </div>

                        <div>
                            <Label for="father_contact_number">Contact Number</Label>
                            <Input id="father_contact_number" name="father_contact_number" type="tel" maxlength="20" :class="fieldClass" />
                            <InputError :message="errors.father_contact_number" />
                        </div>

                        <div>
                            <Label for="father_occupation">Occupation</Label>
                            <Input id="father_occupation" name="father_occupation" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.father_occupation" />
                        </div>

                        <div>
                            <Label for="father_relationship_status">Relationship Status</Label>
                            <Input id="father_relationship_status" name="father_relationship_status" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.father_relationship_status" />
                        </div>

                        <div>
                            <Label for="father_communication">Preferred Communication</Label>
                            <Input id="father_communication" name="father_communication" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.father_communication" />
                        </div>
                    </div>
                </div>

                <!-- Mother -->
                <div class="mb-8">
                    <h3 class="text-lg font-bold text-red-700">
                        Mother's Information
                    </h3>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <Label for="mother_first_name">First Name</Label>
                            <Input id="mother_first_name" name="mother_first_name" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.mother_first_name" />
                        </div>

                        <div>
                            <Label for="mother_middle_name">Middle Name</Label>
                            <Input id="mother_middle_name" name="mother_middle_name" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.mother_middle_name" />
                        </div>

                        <div>
                            <Label for="mother_last_name">Last Name</Label>
                            <Input id="mother_last_name" name="mother_last_name" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.mother_last_name" />
                        </div>

                        <div>
                            <Label for="mother_contact_number">Contact Number</Label>
                            <Input id="mother_contact_number" name="mother_contact_number" type="tel" maxlength="20" :class="fieldClass" />
                            <InputError :message="errors.mother_contact_number" />
                        </div>

                        <div>
                            <Label for="mother_occupation">Occupation</Label>
                            <Input id="mother_occupation" name="mother_occupation" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.mother_occupation" />
                        </div>

                        <div>
                            <Label for="mother_relationship_status">Relationship Status</Label>
                            <Input id="mother_relationship_status" name="mother_relationship_status" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.mother_relationship_status" />
                        </div>

                        <div>
                            <Label for="mother_communication">Preferred Communication</Label>
                            <Input id="mother_communication" name="mother_communication" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.mother_communication" />
                        </div>
                    </div>
                </div>

                <!-- Guardian -->
                <div>
                    <h3 class="mb-4 text-lg font-bold text-red-700">
                        Guardian's Information
                    </h3>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <Label for="guardian_first_name">First Name</Label>
                            <Input id="guardian_first_name" name="guardian_first_name" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.guardian_first_name" />
                        </div>

                        <div>
                            <Label for="guardian_middle_name">Middle Name</Label>
                            <Input id="guardian_middle_name" name="guardian_middle_name" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.guardian_middle_name" />
                        </div>

                        <div>
                            <Label for="guardian_last_name">Last Name</Label>
                            <Input id="guardian_last_name" name="guardian_last_name" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.guardian_last_name" />
                        </div>

                        <div>
                            <Label for="guardian_contact_number">Contact Number</Label>
                            <Input id="guardian_contact_number" name="guardian_contact_number" type="tel" maxlength="20" :class="fieldClass" />
                            <InputError :message="errors.guardian_contact_number" />
                        </div>

                        <div>
                            <Label for="guardian_occupation">Occupation</Label>
                            <Input id="guardian_occupation" name="guardian_occupation" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.guardian_occupation" />
                        </div>

                        <div>
                            <Label for="guardian_relationship_status">Relationship Status</Label>
                            <Input id="guardian_relationship_status" name="guardian_relationship_status" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.guardian_relationship_status" />
                        </div>

                        <div>
                            <Label for="guardian_communication">Preferred Communication</Label>
                            <Input id="guardian_communication" name="guardian_communication" maxlength="50" :class="fieldClass" />
                            <InputError :message="errors.guardian_communication" />
                        </div>

                        <div>
                            <Label for="emergency_contact">Emergency Contact Number</Label>
                            <Input id="emergency_contact" name="emergency_contact" type="tel" maxlength="10" :class="fieldClass" />
                            <InputError :message="errors.emergency_contact" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- 5. Medical Information -->
            <section
                v-show="currentStep === 5"
                class="rounded-xl border border-slate-200 bg-slate-100 p-6 text-slate-900 shadow-sm"
            >
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-red-700">
                        5. Medical Information
                    </h2>

                    <p class="mt-1 text-sm text-slate-900">
                        Please provide relevant medical information to help the
                        school support your child's health and safety. All fields
                        in this section are optional.
                    </p>
                </div>

                <!-- Allergies -->
                <div class="mb-8">
                    <h3 class="mb-4 text-lg font-bold text-red-700">
                        Allergies
                    </h3>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <Label for="allergy_medicine">Medicine Allergies</Label>
                            <Input id="allergy_medicine" name="allergy_medicine" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.allergy_medicine" />
                        </div>

                        <div>
                            <Label for="allergy_pollen">Pollen Allergies</Label>
                            <Input id="allergy_pollen" name="allergy_pollen" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.allergy_pollen" />
                        </div>

                        <div>
                            <Label for="allergy_food">Food Allergies</Label>
                            <Input id="allergy_food" name="allergy_food" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.allergy_food" />
                        </div>

                        <div>
                            <Label for="allergy_others">Other Allergies</Label>
                            <Input id="allergy_others" name="allergy_others" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.allergy_others" />
                        </div>
                    </div>
                </div>

                <!-- Existing Medical Conditions -->
                <div class="mb-8">
                    <h3 class="mb-2 text-lg font-bold text-red-700">
                        Existing Medical Conditions
                    </h3>

                    <p class="mb-4 text-sm text-slate-900">
                        Select all conditions that apply.
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="medical_conditions[]" value="Asthma" class="h-4 w-4 accent-red-700" />
                            Asthma
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="medical_conditions[]" value="Diabetes" class="h-4 w-4 accent-red-700" />
                            Diabetes
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="medical_conditions[]" value="Epilepsy" class="h-4 w-4 accent-red-700" />
                            Epilepsy / Seizures
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="medical_conditions[]" value="Heart Condition" class="h-4 w-4 accent-red-700" />
                            Heart Condition
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="medical_conditions[]" value="Hypertension" class="h-4 w-4 accent-red-700" />
                            Hypertension
                        </label>
                    </div>

                    <div class="mt-4">
                        <Label for="medical_conditions_others">Other Medical Conditions</Label>
                        <Input id="medical_conditions_others" name="medical_conditions_others" maxlength="100" :class="fieldClass" />
                        <InputError :message="errors.medical_conditions_others" />
                    </div>
                </div>

                <!-- Surgery History -->
                <div class="mb-8">
                    <h3 class="mb-4 text-lg font-bold text-red-700">
                        Surgery History
                    </h3>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <Label for="surgery_date">Date of Surgery</Label>
                            <Input id="surgery_date" name="surgery_date" type="date" :class="fieldClass" />
                            <InputError :message="errors.surgery_date" />
                        </div>

                        <div>
                            <Label for="surgery_hospital">Hospital or Medical Facility</Label>
                            <Input id="surgery_hospital" name="surgery_hospital" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.surgery_hospital" />
                        </div>

                        <div class="md:col-span-2">
                            <Label for="surgery_body_part">Surgery Performed / Body Part</Label>
                            <Input id="surgery_body_part" name="surgery_body_part" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.surgery_body_part" />
                        </div>
                    </div>
                </div>

                <!-- Current Medication -->
                <div class="mb-8">
                    <h3 class="mb-4 text-lg font-bold text-red-700">
                        Current Medication
                    </h3>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <Label for="medication_type">Medication Name / Type</Label>
                            <Input id="medication_type" name="medication_type" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.medication_type" />
                        </div>

                        <div>
                            <Label for="medication_dosage_schedule">Dosage and Schedule</Label>
                            <Input id="medication_dosage_schedule" name="medication_dosage_schedule" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.medication_dosage_schedule" />
                        </div>
                    </div>
                </div>

                <!-- Chronic Conditions -->
                <div class="mb-8">
                    <h3 class="mb-2 text-lg font-bold text-red-700">
                        Chronic Conditions
                    </h3>

                    <p class="mb-4 text-sm text-slate-900">
                        Select all applicable long-term health conditions.
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="chronic_conditions[]" value="Cancer" class="h-4 w-4 accent-red-700" />
                            Cancer
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="chronic_conditions[]" value="Kidney Disease" class="h-4 w-4 accent-red-700" />
                            Kidney Disease
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="chronic_conditions[]" value="Liver Disease" class="h-4 w-4 accent-red-700" />
                            Liver Disease
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="chronic_conditions[]" value="Chronic Respiratory Disease" class="h-4 w-4 accent-red-700" />
                            Chronic Respiratory Disease
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-900">
                            <input type="checkbox" name="chronic_conditions[]" value="Chronic Heart Disease" class="h-4 w-4 accent-red-700" />
                            Chronic Heart Disease
                        </label>
                    </div>

                    <div class="mt-4 grid gap-5 md:grid-cols-2">
                        <div>
                            <Label for="cancer_type">Cancer Type (if applicable)</Label>
                            <Input id="cancer_type" name="cancer_type" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.cancer_type" />
                        </div>

                        <div>
                            <Label for="chronic_conditions_others">Other Chronic Conditions</Label>
                            <Input id="chronic_conditions_others" name="chronic_conditions_others" maxlength="100" :class="fieldClass" />
                            <InputError :message="errors.chronic_conditions_others" />
                        </div>
                    </div>
                </div>

                <!-- COVID Exposure -->
                <div class="mb-8">
                    <h3 class="mb-4 text-lg font-bold text-red-700">
                        COVID-19 Exposure
                    </h3>

                    <div>
                        <Label for="exposed_to_covid">
                            Has the student been exposed to COVID-19?
                        </Label>

                        <select
                            id="exposed_to_covid"
                            name="exposed_to_covid"
                            :class="selectClass"
                        >
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>

                        <InputError :message="errors.exposed_to_covid" />
                    </div>
                </div>

                <!-- Additional Medical Information -->
                <div>
                    <h3 class="mb-4 text-lg font-bold text-red-700">
                        Additional Information
                    </h3>

                    <div>
                        <Label for="pertinent_information">
                            Other Relevant Medical Information
                        </Label>

                        <textarea
                            id="pertinent_information"
                            name="pertinent_information"
                            maxlength="255"
                            rows="4"
                            class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-black shadow-sm focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600"
                            placeholder="Enter any other relevant information..."
                        ></textarea>

                        <InputError :message="errors.pertinent_information" />
                    </div>
                </div>
            </section>

            <!-- 6. Special Needs Information -->
            <section
                v-show="currentStep === 6"
                class="rounded-xl border border-slate-200 bg-slate-100 p-6 text-slate-900 shadow-sm"
            >
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-red-700">
                        6. Special Needs Information
                    </h2>

                    <p class="mt-1 text-sm text-slate-700">
                        Please provide the following information if the learner has
                        special needs or a diagnosed condition. All fields are optional.
                    </p>
                </div>

                <div class="space-y-6">
                    <!-- Diagnosis -->
                    <div>
                        <Label class="font-semibold text-slate-900">
                            Diagnosis
                        </Label>

                        <p class="mb-3 text-sm text-slate-700">
                            Select all that apply. Select None if there is no diagnosed condition.
                        </p>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="diagnosis[]"
                                    value="ADHD"
                                    class="rounded border-slate-300 text-red-600 focus:ring-red-600"
                                />
                                ADHD
                            </label>

                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="diagnosis[]"
                                    value="LD"
                                    class="rounded border-slate-300 text-red-600 focus:ring-red-600"
                                />
                                Learning Disability (LD)
                            </label>

                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="diagnosis[]"
                                    value="NONE"
                                    class="rounded border-slate-300 text-red-600 focus:ring-red-600"
                                />
                                None
                            </label>
                        </div>

                        <div class="mt-3">
                            <Label for="diagnosis_other" class="text-slate-900">
                                Other Diagnosis
                            </Label>

                            <input
                                id="diagnosis_other"
                                name="diagnosis[]"
                                type="text"
                                maxlength="100"
                                placeholder="Specify other diagnosis"
                                class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-black shadow-sm placeholder:text-slate-400 focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600"
                            />
                        </div>
                    </div>

                    <!-- SHPCD Subtype -->
                    <div>
                        <Label for="shpcd_subtype" class="font-semibold text-slate-900">
                            SHPCD Subtype
                        </Label>

                        <p class="text-sm text-slate-700">
                            Enter the subtype if applicable. Enter NONE if not applicable.
                        </p>

                        <input
                            id="shpcd_subtype"
                            name="shpcd_subtype"
                            type="text"
                            maxlength="20"
                            placeholder="Enter subtype or NONE"
                            class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-black shadow-sm placeholder:text-slate-400 focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600"
                        />
                    </div>

                    <!-- Visual Impairment Subtype -->
                    <div>
                        <Label for="vi_subtype" class="font-semibold text-slate-900">
                            Visual Impairment (VI) Subtype
                        </Label>

                        <p class="text-sm text-slate-700">
                            Enter the subtype if applicable. Enter NONE if not applicable.
                        </p>

                        <input
                            id="vi_subtype"
                            name="vi_subtype"
                            type="text"
                            maxlength="20"
                            placeholder="Enter subtype or NONE"
                            class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-black shadow-sm placeholder:text-slate-400 focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600"
                        />
                    </div>

                    <!-- Manifestations -->
                    <div>
                        <Label class="font-semibold text-slate-900">
                            Manifestations
                        </Label>

                        <p class="mb-3 text-sm text-slate-700">
                            Specify any known manifestations, if applicable.
                        </p>

                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                name="manifestations[]"
                                value="NONE"
                                class="rounded border-slate-300 text-red-600 focus:ring-red-600"
                            />
                            None
                        </label>

                        <div class="mt-3">
                            <Label for="manifestations_other" class="text-slate-900">
                                Other Manifestations
                            </Label>

                            <input
                                id="manifestations_other"
                                name="manifestations[]"
                                type="text"
                                maxlength="100"
                                placeholder="Specify manifestations"
                                class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-black shadow-sm placeholder:text-slate-400 focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600"
                            />
                        </div>
                    </div>

                    <!-- PWD ID -->
                    <div>
                        <Label for="has_pwd_id" class="font-semibold text-slate-900">
                            Does the learner have a PWD ID?
                        </Label>

                        <select
                            id="has_pwd_id"
                            name="has_pwd_id"
                            :class="selectClass"
                        >
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Honeypot field: leave hidden -->
            <div
                class="absolute left-[-9999px] top-auto h-px w-px overflow-hidden"
                aria-hidden="true"
            >
                <Label for="website">
                    Website
                </Label>

                <Input
                    id="website"
                    name="website"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>

            <!-- Wizard Navigation -->
            <div class="flex flex-col-reverse justify-between gap-3 sm:flex-row">
                <Button
                    v-if="currentStep > 1"
                    type="button"
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="goPrevious"
                >
                    Previous
                </Button>

                <div v-else></div>

                <Button
                    v-if="currentStep < totalSteps"
                    type="button"
                    class="w-full sm:w-auto"
                    @click="goNext"
                >
                    Next
                </Button>

                <Button
                    v-else
                    type="submit"
                    class="w-full sm:w-auto"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    {{ processing ? 'Submitting...' : 'Submit Enrollment' }}
                </Button>
            </div>
        </Form>
    </div>
</template>
