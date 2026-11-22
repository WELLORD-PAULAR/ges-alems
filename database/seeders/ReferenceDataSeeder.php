<?php

namespace Database\Seeders;

use App\Enums\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Sections from the old `SectionAssignmentHandler::SECTIONS` constant plus the
 * standard subject list. Safe to re-run.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $schoolYear = (string) config('school.current_school_year');
        $groups = config('school.sections');

        if (is_array($groups)) {
            foreach ($groups as $grade => $names) {
                $gradeLevel = GradeLevel::tryFrom((string) $grade);

                if ($gradeLevel === null || ! is_array($names)) {
                    continue;
                }

                foreach ($names as $name) {
                    if (! is_string($name)) {
                        continue;
                    }

                    Section::updateOrCreate(
                        ['name' => $name, 'school_year' => $schoolYear],
                        ['grade_level' => $gradeLevel],
                    );
                }
            }
        }

        /** @var list<array{0: string, 1: string, 2: int|null}> $subjects */
        $subjects = [
            ['MTB-MLE', 'Mother Tongue', null],
            ['FIL', 'Filipino', null],
            ['ENG', 'English', null],
            ['MATH', 'Mathematics', null],
            ['SCI', 'Science', null],
            ['AP', 'Araling Panlipunan', null],
            ['ESP', 'Edukasyon sa Pagpapakatao', null],
            ['MAPEH', 'MAPEH', null],
        ];

        foreach ($subjects as [$code, $name, $term]) {
            Subject::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'offered_term' => $term],
            );
        }
    }
}
