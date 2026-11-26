<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GradeLevel;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignSectionRequest;
use App\Http\Requests\Admin\BulkAssignSectionsRequest;
use App\Models\Enrollment;
use App\Models\Section;
use App\Services\SectionAssigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SectioningController extends Controller
{
    /**
     * One grade level of one school year at a time, filtered by verification
     * status. Searching by name or LRN happens in the browser so unsaved
     * changes are not lost while someone searches.
     */
    public function index(Request $request): Response
    {
        $schoolYear = $this->schoolYear($request->query('school_year'));
        $grade = GradeLevel::tryFrom($this->text($request->query('grade')) ?? '') ?? GradeLevel::Kindergarten;
        $status = VerificationStatus::tryFrom($this->text($request->query('status')) ?? '');

        $students = Enrollment::query()
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->where('enrollments.school_year', $schoolYear)
            ->where('enrollments.grade_level', $grade->value)
            ->when(
                $status !== null,
                fn ($query) => $query->where('enrollments.verification', $status?->value),
            )
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('enrollments.*')
            ->with('student')
            ->get()
            ->map(fn (Enrollment $enrollment): array => [
                'enrollment_id' => $enrollment->id,
                'full_name' => $enrollment->student->fullName(),
                'lrn' => $enrollment->lrn,
                'verification' => [
                    'value' => $enrollment->verification->value,
                    'label' => $enrollment->verification->label(),
                    'tone' => $enrollment->verification->tone(),
                ],
                'section_id' => $enrollment->section_id,
            ])
            ->values();

        // Only the sections this grade and year can actually use.
        $sections = Section::query()
            ->where('school_year', $schoolYear)
            ->where('grade_level', $grade->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Section $section): array => ['id' => $section->id, 'name' => $section->name])
            ->values();

        $totals = Enrollment::query()
            ->where('school_year', $schoolYear)
            ->selectRaw('grade_level, count(*) as total')
            ->groupBy('grade_level')
            ->pluck('total', 'grade_level');

        $gradeCounts = [];

        foreach (GradeLevel::cases() as $case) {
            $gradeCounts[$case->value] = (int) ($totals[$case->value] ?? 0);
        }

        return Inertia::render('admin/sectioning/Index', [
            'schoolYear' => $schoolYear,
            'schoolYears' => config('school.school_years'),
            'grade' => $grade->value,
            'status' => $status?->value,
            'grades' => GradeLevel::options(),
            'statuses' => array_map(
                fn (VerificationStatus $case): array => ['value' => $case->value, 'label' => $case->label()],
                VerificationStatus::cases(),
            ),
            'students' => $students,
            'sections' => $sections,
            'gradeCounts' => $gradeCounts,
        ]);
    }

    /**
     * Assign one student, or remove them from their section with a null id.
     */
    public function assign(AssignSectionRequest $request, Enrollment $enrollment, SectionAssigner $assigner): RedirectResponse
    {
        $sectionId = $request->validated('section_id');

        $result = $assigner->assign($enrollment, $sectionId === null ? null : (int) $sectionId);

        if (! $result['ok']) {
            throw ValidationException::withMessages(['section_id' => (string) $result['message']]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $sectionId === null ? __('Section removed.') : __('Section assigned.'),
        ]);

        return back();
    }

    /**
     * Save many assignments at once. Returns a result for every row, so the
     * screen can show which rows were saved and why the others were not.
     */
    public function bulk(BulkAssignSectionsRequest $request, SectionAssigner $assigner): JsonResponse
    {
        /** @var list<array{enrollment_id: int, section_id: int|null}> $rows */
        $rows = array_map(
            fn (array $row): array => [
                'enrollment_id' => (int) $row['enrollment_id'],
                'section_id' => $row['section_id'] === null ? null : (int) $row['section_id'],
            ],
            $request->validated('assignments'),
        );

        $results = $assigner->assignMany($rows);

        $failed = count(array_filter($results, fn (array $result): bool => ! $result['ok']));

        return response()->json([
            'saved' => count($results) - $failed,
            'failed' => $failed,
            'results' => $results,
        ]);
    }

    private function schoolYear(mixed $value): string
    {
        $text = $this->text($value);

        return $text !== null && preg_match('/^\d{4}-\d{4}$/', $text) === 1
            ? $text
            : (string) config('school.current_school_year');
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
