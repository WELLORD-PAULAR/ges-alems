<?php

namespace App\Http\Controllers;

use App\Enums\GradeLevel;
use App\Enums\Sex;
use App\Enums\VerificationStatus;
use App\Http\Requests\Enrollment\StoreEnrollmentRequest;
use App\Models\IndigenousGroup;
use App\Models\MotherTongue;
use App\Models\Religion;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('enrollment/Create', [
            'gradeLevels' => GradeLevel::options(),
            'sexOptions' => Sex::options(),
            'lookups' => [
                'motherTongues' => MotherTongue::query()
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'religions' => Religion::query()
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'indigenousGroups' => IndigenousGroup::query()
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }

    public function store(StoreEnrollmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $student = Student::create(Arr::only($data, [
                'first_name',
                'middle_name',
                'last_name',
                'extension',
                'birth_date',
                'sex',
                'place_of_birth',
                'mother_tongue_id',
                'religion_id',
                'indigenous_group_id',
            ]));

            $enrollment = $student->enrollments()->create([
                ...Arr::only($data, [
                    'grade_level',
                    'lrn',
                    'learning_classification',
                    'last_grade_level_completed',
                    'last_school_year_completed',
                    'last_school_attended',
                    'last_school_id',
                    'attended_early_learning_program_name',
                    'four_ps_household_number',
                    'learning_modality',
                ]),
                'school_year' => config('school.current_school_year'),
                'verification' => VerificationStatus::Processing,
                'section_id' => null,
                'encoded_by' => null,
            ]);

            $enrollment->address()->create(Arr::only($data, [
                'current_house_number',
                'current_street_name',
                'current_barangay',
                'current_municipality',
                'current_province',
                'current_country',
                'current_zip_code',
                'current_address_status',
                'permanent_house_number',
                'permanent_street_name',
                'permanent_barangay',
                'permanent_municipality',
                'permanent_province',
                'permanent_country',
                'permanent_zip_code',
                'permanent_address_status',
            ]));

            $enrollment->parents()->create(Arr::only($data, [
                'father_first_name',
                'father_middle_name',
                'father_last_name',
                'father_contact_number',
                'father_occupation',
                'father_relationship_status',
                'father_communication',
                'mother_first_name',
                'mother_middle_name',
                'mother_last_name',
                'mother_contact_number',
                'mother_occupation',
                'mother_relationship_status',
                'mother_communication',
                'guardian_first_name',
                'guardian_middle_name',
                'guardian_last_name',
                'guardian_contact_number',
                'guardian_occupation',
                'guardian_relationship_status',
                'guardian_communication',
                'emergency_contact',
            ]));

            $enrollment->medical()->create([
                ...Arr::only($data, [
                    'allergy_medicine',
                    'allergy_pollen',
                    'allergy_food',
                    'allergy_others',
                    'medical_conditions',
                    'medical_conditions_others',
                    'surgery_date',
                    'surgery_hospital',
                    'surgery_body_part',
                    'medication_type',
                    'medication_dosage_schedule',
                    'chronic_conditions',
                    'cancer_type',
                    'chronic_conditions_others',
                    'pertinent_information',
                ]),
                'exposed_to_covid' => $data['exposed_to_covid'] ?? false,
            ]);

            $enrollment->specialNeeds()->create([
                ...Arr::only($data, [
                    'diagnosis',
                    'shpcd_subtype',
                    'vi_subtype',
                    'manifestations',
                ]),
                'has_pwd_id' => $data['has_pwd_id'] ?? false,
            ]);
        });

        return to_route('enrollment.success');
    }

    public function success(): Response
    {
        return Inertia::render('enrollment/Success');
    }
}
