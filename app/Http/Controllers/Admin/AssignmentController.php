<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Term;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignmentRequest;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Rules\SchoolYear;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    /**
     * Assignments for one school year (the current one by default), narrowed by
     * term, teacher, section and subject. The old page asked for `date('Y')`
     * (2026) while assignments store `2026-2027`, so it never matched.
     */
    public function index(Request $request): Response
    {
        $schoolYear = $this->schoolYear($request->query('school_year'));
        $term = $this->term($request->query('term'));
        $teacherId = $this->id($request->query('teacher_id'));
        $sectionId = $this->id($request->query('section_id'));
        $subjectId = $this->id($request->query('subject_id'));

        $assignments = TeacherAssignment::query()
            ->with(['teacher', 'subject', 'section'])
            ->forSchoolYear($schoolYear)
            ->when($term, fn ($query, int $term) => $query->forTerm($term))
            ->when($teacherId, fn ($query, int $id) => $query->forTeacher($id))
            ->when($sectionId, fn ($query, int $id) => $query->forSection($id))
            ->when($subjectId, fn ($query, int $id) => $query->forSubject($id))
            ->get()
            // By term, then Kindergarten up to Grade 6, section, teacher and subject.
            ->sort(fn (TeacherAssignment $a, TeacherAssignment $b): int => [
                $a->term->value,
                $a->section->grade_level->sortOrder(),
                $a->section->name,
                $a->teacher->last_name,
                $a->teacher->first_name,
                $a->subject->name,
            ] <=> [
                $b->term->value,
                $b->section->grade_level->sortOrder(),
                $b->section->name,
                $b->teacher->last_name,
                $b->teacher->first_name,
                $b->subject->name,
            ])
            ->map(fn (TeacherAssignment $assignment): array => [
                'id' => $assignment->id,
                'teacher_id' => $assignment->teacher_id,
                'subject_id' => $assignment->subject_id,
                'section_id' => $assignment->section_id,
                'teacher_name' => $assignment->teacher->fullName(),
                'subject_code' => $assignment->subject->code,
                'subject_name' => $assignment->subject->name,
                'section_name' => $assignment->section->name,
                'grade_label' => $assignment->section->grade_level->label(),
                'school_year' => $assignment->school_year,
                'term' => $assignment->term->value,
                'term_label' => $this->termLabel($assignment->term),
            ])
            ->values();

        $teachers = Teacher::query()
            ->orderedByName()
            ->get()
            ->map(fn (Teacher $teacher): array => ['id' => $teacher->id, 'full_name' => $teacher->fullName()])
            ->values();

        $subjects = Subject::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name,
                'offered_term' => $subject->offered_term === null ? null : (int) $subject->offered_term,
            ])
            ->values();

        // Every year's sections; the page narrows them to the year being used.
        $sections = Section::query()
            ->get()
            ->sort(fn (Section $a, Section $b): int => [$b->school_year, $a->grade_level->sortOrder(), $a->name]
                <=> [$a->school_year, $b->grade_level->sortOrder(), $b->name])
            ->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'grade_level' => $section->grade_level->value,
                'grade_label' => $section->grade_level->label(),
                'school_year' => $section->school_year,
            ])
            ->values();

        return Inertia::render('admin/assignments/Index', [
            'assignments' => $assignments,
            'filters' => [
                'school_year' => $schoolYear,
                'term' => $term,
                'teacher_id' => $teacherId,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
            ],
            'schoolYears' => config('school.school_years'),
            'terms' => array_map(
                fn (Term $case): array => ['value' => $case->value, 'label' => $this->termLabel($case)],
                Term::assignable(),
            ),
            'teachers' => $teachers,
            'subjects' => $subjects,
            'sections' => $sections,
            // What "delete all for this teacher / section" would remove.
            'teacherTotal' => $teacherId === null
                ? null
                : TeacherAssignment::query()->forTeacher($teacherId)->forSchoolYear($schoolYear)->count(),
            'sectionTotal' => $sectionId === null
                ? null
                : TeacherAssignment::query()->forSection($sectionId)->count(),
        ]);
    }

    public function store(AssignmentRequest $request): RedirectResponse
    {
        try {
            TeacherAssignment::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            // Two people saving the same assignment at once.
            throw ValidationException::withMessages(['assignment' => AssignmentRequest::DUPLICATE_MESSAGE]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment created.')]);

        return back(fallback: route('admin.assignments.index'));
    }

    public function update(AssignmentRequest $request, TeacherAssignment $assignment): RedirectResponse
    {
        try {
            $assignment->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['assignment' => AssignmentRequest::DUPLICATE_MESSAGE]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment updated.')]);

        return back(fallback: route('admin.assignments.index'));
    }

    public function destroy(TeacherAssignment $assignment): RedirectResponse
    {
        $assignment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment deleted.')]);

        return back(fallback: route('admin.assignments.index'));
    }

    /**
     * Remove one teacher's assignments for one school year. The year is
     * required so a click can never wipe the teacher's earlier years.
     */
    public function destroyForTeacher(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'school_year' => ['required', 'string', new SchoolYear],
        ]);

        $count = $teacher->assignments()->forSchoolYear($data['school_year'])->delete();

        return $this->deleted($count, $teacher->fullName().' ('.$data['school_year'].')');
    }

    /**
     * Remove every assignment of one section. A section belongs to a single
     * school year, so that is the scope.
     */
    public function destroyForSection(Section $section): RedirectResponse
    {
        $count = $section->teacherAssignments()->delete();

        return $this->deleted($count, $section->name.' ('.$section->school_year.')');
    }

    private function deleted(int $count, string $for): RedirectResponse
    {
        Inertia::flash('toast', $count === 0
            ? ['type' => 'info', 'message' => __('There were no assignments to delete for :for.', ['for' => $for])]
            : ['type' => 'success', 'message' => __('Deleted :count assignment(s) for :for.', ['count' => $count, 'for' => $for])]);

        return back(fallback: route('admin.assignments.index'));
    }

    private function termLabel(Term $term): string
    {
        return (string) config("school.terms.{$term->value}.label");
    }

    private function schoolYear(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{4}$/', $value) === 1
            ? $value
            : (string) config('school.current_school_year');
    }

    private function term(mixed $value): ?int
    {
        $term = is_string($value) ? Term::tryFrom((int) $value) : null;

        return $term?->isAssignable() === true ? $term->value : null;
    }

    private function id(mixed $value): ?int
    {
        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
