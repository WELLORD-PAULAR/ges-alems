<?php

use App\Enums\VerificationStatus;
use App\Models\Enrollment;
use App\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\assertDatabaseCount;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function enrollmentPayload(array $overrides = []): array
{
    return array_merge([
        'grade_level' => '3',
        'lrn' => '123456789012',

        'first_name' => 'Juan',
        'middle_name' => 'Dela Cruz',
        'last_name' => 'Santos',
        'birth_date' => '2010-05-15',
        'sex' => 'MALE',
        'place_of_birth' => 'Baguio City',

        'current_house_number' => '12',
        'current_street_name' => 'Sample Street',
        'current_barangay' => 'Gibraltar',
        'current_municipality' => 'Baguio City',
        'current_province' => 'Benguet',
        'current_country' => 'Philippines',

        'father_first_name' => 'Pedro',
        'father_last_name' => 'Santos',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Public access
|--------------------------------------------------------------------------
*/

test('guests can view the enrollment form', function () {
    $this->get(route('enrollment.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('enrollment/Create')
            ->has('gradeLevels')
            ->has('sexOptions'));
});

test('guests can view the enrollment success page', function () {
    $this->get(route('enrollment.success'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('enrollment/Success'));
});

/*
|--------------------------------------------------------------------------
| Submission
|--------------------------------------------------------------------------
*/

test('a valid public enrollment creates the student and all detail records', function () {
    $this->post(route('enrollment.store'), enrollmentPayload())
        ->assertRedirect(route('enrollment.success'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('students', 1);
    $this->assertDatabaseCount('enrollments', 1);
    $this->assertDatabaseCount('enrollment_addresses', 1);
    $this->assertDatabaseCount('enrollment_parents', 1);
    $this->assertDatabaseCount('enrollment_medical', 1);
    $this->assertDatabaseCount('enrollment_special_needs', 1);

    $student = Student::query()->firstOrFail();
    $enrollment = Enrollment::query()->firstOrFail();

    expect($student->first_name)->toBe('Juan')
        ->and($student->last_name)->toBe('Santos')
        ->and($enrollment->student_id)->toBe($student->id)
        ->and($enrollment->school_year)->toBe(config('school.current_school_year'))
        ->and($enrollment->verification)->toBe(VerificationStatus::Processing)
        ->and($enrollment->section_id)->toBeNull()
        ->and($enrollment->encoded_by)->toBeNull();
});

test('server controlled enrollment values cannot be overridden by submitted data', function () {
    $this->post(route('enrollment.store'), enrollmentPayload([
        'school_year' => '1900-1901',
        'verification' => 'VERIFIED',
        'section_id' => 999,
        'encoded_by' => 999,
    ]))
        ->assertRedirect(route('enrollment.success'))
        ->assertSessionHasNoErrors();

    $enrollment = Enrollment::query()->firstOrFail();

    expect($enrollment->school_year)->toBe(config('school.current_school_year'))
        ->and($enrollment->verification)->toBe(VerificationStatus::Processing)
        ->and($enrollment->section_id)->toBeNull()
        ->and($enrollment->encoded_by)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Required fields
|--------------------------------------------------------------------------
*/

test('required enrollment and personal fields are validated', function () {
    $this->post(route('enrollment.store'), [])
        ->assertSessionHasErrors([
            'grade_level',
            'lrn',
            'first_name',
            'last_name',
            'birth_date',
            'sex',
            'current_municipality',
            'current_province',
            'guardian_first_name',
        ]);

    expect(Student::query()->count())->toBe(0)
        ->and(Enrollment::query()->count())->toBe(0);
});

test('an invalid lrn is rejected', function (string $lrn) {
    $this->post(route('enrollment.store'), enrollmentPayload([
        'lrn' => $lrn,
    ]))
        ->assertSessionHasErrors('lrn');

    expect(Student::query()->count())->toBe(0);
})->with([
    'too short' => '12345678901',
    'too long' => '1234567890123',
    'letters' => '12345678901A',
]);

test('a duplicate lrn is rejected', function () {
    $this->post(route('enrollment.store'), enrollmentPayload())
        ->assertSessionHasNoErrors();

    $this->post(route('enrollment.store'), enrollmentPayload([
        'first_name' => 'Maria',
        'lrn' => '123456789012',
    ]))
        ->assertSessionHasErrors('lrn');

    expect(Student::query()->count())->toBe(1)
        ->and(Enrollment::query()->count())->toBe(1);
});

test('a future birth date is rejected', function () {
    $this->post(route('enrollment.store'), enrollmentPayload([
        'birth_date' => now()->addDay()->toDateString(),
    ]))
        ->assertSessionHasErrors('birth_date');

    expect(Student::query()->count())->toBe(0);
});

test('a complete parent or guardian name is required', function () {
    $this->post(route('enrollment.store'), enrollmentPayload([
        'father_first_name' => 'Pedro',
        'father_last_name' => null,
        'mother_first_name' => null,
        'mother_last_name' => null,
        'guardian_first_name' => null,
        'guardian_last_name' => null,
    ]))
        ->assertSessionHasErrors('guardian_first_name');

    expect(Student::query()->count())->toBe(0);
});

test('the honeypot field must remain empty', function () {
    $this->post(route('enrollment.store'), enrollmentPayload([
        'website' => 'https://spam.example',
    ]))
        ->assertSessionHasErrors('website');

    expect(Student::query()->count())->toBe(0);
});


/*
|--------------------------------------------------------------------------
| Medical and special needs persistence
|--------------------------------------------------------------------------
*/

test('medical and special needs information is saved correctly', function () {
    $this->post(route('enrollment.store'), enrollmentPayload([
        // Section 5: Medical information
        'allergy_medicine' => 'Penicillin',
        'allergy_pollen' => 'Grass pollen',
        'allergy_food' => 'Peanuts',
        'allergy_others' => 'Dust',

        'medical_conditions' => ['ASTHMA', 'OTHER'],
        'medical_conditions_others' => 'Seasonal respiratory condition',

        'surgery_date' => '2020-03-01',
        'surgery_hospital' => 'Baguio General Hospital',
        'surgery_body_part' => 'Left arm',

        'medication_type' => 'Inhaler',
        'medication_dosage_schedule' => 'Twice daily',

        'chronic_conditions' => ['DIABETES'],
        'cancer_type' => 'Not applicable',
        'chronic_conditions_others' => 'Additional chronic condition',

        'exposed_to_covid' => '1',
        'pertinent_information' => 'Additional medical information',

        // Section 6: Special needs information
        'diagnosis' => ['ADHD', 'LD'],
        'shpcd_subtype' => 'NONE',
        'vi_subtype' => 'NONE',
        'manifestations' => ['Difficulty concentrating'],
        'has_pwd_id' => '1',
    ]))
        ->assertRedirect(route('enrollment.success'))
        ->assertSessionHasNoErrors();

    $enrollment = Enrollment::query()->firstOrFail();

    $medical = \App\Models\EnrollmentMedical::query()
        ->where('enrollment_id', $enrollment->id)
        ->firstOrFail();

    $specialNeeds = \App\Models\EnrollmentSpecialNeed::query()
        ->where('enrollment_id', $enrollment->id)
        ->firstOrFail();

    // Verify Section 5: Medical information
    expect($medical->allergy_medicine)->toBe('Penicillin')
        ->and($medical->allergy_pollen)->toBe('Grass pollen')
        ->and($medical->allergy_food)->toBe('Peanuts')
        ->and($medical->allergy_others)->toBe('Dust')
        ->and($medical->medical_conditions)->toBe(['ASTHMA', 'OTHER'])
        ->and($medical->medical_conditions_others)->toBe('Seasonal respiratory condition')
        ->and($medical->surgery_date->toDateString())->toBe('2020-03-01')
        ->and($medical->surgery_hospital)->toBe('Baguio General Hospital')
        ->and($medical->surgery_body_part)->toBe('Left arm')
        ->and($medical->medication_type)->toBe('Inhaler')
        ->and($medical->medication_dosage_schedule)->toBe('Twice daily')
        ->and($medical->chronic_conditions)->toBe(['DIABETES'])
        ->and($medical->cancer_type)->toBe('Not applicable')
        ->and($medical->chronic_conditions_others)->toBe('Additional chronic condition')
        ->and($medical->exposed_to_covid)->toBeTrue()
        ->and($medical->pertinent_information)->toBe('Additional medical information');

    // Verify Section 6: Special needs information
    expect($specialNeeds->diagnosis)->toBe(['ADHD', 'LD'])
        ->and($specialNeeds->shpcd_subtype)->toBe('NONE')
        ->and($specialNeeds->vi_subtype)->toBe('NONE')
        ->and($specialNeeds->manifestations)->toBe(['Difficulty concentrating'])
        ->and($specialNeeds->has_pwd_id)->toBeTrue();
});


/*
|--------------------------------------------------------------------------
| Enrollment transaction rollback
|--------------------------------------------------------------------------
*/

test('the entire enrollment is rolled back when saving special needs fails', function () {
    DB::statement(<<<'SQL'
        CREATE TRIGGER fail_enrollment_special_needs_insert
        BEFORE INSERT ON enrollment_special_needs
        BEGIN
            SELECT RAISE(ABORT, 'Simulated special needs insert failure');
        END;
    SQL);

    expect(fn () => $this
        ->withoutExceptionHandling()
        ->post(route('enrollment.store'), enrollmentPayload())
    )->toThrow(\Illuminate\Database\QueryException::class);

    expect(Student::query()->count())->toBe(0)
        ->and(Enrollment::query()->count())->toBe(0)
        ->and(DB::table('enrollment_addresses')->count())->toBe(0)
        ->and(DB::table('enrollment_parents')->count())->toBe(0)
        ->and(DB::table('enrollment_medical')->count())->toBe(0)
        ->and(DB::table('enrollment_special_needs')->count())->toBe(0);
});
