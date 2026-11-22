<?php

namespace App\Support\Legacy;

use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Enums\VerificationStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentAddress;
use App\Models\EnrollmentMedical;
use App\Models\EnrollmentParent;
use App\Models\EnrollmentSpecialNeed;
use App\Models\Section;
use App\Models\Student;
use App\Support\SchoolYear;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Imports the old AMS database into the new schema.
 *
 * Every step is keyed by `legacy_key` (or by the old lookup id), so running the
 * command twice updates the same rows instead of duplicating them. Child rows
 * are keyed by `enrollment_id`; when the old database has duplicates the newest
 * row wins, matching the old PDF code's `ORDER BY id DESC`.
 */
final class LegacyImporter
{
    public function import(bool $dryRun = false, ?string $reportPath = null): LegacyImportResult
    {
        $report = new LegacyImportReport;
        $connection = DB::connection();
        $connection->beginTransaction();

        try {
            $counts = [];
            $counts['mother_tongues'] = $this->importLookup('mother_tongue', 'mother_tongues', $report);
            $counts['religions'] = $this->importLookup('religion', 'religions', $report);
            $counts['indigenous_groups'] = $this->importLookup('indigenous_group', 'indigenous_groups', $report);
            $counts['users'] = $this->importUsers($report);
            $counts['sections'] = $this->seedSections($report);
            $counts += $this->importStudentsAndEnrollments($report);
            $counts += $this->importEnrollmentDetails($report);

            if ($dryRun) {
                $connection->rollBack();
            } else {
                $connection->commit();
            }
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $reportPath ??= storage_path('app/imports/legacy-import-'.now()->format('Ymd-His').'.json');
        $report->write($reportPath);

        return new LegacyImportResult($counts, $report, $reportPath, $dryRun);
    }

    /**
     * @return array{legacy: int, imported: int}
     */
    private function importLookup(string $legacyTable, string $targetTable, LegacyImportReport $report): array
    {
        $rows = DB::connection('legacy')->table($legacyTable)->orderBy('id')->get();
        $imported = 0;

        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $row = (array) $row;
            $id = $this->legacyId($row);
            $name = LegacyRowCleaner::text($row['name'] ?? null);

            if ($id === null || $name === null) {
                $report->reject($legacyTable, $id ?? '(missing id)', 'Missing id or name');

                continue;
            }

            try {
                $exists = DB::table($targetTable)->where('id', $id)->exists();
                $values = [
                    'name' => $name,
                    'legacy_key' => (string) $id,
                    'updated_at' => now(),
                ];

                if (! $exists) {
                    $values['created_at'] = now();
                }

                DB::table($targetTable)->updateOrInsert(['id' => $id], $values);
                $imported++;
            } catch (QueryException $exception) {
                $report->reject($legacyTable, $id, 'Lookup could not be imported: '.$exception->getMessage());
            }
        }

        return ['legacy' => $rows->count(), 'imported' => $imported];
    }

    /**
     * @return array{legacy: int, imported: int}
     */
    private function importUsers(LegacyImportReport $report): array
    {
        $rows = DB::connection('legacy')->table('user_account')->orderBy('id')->get();
        $imported = 0;

        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $row = (array) $row;
            $id = $this->legacyId($row);
            $username = LegacyRowCleaner::name($row['username'] ?? null);
            $email = LegacyRowCleaner::text($row['email'] ?? null);
            $password = LegacyRowCleaner::text($row['password_hash'] ?? null);

            if ($id === null || $username === null || $email === null || $password === null) {
                $report->reject('user_account', $id ?? '(missing id)', 'Missing username, email, or password hash');

                continue;
            }

            $legacyRole = strtoupper((string) ($row['role'] ?? ''));
            $role = $legacyRole === 'ADMIN' ? Role::Admin : Role::Teacher;

            if ($legacyRole === 'PARENT') {
                $report->warn('user_account', $id, 'PARENT role mapped to Teacher (decision D5)');
            }

            $createdAt = LegacyRowCleaner::dateTime($row['created_at'] ?? null) ?? now();
            $values = [
                'name' => $username,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => $role->value,
                'is_active' => LegacyRowCleaner::boolean($row['is_active'] ?? 0),
                'email_verified_at' => now(),
                'updated_at' => now(),
            ];

            try {
                $existing = DB::table('users')->where('legacy_key', (string) $id)->first();

                if ($existing !== null) {
                    /** @var array<string, mixed> $existing */
                    $existing = (array) $existing;
                    DB::table('users')->where('id', (int) ($existing['id'] ?? 0))->update($values);
                } else {
                    $values['legacy_key'] = (string) $id;
                    $values['created_at'] = $createdAt;
                    DB::table('users')->insert($values);
                }

                $imported++;
            } catch (QueryException $exception) {
                $report->reject('user_account', $id, 'User could not be imported: '.$exception->getMessage());
            }
        }

        return ['legacy' => $rows->count(), 'imported' => $imported];
    }

    /**
     * Seed the section list from `config('school.sections')` for every school
     * year found in the old data, plus the current year.
     *
     * @return array{legacy: int, imported: int}
     */
    private function seedSections(LegacyImportReport $report): array
    {
        $years = DB::connection('legacy')
            ->table('enrollment2')
            ->pluck('ed_school_year')
            ->map(fn (mixed $value): ?string => SchoolYear::normalize(is_string($value) ? $value : null))
            ->filter(fn (?string $value): bool => $value !== null)
            ->push(SchoolYear::current())
            ->unique()
            ->values();

        $groups = config('school.sections');

        if (! is_array($groups)) {
            throw new \RuntimeException('config/school.php must define a sections array.');
        }

        $imported = 0;

        foreach ($years as $year) {
            foreach ($groups as $grade => $names) {
                $gradeLevel = GradeLevel::tryFrom((string) $grade);

                if ($gradeLevel === null || ! is_array($names)) {
                    $report->warn('sections', (string) $grade, 'Unknown grade in config/school.php');

                    continue;
                }

                foreach ($names as $name) {
                    if (! is_string($name)) {
                        continue;
                    }

                    Section::updateOrCreate(
                        ['name' => $name, 'school_year' => $year],
                        ['grade_level' => $gradeLevel],
                    );

                    $imported++;
                }
            }
        }

        return ['legacy' => 0, 'imported' => $imported];
    }

    /**
     * @return array{students: array{legacy: int, imported: int}, enrollments: array{legacy: int, imported: int}}
     */
    private function importStudentsAndEnrollments(LegacyImportReport $report): array
    {
        $rows = DB::connection('legacy')->table('enrollment2')->orderBy('fk_full_name_bd')->get();

        /** @var array<array-key, int> $userMap */
        $userMap = array_map('intval', DB::table('users')->whereNotNull('legacy_key')->pluck('id', 'legacy_key')->all());
        /** @var list<int> $motherTongueIds */
        $motherTongueIds = array_map('intval', DB::table('mother_tongues')->pluck('id')->all());
        /** @var list<int> $religionIds */
        $religionIds = array_map('intval', DB::table('religions')->pluck('id')->all());
        /** @var list<int> $indigenousGroupIds */
        $indigenousGroupIds = array_map('intval', DB::table('indigenous_groups')->pluck('id')->all());

        /** @var array<string, int> $sectionMap */
        $sectionMap = [];

        foreach (DB::table('sections')->get() as $section) {
            /** @var array<string, mixed> $section */
            $section = (array) $section;
            $sectionMap[((string) ($section['school_year'] ?? '')).'|'.((string) ($section['name'] ?? ''))] = (int) ($section['id'] ?? 0);
        }

        $students = 0;
        $enrollments = 0;

        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $row = (array) $row;
            $key = LegacyRowCleaner::text($row['fk_full_name_bd'] ?? null);
            $grade = GradeLevel::fromLegacy(LegacyRowCleaner::text($row['ed_grade_level'] ?? null));
            $schoolYear = SchoolYear::normalize(LegacyRowCleaner::text($row['ed_school_year'] ?? null));
            $birthDate = LegacyRowCleaner::date($row['pi_birth_date'] ?? null);
            $lastName = LegacyRowCleaner::name($row['pi_last_name'] ?? null);
            $firstName = LegacyRowCleaner::name($row['pi_first_name'] ?? null);

            if ($key === null || $grade === null || $schoolYear === null || $lastName === null || $firstName === null) {
                $report->reject(
                    'enrollment2',
                    $key ?? '(missing key)',
                    'Missing or invalid student key, grade, school year, or name',
                );

                continue;
            }

            if ($birthDate === null) {
                $report->warn('enrollment2', $key, 'Birth date is empty or 0000-00-00; stored as null');
            }

            $sex = LegacyRowCleaner::sex($row['pi_sex'] ?? null);

            if ($sex === null) {
                $report->warn('enrollment2', $key, 'Unknown sex; stored as null');
            }

            $motherTongueId = $this->lookupId($row['pi_mother_tongue_id'] ?? null, $motherTongueIds);
            $religionId = $this->lookupId($row['pi_religion_id'] ?? null, $religionIds);
            $indigenousGroupId = $this->lookupId($row['ac_indigenous_group_id'] ?? null, $indigenousGroupIds);

            if ($motherTongueId === null) {
                $report->warn('enrollment2', $key, 'Unknown mother tongue id; stored as null');
            }

            if ($religionId === null) {
                $report->warn('enrollment2', $key, 'Unknown religion id; stored as null');
            }

            if ($indigenousGroupId === null) {
                $report->warn('enrollment2', $key, 'Unknown indigenous group id; stored as null');
            }

            $student = Student::updateOrCreate(
                ['legacy_key' => $key],
                [
                    'psa_bcn' => LegacyRowCleaner::psaBcn($row['pi_psa_bcn'] ?? null),
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'middle_name' => LegacyRowCleaner::name($row['pi_middle_name'] ?? null),
                    'extension' => LegacyRowCleaner::text($row['pi_extension'] ?? null),
                    'birth_date' => $birthDate,
                    'sex' => $sex,
                    'place_of_birth' => LegacyRowCleaner::text($row['pi_place_of_birth'] ?? null),
                    'mother_tongue_id' => $motherTongueId,
                    'religion_id' => $religionId,
                    'indigenous_group_id' => $indigenousGroupId,
                ],
            );

            $students++;

            $lrn = LegacyRowCleaner::lrn($row['ed_lrn'] ?? null);

            if ($lrn !== null && strlen($lrn) !== 12) {
                $report->warn('enrollment2', $key, "LRN is not 12 digits ({$lrn})");
            }

            $sectionName = LegacyRowCleaner::text($row['section'] ?? null);
            $sectionId = null;

            if ($sectionName !== null) {
                $sectionId = $sectionMap[$schoolYear.'|'.$sectionName] ?? null;

                if ($sectionId === null) {
                    $report->warn('enrollment2', $key, "Unknown section '{$sectionName}' for {$schoolYear}");
                }
            }

            $encodedBy = null;
            $legacyUserId = $row['user_account_id'] ?? null;

            if ($legacyUserId !== null && $legacyUserId !== '') {
                $encodedBy = $userMap[(int) $legacyUserId] ?? null;

                if ($encodedBy === null) {
                    $report->warn('enrollment2', $key, "Unknown user_account_id {$legacyUserId}; encoded_by left null");
                }
            }

            $classification = LegacyRowCleaner::text($row['pi_learning_classification'] ?? null);

            if ($classification !== null && ! in_array(strtoupper($classification), ['GRADED', 'NON-GRADED'], true)) {
                $report->warn('enrollment2', $key, "Unknown learning classification '{$classification}'; stored as null");
                $classification = null;
            }

            $enrollment = Enrollment::updateOrCreate(
                ['legacy_key' => $key],
                [
                    'student_id' => $student->id,
                    'school_year' => $schoolYear,
                    'grade_level' => $grade,
                    'section_id' => $sectionId,
                    'lrn' => $lrn,
                    'learning_classification' => $classification,
                    'last_grade_level_completed' => LegacyRowCleaner::text($row['rl_last_grade_level_completed'] ?? null),
                    'last_school_year_completed' => SchoolYear::normalize(LegacyRowCleaner::text($row['rl_last_school_year_completed'] ?? null)),
                    'last_school_attended' => LegacyRowCleaner::text($row['rl_school_attended'] ?? null),
                    'last_school_id' => LegacyRowCleaner::text($row['rl_school_id'] ?? null),
                    'attended_early_learning_program_name' => LegacyRowCleaner::text($row['pi__attended_early_learning_program_name'] ?? null),
                    'four_ps_household_number' => LegacyRowCleaner::fourPsHouseholdNumber($row['ac_4ps_household_number'] ?? null),
                    'learning_modality' => LegacyRowCleaner::set($row['li_learning_modality'] ?? null),
                    'verification' => VerificationStatus::normalize(LegacyRowCleaner::text($row['verification'] ?? null))->value,
                    'encoded_by' => $encodedBy,
                ],
            );

            $enrollments++;

            $legacyTimestamp = LegacyRowCleaner::dateTime($row['created-at'] ?? null);

            if ($legacyTimestamp !== null) {
                DB::table('enrollments')->where('id', $enrollment->id)->update([
                    'created_at' => $legacyTimestamp,
                    'updated_at' => $legacyTimestamp,
                ]);

                DB::table('students')->where('id', $student->id)->update([
                    'created_at' => $legacyTimestamp,
                    'updated_at' => $legacyTimestamp,
                ]);
            }
        }

        return [
            'students' => ['legacy' => $rows->count(), 'imported' => $students],
            'enrollments' => ['legacy' => $rows->count(), 'imported' => $enrollments],
        ];
    }

    /**
     * @return array<string, array{legacy: int, imported: int}>
     */
    private function importEnrollmentDetails(LegacyImportReport $report): array
    {
        /** @var array<string, int> $enrollmentMap */
        $enrollmentMap = array_map('intval', Enrollment::query()->whereNotNull('legacy_key')->pluck('id', 'legacy_key')->all());

        return [
            'enrollment_addresses' => $this->importChildRecords(
                'enrollment_address2',
                $enrollmentMap,
                $report,
                function (int $enrollmentId, array $row): void {
                    EnrollmentAddress::updateOrCreate(
                        ['enrollment_id' => $enrollmentId],
                        $this->addressValues($enrollmentId, $row),
                    );
                },
            ),
            'enrollment_parents' => $this->importChildRecords(
                'enrollment_parent2',
                $enrollmentMap,
                $report,
                function (int $enrollmentId, array $row): void {
                    EnrollmentParent::updateOrCreate(
                        ['enrollment_id' => $enrollmentId],
                        $this->parentValues($enrollmentId, $row),
                    );
                },
            ),
            'enrollment_medical' => $this->importChildRecords(
                'enrollment_medical2',
                $enrollmentMap,
                $report,
                function (int $enrollmentId, array $row): void {
                    EnrollmentMedical::updateOrCreate(
                        ['enrollment_id' => $enrollmentId],
                        $this->medicalValues($enrollmentId, $row),
                    );
                },
            ),
            'enrollment_special_needs' => $this->importChildRecords(
                'enrollment_special_needs2',
                $enrollmentMap,
                $report,
                function (int $enrollmentId, array $row): void {
                    EnrollmentSpecialNeed::updateOrCreate(
                        ['enrollment_id' => $enrollmentId],
                        $this->specialNeedsValues($enrollmentId, $row),
                    );
                },
            ),
        ];
    }

    /**
     * @param  array<string, int>  $enrollmentMap
     * @param  callable(int, array<string, mixed>): void  $upsert
     * @return array{legacy: int, imported: int}
     */
    private function importChildRecords(
        string $legacyTable,
        array $enrollmentMap,
        LegacyImportReport $report,
        callable $upsert,
    ): array {
        $rows = DB::connection('legacy')->table($legacyTable)->orderBy('id')->get();
        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $row = (array) $row;
            $key = LegacyRowCleaner::text($row['fk_full_name_bd'] ?? null);

            if ($key === null) {
                $report->reject($legacyTable, $this->legacyId($row) ?? '(missing id)', 'Missing student key');

                continue;
            }

            $grouped[$key][] = $row;
        }

        $imported = 0;

        foreach ($grouped as $key => $group) {
            usort(
                $group,
                fn (array $left, array $right): int => ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0)),
            );

            $newest = $group[0];

            foreach (array_slice($group, 1) as $duplicate) {
                $report->warn(
                    $legacyTable,
                    $this->legacyId($duplicate) ?? '(missing id)',
                    "Duplicate child row for {$key}; kept newest id ".($this->legacyId($newest) ?? '(missing)'),
                );
            }

            $enrollmentId = $enrollmentMap[$key] ?? null;

            if ($enrollmentId === null) {
                $report->reject(
                    $legacyTable,
                    $this->legacyId($newest) ?? '(missing id)',
                    "No enrollment found for {$key}",
                );

                continue;
            }

            try {
                $upsert($enrollmentId, $newest);
                $imported++;
            } catch (QueryException $exception) {
                $report->reject(
                    $legacyTable,
                    $this->legacyId($newest) ?? '(missing id)',
                    'Row could not be imported: '.$exception->getMessage(),
                );
            }
        }

        return ['legacy' => $rows->count(), 'imported' => $imported];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function addressValues(int $enrollmentId, array $row): array
    {
        return [
            'enrollment_id' => $enrollmentId,
            'legacy_key' => $this->legacyId($row),
            'current_house_number' => LegacyRowCleaner::text($row['ca_house_number'] ?? null),
            'current_street_name' => LegacyRowCleaner::text($row['ca_street_name'] ?? null),
            'current_barangay' => LegacyRowCleaner::text($row['ca_barangay'] ?? null),
            'current_municipality' => LegacyRowCleaner::text($row['ca_municipality'] ?? null),
            'current_province' => LegacyRowCleaner::text($row['ca_provice'] ?? null),
            'current_country' => LegacyRowCleaner::text($row['ca_country'] ?? null),
            'current_zip_code' => LegacyRowCleaner::zipCode($row['ca_zipcode'] ?? null),
            'current_address_status' => LegacyRowCleaner::text($row['ca_address_status'] ?? null),
            'permanent_house_number' => LegacyRowCleaner::text($row['pa_house_number'] ?? null),
            'permanent_street_name' => LegacyRowCleaner::text($row['pa_street_name'] ?? null),
            'permanent_barangay' => LegacyRowCleaner::text($row['pa_barangay'] ?? null),
            'permanent_municipality' => LegacyRowCleaner::text($row['pa_municipality'] ?? null),
            'permanent_province' => LegacyRowCleaner::text($row['pa_province'] ?? null),
            'permanent_country' => LegacyRowCleaner::text($row['pa_country'] ?? null),
            'permanent_zip_code' => LegacyRowCleaner::zipCode($row['pa_zip_code'] ?? null),
            'permanent_address_status' => LegacyRowCleaner::text($row['pa_address_status'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function parentValues(int $enrollmentId, array $row): array
    {
        $values = [
            'enrollment_id' => $enrollmentId,
            'legacy_key' => $this->legacyId($row),
            'emergency_contact' => LegacyRowCleaner::text($row['ec_to_contact'] ?? null),
        ];

        foreach (['father' => 'fi', 'mother' => 'mi', 'guardian' => 'gi'] as $target => $prefix) {
            $values[$target.'_last_name'] = LegacyRowCleaner::text($row[$prefix.'_last_name'] ?? null);
            $values[$target.'_first_name'] = LegacyRowCleaner::text($row[$prefix.'_first_name'] ?? null);
            $values[$target.'_middle_name'] = LegacyRowCleaner::text($row[$prefix.'_middle_name'] ?? null);
            $values[$target.'_contact_number'] = LegacyRowCleaner::text($row[$prefix.'_contact_number'] ?? null);
            $values[$target.'_occupation'] = LegacyRowCleaner::text($row[$prefix.'_occupation'] ?? null);
            $values[$target.'_relationship_status'] = LegacyRowCleaner::text($row[$prefix.'_relationship_status'] ?? null);
            $values[$target.'_communication'] = LegacyRowCleaner::text($row[$prefix.'_communication'] ?? null);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function medicalValues(int $enrollmentId, array $row): array
    {
        return [
            'enrollment_id' => $enrollmentId,
            'legacy_key' => $this->legacyId($row),
            'allergy_medicine' => LegacyRowCleaner::text($row['mf_a_medicine'] ?? null),
            'allergy_pollen' => LegacyRowCleaner::text($row['mf_a_pollen'] ?? null),
            'allergy_food' => LegacyRowCleaner::text($row['mf_a_food'] ?? null),
            'allergy_others' => LegacyRowCleaner::text($row['mf_a_others'] ?? null),
            'medical_conditions' => LegacyRowCleaner::set($row['mf_o_medical_conditions'] ?? null),
            'medical_conditions_others' => LegacyRowCleaner::text($row['mf_o_others'] ?? null),
            'surgery_date' => LegacyRowCleaner::date($row['mf_sh_surgery_date'] ?? null),
            'surgery_hospital' => LegacyRowCleaner::text($row['mf_sh_hospital_name'] ?? null),
            'surgery_body_part' => LegacyRowCleaner::text($row['mf_sh_bodypart_affected'] ?? null),
            'medication_type' => LegacyRowCleaner::text($row['mf_tm_type'] ?? null),
            'medication_dosage_schedule' => LegacyRowCleaner::text($row['mf_tm_dosage_schedule'] ?? null),
            'chronic_conditions' => LegacyRowCleaner::set($row['mf_mc_conditions'] ?? null),
            'cancer_type' => LegacyRowCleaner::text($row['mf_mc_cancer_type'] ?? null),
            'chronic_conditions_others' => LegacyRowCleaner::text($row['mf_mc_others'] ?? null),
            'exposed_to_covid' => LegacyRowCleaner::boolean($row['mf_exposure_c_v'] ?? false),
            'pertinent_information' => LegacyRowCleaner::text($row['mf_o_pertinent_information'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function specialNeedsValues(int $enrollmentId, array $row): array
    {
        return [
            'enrollment_id' => $enrollmentId,
            'legacy_key' => $this->legacyId($row),
            'diagnosis' => LegacyRowCleaner::set($row['snep_a1_diagnosis'] ?? null),
            'shpcd_subtype' => LegacyRowCleaner::text($row['snep_a1_sub_shpcd'] ?? null),
            'vi_subtype' => LegacyRowCleaner::text($row['snep_a1_sub_vi'] ?? null),
            'manifestations' => LegacyRowCleaner::set($row['snep_a2_manifestations'] ?? null),
            'has_pwd_id' => LegacyRowCleaner::boolean($row['snep_pwd_id'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function legacyId(array $row): ?int
    {
        $id = $row['id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @param  list<int>  $validIds
     */
    private function lookupId(mixed $value, array $validIds): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return in_array($id, $validIds, true) ? $id : null;
    }
}
