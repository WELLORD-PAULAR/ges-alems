<?php

namespace App\Http\Requests\Enrollment;

use App\Enums\GradeLevel;
use App\Enums\Sex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Enrollment details
            |--------------------------------------------------------------------------
            */
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
            'lrn' => ['required', 'string', 'digits:12', Rule::unique('enrollments', 'lrn')],
            'last_grade_level_completed' => ['nullable', 'string', 'max:10'],
            'last_school_year_completed' => ['nullable', 'string', 'max:20'],
            'last_school_attended' => ['nullable', 'string', 'max:100'],
            'last_school_id' => ['nullable', 'string', 'max:10'],
            'attended_early_learning_program_name' => ['nullable', 'string', 'max:100'],
            'four_ps_household_number' => ['nullable', 'string', 'max:30'],
            'learning_classification' => ['nullable', 'string', 'max:20'],
            'learning_modality' => ['nullable', 'array'],
            'learning_modality.*' => ['string', 'max:50'],

            /*
            |--------------------------------------------------------------------------
            | Personal information
            |--------------------------------------------------------------------------
            */
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'extension' => ['nullable', 'string', 'max:10'],
            'birth_date' => ['required', 'date', 'before:today'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'place_of_birth' => ['nullable', 'string', 'max:100'],

            'mother_tongue_id' => ['nullable', 'integer', 'exists:mother_tongues,id'],
            'religion_id' => ['nullable', 'integer', 'exists:religions,id'],
            'indigenous_group_id' => ['nullable', 'integer', 'exists:indigenous_groups,id'],

            /*
            |--------------------------------------------------------------------------
            | Current and permanent address
            |--------------------------------------------------------------------------
            */
            'current_house_number' => ['nullable', 'string', 'max:20'],
            'current_street_name' => ['nullable', 'string', 'max:100'],
            'current_barangay' => ['nullable', 'string', 'max:100'],
            'current_municipality' => ['required', 'string', 'max:100'],
            'current_province' => ['required', 'string', 'max:100'],
            'current_country' => ['nullable', 'string', 'max:100'],
            'current_zip_code' => ['nullable', 'string', 'max:10'],
            'current_address_status' => ['nullable', 'string', 'max:30'],

            'permanent_house_number' => ['nullable', 'string', 'max:20'],
            'permanent_street_name' => ['nullable', 'string', 'max:100'],
            'permanent_barangay' => ['nullable', 'string', 'max:100'],
            'permanent_municipality' => ['nullable', 'string', 'max:100'],
            'permanent_province' => ['nullable', 'string', 'max:100'],
            'permanent_country' => ['nullable', 'string', 'max:100'],
            'permanent_zip_code' => ['nullable', 'string', 'max:10'],
            'permanent_address_status' => ['nullable', 'string', 'max:30'],

            /*
            |--------------------------------------------------------------------------
            | Parent and guardian information
            |--------------------------------------------------------------------------
            */
            'father_first_name' => ['nullable', 'string', 'max:50'],
            'father_middle_name' => ['nullable', 'string', 'max:50'],
            'father_last_name' => ['nullable', 'string', 'max:50'],
            'father_contact_number' => ['nullable', 'string', 'max:20'],
            'father_occupation' => ['nullable', 'string', 'max:100'],
            'father_relationship_status' => ['nullable', 'string', 'max:50'],
            'father_communication' => ['nullable', 'string', 'max:50'],

            'mother_first_name' => ['nullable', 'string', 'max:50'],
            'mother_middle_name' => ['nullable', 'string', 'max:50'],
            'mother_last_name' => ['nullable', 'string', 'max:50'],
            'mother_contact_number' => ['nullable', 'string', 'max:20'],
            'mother_occupation' => ['nullable', 'string', 'max:100'],
            'mother_relationship_status' => ['nullable', 'string', 'max:50'],
            'mother_communication' => ['nullable', 'string', 'max:50'],

            'guardian_first_name' => ['nullable', 'string', 'max:50'],
            'guardian_middle_name' => ['nullable', 'string', 'max:50'],
            'guardian_last_name' => ['nullable', 'string', 'max:50'],
            'guardian_contact_number' => ['nullable', 'string', 'max:20'],
            'guardian_occupation' => ['nullable', 'string', 'max:100'],
            'guardian_relationship_status' => ['nullable', 'string', 'max:50'],
            'guardian_communication' => ['nullable', 'string', 'max:50'],

            'emergency_contact' => ['nullable', 'string', 'max:10'],

            /*
            |--------------------------------------------------------------------------
            | Medical information
            |--------------------------------------------------------------------------
            */
            'allergy_medicine' => ['nullable', 'string', 'max:100'],
            'allergy_pollen' => ['nullable', 'string', 'max:100'],
            'allergy_food' => ['nullable', 'string', 'max:100'],
            'allergy_others' => ['nullable', 'string', 'max:100'],

            'medical_conditions' => ['nullable', 'array'],
            'medical_conditions.*' => ['string', 'max:100'],
            'medical_conditions_others' => ['nullable', 'string', 'max:100'],

            'surgery_date' => ['nullable', 'date', 'before_or_equal:today'],
            'surgery_hospital' => ['nullable', 'string', 'max:100'],
            'surgery_body_part' => ['nullable', 'string', 'max:100'],

            'medication_type' => ['nullable', 'string', 'max:100'],
            'medication_dosage_schedule' => ['nullable', 'string', 'max:100'],

            'chronic_conditions' => ['nullable', 'array'],
            'chronic_conditions.*' => ['string', 'max:100'],
            'cancer_type' => ['nullable', 'string', 'max:100'],
            'chronic_conditions_others' => ['nullable', 'string', 'max:100'],

            'exposed_to_covid' => ['nullable', 'boolean'],
            'pertinent_information' => ['nullable', 'string', 'max:255'],

            /*
            |--------------------------------------------------------------------------
            | Special needs
            |--------------------------------------------------------------------------
            */
            'diagnosis' => ['nullable', 'array'],
            'diagnosis.*' => ['string', 'max:100'],
            'shpcd_subtype' => ['nullable', 'string', 'max:20'],
            'vi_subtype' => ['nullable', 'string', 'max:20'],
            'manifestations' => ['nullable', 'array'],
            'manifestations.*' => ['string', 'max:100'],
            'has_pwd_id' => ['nullable', 'boolean'],

            /*
            |--------------------------------------------------------------------------
            | Honeypot
            |--------------------------------------------------------------------------
            */
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lrn.digits' => 'The LRN must contain exactly 12 digits.',
            'birth_date.before' => 'The birth date must be before today.',
            'current_municipality.required' => 'Please enter the current municipality or city.',
            'current_province.required' => 'Please enter the current province.',
            'website.prohibited' => 'The submission could not be accepted.',
            'lrn.unique' => 'This LRN is already registered.',
        ];
    }

    /**
     * Require at least one complete parent or guardian name.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parentPairs = [
                ['father_first_name', 'father_last_name'],
                ['mother_first_name', 'mother_last_name'],
                ['guardian_first_name', 'guardian_last_name'],
            ];

            foreach ($parentPairs as [$firstName, $lastName]) {
                if (
                    filled($this->input($firstName)) &&
                    filled($this->input($lastName))
                ) {
                    return;
                }
            }

            $validator->errors()->add(
                'guardian_first_name',
                'Please provide both the first and last name of at least one parent or guardian.'
            );
        });
    }
}
