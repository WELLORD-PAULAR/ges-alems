<?php

namespace Database\Seeders;

use App\Enums\GradeLevel;
use App\Models\Enrollment;
use App\Models\EnrollmentAddress;
use App\Models\EnrollmentMedical;
use App\Models\EnrollmentParent;
use App\Models\EnrollmentSpecialNeed;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Seeder;

/**
 * Fake students for local development. No real student data ever lives in the
 * repository; these rows come from factories.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Student::query()->exists()) {
            return;
        }

        if (! Section::query()->exists()) {
            $this->call(ReferenceDataSeeder::class);
        }

        $schoolYear = (string) config('school.current_school_year');
        $sections = Section::query()->where('school_year', $schoolYear)->get();

        if ($sections->isEmpty()) {
            return;
        }

        /** @var list<GradeLevel> $grades */
        $grades = $sections
            ->map(fn (Section $section): GradeLevel => $section->grade_level)
            ->unique()
            ->values()
            ->all();

        for ($i = 0; $i < 30; $i++) {
            $grade = $grades[array_rand($grades)];
            $section = $sections->first(
                fn (Section $candidate): bool => $candidate->grade_level === $grade,
            );

            if (! $section instanceof Section) {
                continue;
            }

            $student = Student::factory()->create();

            $enrollment = Enrollment::factory()->for($student)->create([
                'school_year' => $schoolYear,
                'grade_level' => $grade,
                'section_id' => $section->id,
            ]);

            EnrollmentAddress::factory()->for($enrollment)->create();
            EnrollmentParent::factory()->for($enrollment)->create();
            EnrollmentMedical::factory()->for($enrollment)->create();
            EnrollmentSpecialNeed::factory()->for($enrollment)->create();
        }
    }
}
