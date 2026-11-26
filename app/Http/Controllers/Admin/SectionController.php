<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GradeLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SectionRequest;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    private const ALL_YEARS = 'all';

    public function index(Request $request): Response
    {
        $filter = $request->query('school_year');
        $filter = is_string($filter) && $filter !== '' ? $filter : (string) config('school.current_school_year');

        $sections = Section::query()
            ->withCount(['enrollments', 'teacherAssignments'])
            ->when(
                $filter !== self::ALL_YEARS,
                fn ($query) => $query->where('school_year', $filter),
            )
            ->get()
            // Newest school year first, then Kindergarten up to Grade 6, then by name.
            ->sort(fn (Section $a, Section $b): int => [$b->school_year, $a->grade_level->sortOrder(), $a->name]
                <=> [$a->school_year, $b->grade_level->sortOrder(), $b->name])
            ->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'grade_level' => $section->grade_level->value,
                'grade_label' => $section->grade_level->label(),
                'school_year' => $section->school_year,
                'enrollments_count' => (int) $section->getAttribute('enrollments_count'),
                'assignments_count' => (int) $section->getAttribute('teacher_assignments_count'),
            ])
            ->values();

        return Inertia::render('admin/sections/Index', [
            'sections' => $sections,
            'grades' => GradeLevel::options(),
            'schoolYears' => config('school.school_years'),
            'currentSchoolYear' => config('school.current_school_year'),
            'filter' => $filter,
        ]);
    }

    public function store(SectionRequest $request): RedirectResponse
    {
        Section::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section created.')]);

        return to_route('admin.sections.index', ['school_year' => $request->validated('school_year')]);
    }

    public function update(SectionRequest $request, Section $section): RedirectResponse
    {
        $data = $request->validated();

        // Moving a section to another grade or year would leave its students
        // and teacher assignments pointing at the wrong one. Renaming is fine.
        $errors = [];

        if ($data['grade_level'] !== $section->grade_level->value) {
            $errors['grade_level'] = __('The grade level cannot change while students or assignments use this section.');
        }

        if ($data['school_year'] !== $section->school_year) {
            $errors['school_year'] = __('The school year cannot change while students or assignments use this section.');
        }

        if ($errors !== [] && ($section->enrollments()->exists() || $section->teacherAssignments()->exists())) {
            throw ValidationException::withMessages($errors);
        }

        $section->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section updated.')]);

        return to_route('admin.sections.index', ['school_year' => $section->school_year]);
    }

    public function destroy(Section $section): RedirectResponse
    {
        // The database would quietly un-section the students (null on delete)
        // and delete the teacher assignments (cascade). Refuse instead.
        if ($section->enrollments()->exists() || $section->teacherAssignments()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __(':name still has students or teacher assignments and cannot be deleted.', [
                    'name' => $section->name,
                ]),
            ]);

            return to_route('admin.sections.index', ['school_year' => $section->school_year]);
        }

        $section->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section deleted.')]);

        return to_route('admin.sections.index', ['school_year' => $section->school_year]);
    }
}
