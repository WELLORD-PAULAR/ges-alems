<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Term;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubjectRequest;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(): Response
    {
        $subjects = Subject::query()
            ->withCount('teacherAssignments')
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name,
                'offered_term' => $subject->offered_term,
                'term_label' => $subject->termLabel(),
                'assignments_count' => (int) $subject->getAttribute('teacher_assignments_count'),
            ])
            ->values();

        $terms = array_map(
            fn (Term $term): array => [
                'value' => $term->value,
                'label' => (string) config("school.terms.{$term->value}.label"),
            ],
            Term::assignable(),
        );

        return Inertia::render('admin/subjects/Index', [
            'subjects' => $subjects,
            'terms' => $terms,
        ]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['offered_term'] ??= null;

        Subject::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject created.')]);

        return to_route('admin.subjects.index');
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $data = $request->validated();
        // The old app ignored a blank term on update, so a subject could never
        // be switched back to "all terms". Blank is a real value here.
        $data['offered_term'] ??= null;

        $subject->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject updated.')]);

        return to_route('admin.subjects.index');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        // The foreign key cascades, so the database would silently delete the
        // assignments. Refuse here instead.
        if ($subject->teacherAssignments()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __(':name is assigned to a teacher and cannot be deleted.', [
                    'name' => $subject->name,
                ]),
            ]);

            return to_route('admin.subjects.index');
        }

        $subject->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject deleted.')]);

        return to_route('admin.subjects.index');
    }
}
