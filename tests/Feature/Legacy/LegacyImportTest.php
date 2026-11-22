<?php

use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Enums\Sex;
use App\Enums\VerificationStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentAddress;
use App\Models\EnrollmentMedical;
use App\Models\EnrollmentParent;
use App\Models\EnrollmentSpecialNeed;
use App\Models\IndigenousGroup;
use App\Models\MotherTongue;
use App\Models\Religion;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Support\Legacy\LegacyImporter;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyTestDatabase;

beforeEach(function () {
    LegacyTestDatabase::configure();
    LegacyTestDatabase::createTables();
    seedLegacyImportFixture();
});

afterEach(function () {
    LegacyTestDatabase::cleanup();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyEnrollmentRow(array $overrides = []): array
{
    return array_merge([
        'fk_full_name_bd' => 'TEST_STUDENT_20150101',
        'ed_grade_level' => '1',
        'ed_lrn' => '136402240059',
        'ed_school_year' => '2026-2027',
        'rl_last_grade_level_completed' => null,
        'rl_last_school_year_completed' => null,
        'rl_school_attended' => null,
        'rl_school_id' => null,
        'pi_psa_bcn' => '',
        'pi_last_name' => 'TEST',
        'pi_first_name' => 'STUDENT',
        'pi_middle_name' => '',
        'pi_extension' => null,
        'pi_birth_date' => '2015-01-01',
        'pi_sex' => 'MALE',
        'pi_place_of_birth' => 'BAGUIO CITY',
        'pi_mother_tongue_id' => 1,
        'pi_religion_id' => 1,
        'pi__attended_early_learning_program_name' => null,
        'pi_learning_classification' => 'GRADED',
        'ac_indigenous_group_id' => 2,
        'ac_4ps_household_number' => null,
        'user_account_id' => null,
        'li_learning_modality' => 'BLENDED (COMBINATION)',
        'verification' => 'VERIFIED',
        'section' => '',
        'created-at' => '2026-06-01 10:00:00',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyAddressRow(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'fk_full_name_bd' => 'TEST_STUDENT_20150101',
        'ca_house_number' => '1',
        'ca_street_name' => 'STREET',
        'ca_barangay' => 'BARANGAY',
        'ca_municipality' => 'BAGUIO CITY',
        'ca_provice' => 'BENGUET',
        'ca_country' => 'PHILIPPINES',
        'ca_zipcode' => 2600,
        'ca_address_status' => 'Rental',
        'pa_house_number' => '1',
        'pa_street_name' => 'STREET',
        'pa_barangay' => 'BARANGAY',
        'pa_municipality' => 'BAGUIO CITY',
        'pa_province' => 'BENGUET',
        'pa_country' => 'PHILIPPINES',
        'pa_zip_code' => 2600,
        'pa_address_status' => 'Owned',
    ], $overrides);
}

/**
 * @return array<string, string>
 */
function legacyPerson(string $prefix, string $first, string $last): array
{
    return [
        $prefix.'_last_name' => $last,
        $prefix.'_first_name' => $first,
        $prefix.'_middle_name' => '',
        $prefix.'_contact_number' => '09171234567',
        $prefix.'_occupation' => 'Teacher',
        $prefix.'_relationship_status' => 'Married',
        $prefix.'_communication' => 'SMS',
    ];
}

function seedLegacyImportFixture(): void
{
    LegacyTestDatabase::insert('mother_tongue', ['id' => 1, 'name' => 'Tagalog']);
    LegacyTestDatabase::insert('mother_tongue', ['id' => 3, 'name' => 'Ibaloi']);
    LegacyTestDatabase::insert('religion', ['id' => 1, 'name' => 'Roman Catholic']);
    LegacyTestDatabase::insert('indigenous_group', ['id' => 2, 'name' => 'Ibaloi']);

    LegacyTestDatabase::insert('user_account', [
        'id' => 1,
        'username' => 'admin',
        'password_hash' => '$2y$10$h2e2UGU9c1mvwhPIhNfuQeWuLbsZ/5UIKiqdlCav9MRQryPsfCJtq',
        'role' => 'ADMIN',
        'email' => 'admin@example.test',
        'created_at' => '2026-01-01 00:00:00',
        'is_active' => 1,
    ]);

    LegacyTestDatabase::insert('user_account', [
        'id' => 2,
        'username' => 'teacher',
        'password_hash' => '$2y$10$NDuPICwUL/aUVZk0QPiIv.XAs7LXEuzfPnT828MBnF5OkQoJuG11G',
        'role' => 'TEACHER',
        'email' => 'teacher@example.test',
        'created_at' => '0000-00-00 00:00:00',
        'is_active' => 0,
    ]);

    LegacyTestDatabase::insert('enrollment2', legacyEnrollmentRow([
        'fk_full_name_bd' => 'JUAN_DELA_CRUZ_20150101',
        'ed_grade_level' => 'Kindergart',
        'ed_school_year' => '2026 _2027',
        'rl_last_grade_level_completed' => 'KINDER',
        'rl_last_school_year_completed' => '2025 2026',
        'rl_school_attended' => 'GIBRALTAR ELEMENTARY SCHOOL',
        'rl_school_id' => '136402',
        'pi_psa_bcn' => '2018 4140436',
        'pi_last_name' => 'DELA CRUZ',
        'pi_first_name' => 'JUAN',
        'pi_middle_name' => 'SANTOS',
        'pi_birth_date' => '2015-01-01',
        'pi_sex' => 'MALE',
        'pi_mother_tongue_id' => 1,
        'pi_religion_id' => 1,
        'pi__attended_early_learning_program_name' => 'DAY CARE',
        'ac_indigenous_group_id' => 2,
        'ac_4ps_household_number' => '#553',
        'user_account_id' => 1,
        'li_learning_modality' => 'BLENDED (COMBINATION),ONLINE',
        'verification' => 'PENDING',
        'section' => 'K-Obedience',
        'created-at' => '2026-06-01 10:00:00',
    ]));

    LegacyTestDatabase::insert('enrollment2', legacyEnrollmentRow([
        'fk_full_name_bd' => 'MARIA_SANTOS_20160202',
        'ed_grade_level' => '3',
        'ed_lrn' => '123',
        'ed_school_year' => '2025_2026',
        'pi_psa_bcn' => 'N/A',
        'pi_last_name' => 'SANTOS',
        'pi_first_name' => 'MARIA',
        'pi_birth_date' => '0000-00-00',
        'pi_sex' => 'FEMALE',
        'pi_place_of_birth' => 'LA TRINIDAD',
        'pi_mother_tongue_id' => 3,
        'pi_learning_classification' => 'NON-GRADED',
        'ac_4ps_household_number' => 'NO',
        'li_learning_modality' => '',
        'verification' => 'VERIFIED',
        'created-at' => '0000-00-00 00:00:00',
    ]));

    LegacyTestDatabase::insert('enrollment2', legacyEnrollmentRow([
        'fk_full_name_bd' => 'BAD_GRADE_20170101',
        'ed_grade_level' => 'Grade 7',
        'pi_last_name' => 'BAD',
        'pi_first_name' => 'GRADE',
    ]));

    LegacyTestDatabase::insert('enrollment2', legacyEnrollmentRow([
        'fk_full_name_bd' => 'PEDRO_REYES_20180303',
        'ed_grade_level' => '1',
        'ed_lrn' => '136402230061',
        'pi_last_name' => 'REYES',
        'pi_first_name' => 'PEDRO',
        'pi_birth_date' => '2018-03-03',
        'verification' => 'TRANSFERRED_OUT',
        'section' => '1-Care',
        'user_account_id' => 2,
        'created-at' => '2026-06-02 09:00:00',
    ]));

    // Duplicate child rows: the newest id (11) must win.
    LegacyTestDatabase::insert('enrollment_address2', legacyAddressRow([
        'id' => 10,
        'fk_full_name_bd' => 'JUAN_DELA_CRUZ_20150101',
        'ca_provice' => 'OLD PROVINCE',
        'ca_zipcode' => 0,
        'pa_province' => 'OLD PROVINCE',
        'pa_zip_code' => 0,
    ]));

    LegacyTestDatabase::insert('enrollment_address2', legacyAddressRow([
        'id' => 11,
        'fk_full_name_bd' => 'JUAN_DELA_CRUZ_20150101',
        'ca_provice' => 'BENGUET',
        'ca_zipcode' => 2600,
        'pa_province' => 'BENGUET',
        'pa_zip_code' => 2600,
    ]));

    LegacyTestDatabase::insert('enrollment_address2', legacyAddressRow([
        'id' => 12,
        'fk_full_name_bd' => 'MARIA_SANTOS_20160202',
    ]));

    LegacyTestDatabase::insert('enrollment_address2', legacyAddressRow([
        'id' => 13,
        'fk_full_name_bd' => 'NOBODY_20000101',
    ]));

    LegacyTestDatabase::insert('enrollment_parent2', array_merge(
        ['id' => 20, 'fk_full_name_bd' => 'JUAN_DELA_CRUZ_20150101', 'ec_to_contact' => 'MOTHER'],
        legacyPerson('fi', 'PEDRO', 'DELA CRUZ'),
        legacyPerson('mi', 'ANA', 'SANTOS'),
        legacyPerson('gi', 'LOLA', 'DELA CRUZ'),
    ));

    LegacyTestDatabase::insert('enrollment_parent2', array_merge(
        ['id' => 21, 'fk_full_name_bd' => 'MARIA_SANTOS_20160202', 'ec_to_contact' => 'FATHER'],
        legacyPerson('fi', 'JOSE', 'SANTOS'),
        legacyPerson('mi', 'ROSA', 'SANTOS'),
        legacyPerson('gi', 'TITA', 'SANTOS'),
    ));

    LegacyTestDatabase::insert('enrollment_medical2', [
        'id' => 30,
        'fk_full_name_bd' => 'JUAN_DELA_CRUZ_20150101',
        'mf_a_medicine' => 'Paracetamol',
        'mf_a_pollen' => '',
        'mf_a_food' => '',
        'mf_a_others' => '',
        'mf_o_medical_conditions' => 'ASTHMA,ANEMIA',
        'mf_o_others' => '',
        'mf_sh_surgery_date' => '0000-00-00',
        'mf_sh_hospital_name' => '',
        'mf_sh_bodypart_affected' => '',
        'mf_tm_type' => '',
        'mf_tm_dosage_schedule' => '',
        'mf_mc_conditions' => '',
        'mf_mc_cancer_type' => '',
        'mf_mc_others' => '',
        'mf_exposure_c_v' => 1,
        'mf_o_pertinent_information' => 'Needs an inhaler',
    ]);

    LegacyTestDatabase::insert('enrollment_medical2', [
        'id' => 31,
        'fk_full_name_bd' => 'MARIA_SANTOS_20160202',
        'mf_a_medicine' => '',
        'mf_a_pollen' => '',
        'mf_a_food' => '',
        'mf_a_others' => '',
        'mf_o_medical_conditions' => '',
        'mf_o_others' => '',
        'mf_sh_surgery_date' => null,
        'mf_sh_hospital_name' => '',
        'mf_sh_bodypart_affected' => '',
        'mf_tm_type' => '',
        'mf_tm_dosage_schedule' => '',
        'mf_mc_conditions' => 'HYPERTENSION',
        'mf_mc_cancer_type' => '',
        'mf_mc_others' => '',
        'mf_exposure_c_v' => 0,
        'mf_o_pertinent_information' => '',
    ]);

    LegacyTestDatabase::insert('enrollment_special_needs2', [
        'id' => 40,
        'fk_full_name_bd' => 'MARIA_SANTOS_20160202',
        'snep_a1_diagnosis' => 'ADHD,LD',
        'snep_a1_sub_shpcd' => 'NONE',
        'snep_a1_sub_vi' => 'NONE',
        'snep_a2_manifestations' => 'NONE',
        'snep_pwd_id' => 1,
    ]);
}

test('the importer creates students, enrollments, details, and reference data', function () {
    $reportPath = storage_path('framework/testing/legacy-import.json');

    $result = (new LegacyImporter)->import(reportPath: $reportPath);

    expect($result->counts['students'])->toBe(['legacy' => 4, 'imported' => 3])
        ->and($result->counts['enrollments'])->toBe(['legacy' => 4, 'imported' => 3])
        ->and($result->counts['mother_tongues'])->toBe(['legacy' => 2, 'imported' => 2])
        ->and($result->counts['religions'])->toBe(['legacy' => 1, 'imported' => 1])
        ->and($result->counts['indigenous_groups'])->toBe(['legacy' => 1, 'imported' => 1])
        ->and($result->counts['users'])->toBe(['legacy' => 2, 'imported' => 2])
        ->and($result->counts['enrollment_addresses'])->toBe(['legacy' => 4, 'imported' => 2])
        ->and($result->counts['enrollment_parents'])->toBe(['legacy' => 2, 'imported' => 2])
        ->and($result->counts['enrollment_medical'])->toBe(['legacy' => 2, 'imported' => 2])
        ->and($result->counts['enrollment_special_needs'])->toBe(['legacy' => 1, 'imported' => 1])
        ->and($result->report->rejects())->toHaveCount(2)
        ->and($result->report->warnings())->toHaveCount(3)
        ->and(file_exists($reportPath))->toBeTrue();

    expect(Student::count())->toBe(3)
        ->and(Enrollment::count())->toBe(3)
        ->and(EnrollmentAddress::count())->toBe(2)
        ->and(EnrollmentParent::count())->toBe(2)
        ->and(EnrollmentMedical::count())->toBe(2)
        ->and(EnrollmentSpecialNeed::count())->toBe(1);

    $juan = Student::query()->where('legacy_key', 'JUAN_DELA_CRUZ_20150101')->firstOrFail();
    $juanEnrollment = Enrollment::query()->where('legacy_key', 'JUAN_DELA_CRUZ_20150101')->firstOrFail();
    $admin = User::query()->where('username', 'admin')->firstOrFail();

    expect($juan->psa_bcn)->toBe('2018 4140436')
        ->and($juan->birth_date?->toDateString())->toBe('2015-01-01')
        ->and($juan->sex)->toBe(Sex::Male)
        ->and($juan->mother_tongue_id)->toBe(1)
        ->and($juan->religion_id)->toBe(1)
        ->and($juan->indigenous_group_id)->toBe(2)
        ->and($juanEnrollment->grade_level)->toBe(GradeLevel::Kindergarten)
        ->and($juanEnrollment->school_year)->toBe('2026-2027')
        ->and($juanEnrollment->section?->name)->toBe('K-Obedience')
        ->and($juanEnrollment->lrn)->toBe('136402240059')
        ->and($juanEnrollment->four_ps_household_number)->toBe('553')
        ->and($juanEnrollment->learning_modality)->toBe(['BLENDED (COMBINATION)', 'ONLINE'])
        ->and($juanEnrollment->verification)->toBe(VerificationStatus::Processing)
        ->and($juanEnrollment->encoded_by)->toBe($admin->id)
        ->and($juanEnrollment->last_school_year_completed)->toBe('2025-2026')
        ->and($juanEnrollment->created_at?->toDateTimeString())->toBe('2026-06-01 10:00:00');

    $maria = Student::query()->where('legacy_key', 'MARIA_SANTOS_20160202')->firstOrFail();
    $mariaEnrollment = Enrollment::query()->where('legacy_key', 'MARIA_SANTOS_20160202')->firstOrFail();

    expect($maria->birth_date)->toBeNull()
        ->and($maria->psa_bcn)->toBeNull()
        ->and($mariaEnrollment->grade_level)->toBe(GradeLevel::Grade3)
        ->and($mariaEnrollment->school_year)->toBe('2025-2026')
        ->and($mariaEnrollment->section_id)->toBeNull()
        ->and($mariaEnrollment->four_ps_household_number)->toBeNull()
        ->and($mariaEnrollment->learning_modality)->toBe([])
        ->and($mariaEnrollment->verification)->toBe(VerificationStatus::Verified);

    $pedroEnrollment = Enrollment::query()->where('legacy_key', 'PEDRO_REYES_20180303')->firstOrFail();
    $teacher = User::query()->where('username', 'teacher')->firstOrFail();

    expect($pedroEnrollment->grade_level)->toBe(GradeLevel::Grade1)
        ->and($pedroEnrollment->section?->name)->toBe('1-Care')
        ->and($pedroEnrollment->verification)->toBe(VerificationStatus::TransferredOut)
        ->and($pedroEnrollment->encoded_by)->toBe($teacher->id);

    $juanAddress = EnrollmentAddress::query()->where('enrollment_id', $juanEnrollment->id)->firstOrFail();

    expect($juanAddress->current_province)->toBe('BENGUET')
        ->and($juanAddress->current_zip_code)->toBe('2600')
        ->and($juanAddress->permanent_province)->toBe('BENGUET');

    $juanParents = EnrollmentParent::query()->where('enrollment_id', $juanEnrollment->id)->firstOrFail();
    $juanMedical = EnrollmentMedical::query()->where('enrollment_id', $juanEnrollment->id)->firstOrFail();

    expect($juanParents->emergency_contact)->toBe('MOTHER')
        ->and($juanParents->father_last_name)->toBe('DELA CRUZ')
        ->and($juanMedical->medical_conditions)->toBe(['ASTHMA', 'ANEMIA'])
        ->and($juanMedical->chronic_conditions)->toBe([])
        ->and($juanMedical->surgery_date)->toBeNull()
        ->and($juanMedical->exposed_to_covid)->toBeTrue();

    $mariaSpecial = EnrollmentSpecialNeed::query()->where('enrollment_id', $mariaEnrollment->id)->firstOrFail();

    expect($mariaSpecial->diagnosis)->toBe(['ADHD', 'LD'])
        ->and($mariaSpecial->has_pwd_id)->toBeTrue();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->password)->toBe('$2y$10$h2e2UGU9c1mvwhPIhNfuQeWuLbsZ/5UIKiqdlCav9MRQryPsfCJtq')
        ->and($teacher->is_active)->toBeFalse();

    expect(MotherTongue::count())->toBe(2)
        ->and(MotherTongue::find(3)?->name)->toBe('Ibaloi')
        ->and(Religion::count())->toBe(1)
        ->and(IndigenousGroup::count())->toBe(1)
        ->and(Section::query()->where('school_year', '2026-2027')->count())->toBe(17)
        ->and(Section::query()->where('school_year', '2025-2026')->count())->toBe(17);

    $report = json_decode((string) file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);

    expect($report['rejects'])->toHaveCount(2)
        ->and(collect($report['rejects'])->pluck('key')->all())->toContain('BAD_GRADE_20170101', '13')
        ->and($report['warnings'])->toHaveCount(3);

    @unlink($reportPath);
});

test('running the import twice keeps identical row counts', function () {
    $importer = new LegacyImporter;
    $first = $importer->import(reportPath: storage_path('framework/testing/legacy-first.json'));
    $counts = [
        Student::count(),
        Enrollment::count(),
        EnrollmentAddress::count(),
        EnrollmentParent::count(),
        EnrollmentMedical::count(),
        EnrollmentSpecialNeed::count(),
        MotherTongue::count(),
    ];

    $second = $importer->import(reportPath: storage_path('framework/testing/legacy-second.json'));

    expect($second->counts)->toBe($first->counts)
        ->and([
            Student::count(),
            Enrollment::count(),
            EnrollmentAddress::count(),
            EnrollmentParent::count(),
            EnrollmentMedical::count(),
            EnrollmentSpecialNeed::count(),
            MotherTongue::count(),
        ])->toBe($counts);

    @unlink(storage_path('framework/testing/legacy-first.json'));
    @unlink(storage_path('framework/testing/legacy-second.json'));
});

test('a dry run rolls everything back but still writes the report', function () {
    $reportPath = storage_path('framework/testing/legacy-dry-run.json');

    $result = (new LegacyImporter)->import(dryRun: true, reportPath: $reportPath);

    expect($result->dryRun)->toBeTrue()
        ->and($result->counts['students'])->toBe(['legacy' => 4, 'imported' => 3])
        ->and(Student::count())->toBe(0)
        ->and(Enrollment::count())->toBe(0)
        ->and(User::count())->toBe(0)
        ->and(file_exists($reportPath))->toBeTrue();

    @unlink($reportPath);
});

test('the artisan command imports and can be re-run', function () {
    $reportPath = storage_path('framework/testing/legacy-command.json');

    $this->artisan('ams:import-legacy', ['--report' => $reportPath])
        ->assertSuccessful();

    expect(Student::count())->toBe(3)
        ->and(Enrollment::count())->toBe(3)
        ->and(file_exists($reportPath))->toBeTrue();

    $this->artisan('ams:import-legacy', ['--report' => $reportPath])
        ->assertSuccessful();

    expect(Student::count())->toBe(3)
        ->and(Enrollment::count())->toBe(3);

    @unlink($reportPath);
});

test('the artisan command fails when the legacy database is not loaded', function () {
    DB::purge('legacy');

    config([
        'database.connections.legacy' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    $this->artisan('ams:import-legacy')
        ->assertFailed();
});
