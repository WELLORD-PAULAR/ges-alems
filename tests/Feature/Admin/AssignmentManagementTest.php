<?php

use App\Enums\GradeLevel;
use App\Http\Requests\Admin\AssignmentRequest;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->year = (string) config('school.current_school_year');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function assignmentPayload(Teacher $teacher, Subject $subject, Section $section, array $overrides = []): array
{
    return array_merge([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'section_id' => $section->id,
        'school_year' => $section->school_year,
        'term' => 1,
    ], $overrides);
}

/** A subject offered in every term unless told otherwise. */
function subjectOffered(?int $term = null): Subject
{
    return Subject::factory()->create(['offered_term' => $term]);
}

/**
 * The ids of the assignments shown on the page, lowest first.
 *
 * @return list<int>
 */
function listedIds(TestResponse $response): array
{
    $ids = [];

    $response->assertInertia(function (Assert $page) use (&$ids) {
        $page->where('assignments', function ($rows) use (&$ids) {
            $ids = collect($rows)->pluck('id')->sort()->values()->all();

            return true;
        });
    });

    return $ids;
}

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

test('guests are redirected to the login page', function () {
    $this->get(route('admin.assignments.index'))->assertRedirect(route('login'));
});

test('teachers cannot use any assignment route', function () {
    $teacherUser = User::factory()->teacher()->create();
    $assignment = TeacherAssignment::factory()->create();
    $teacher = $assignment->teacher;
    $section = $assignment->section;

    $this->actingAs($teacherUser);

    $this->get(route('admin.assignments.index'))->assertForbidden();
    $this->post(route('admin.assignments.store'), assignmentPayload($teacher, $assignment->subject, $section))->assertForbidden();
    $this->put(route('admin.assignments.update', $assignment), assignmentPayload($teacher, $assignment->subject, $section))->assertForbidden();
    $this->delete(route('admin.assignments.destroy', $assignment))->assertForbidden();
    $this->delete(route('admin.teachers.assignments.destroy', ['teacher' => $teacher, 'school_year' => $assignment->school_year]))->assertForbidden();
    $this->delete(route('admin.sections.assignments.destroy', $section))->assertForbidden();

    expect(TeacherAssignment::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Listing and filters
|--------------------------------------------------------------------------
*/

test('the current school year is listed by default, ordered by term, grade, section and teacher', function () {
    $abad = Teacher::factory()->create(['last_name' => 'Abad', 'first_name' => 'Ana']);
    $zulu = Teacher::factory()->create(['last_name' => 'Zulu', 'first_name' => 'Zed']);
    $kinder = Section::factory()->forGrade(GradeLevel::Kindergarten)->create(['name' => 'K-A']);
    $grade3 = Section::factory()->forGrade(GradeLevel::Grade3)->create(['name' => '3-A']);
    $oldSection = Section::factory()->forGrade(GradeLevel::Grade3)->forSchoolYear('2020-2021')->create();

    $second = TeacherAssignment::factory()->create(['teacher_id' => $abad->id, 'section_id' => $grade3->id, 'term' => 2]);
    $zuluGrade3 = TeacherAssignment::factory()->create(['teacher_id' => $zulu->id, 'section_id' => $grade3->id, 'term' => 1]);
    $zuluKinder = TeacherAssignment::factory()->create(['teacher_id' => $zulu->id, 'section_id' => $kinder->id, 'term' => 1]);
    $abadGrade3 = TeacherAssignment::factory()->create(['teacher_id' => $abad->id, 'section_id' => $grade3->id, 'term' => 1]);
    TeacherAssignment::factory()->create(['section_id' => $oldSection->id, 'school_year' => '2020-2021']);

    $expected = [$zuluKinder->id, $abadGrade3->id, $zuluGrade3->id, $second->id];

    $this->actingAs($this->admin)
        ->get(route('admin.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/assignments/Index')
            ->where('filters.school_year', $this->year)
            ->where('assignments', fn ($rows) => collect($rows)->pluck('id')->all() === $expected)
            ->where('assignments.0.term_label', config('school.terms.1.label'))
            ->where('assignments.1.teacher_name', $abad->fullName()));
});

test('assignments can be filtered by year, term, teacher, section and subject', function () {
    $teacherA = Teacher::factory()->create();
    $teacherB = Teacher::factory()->create();
    $subject1 = subjectOffered();
    $subject2 = subjectOffered();
    $grade3 = Section::factory()->forGrade(GradeLevel::Grade3)->create();
    $kinder = Section::factory()->forGrade(GradeLevel::Kindergarten)->create();
    $oldSection = Section::factory()->forGrade(GradeLevel::Grade3)->forSchoolYear('2020-2021')->create();

    $a = TeacherAssignment::factory()->create(['teacher_id' => $teacherA->id, 'subject_id' => $subject1->id, 'section_id' => $grade3->id, 'term' => 1]);
    $b = TeacherAssignment::factory()->create(['teacher_id' => $teacherB->id, 'subject_id' => $subject2->id, 'section_id' => $kinder->id, 'term' => 2]);
    $c = TeacherAssignment::factory()->create(['teacher_id' => $teacherA->id, 'subject_id' => $subject2->id, 'section_id' => $grade3->id, 'term' => 2]);
    $old = TeacherAssignment::factory()->create(['section_id' => $oldSection->id, 'school_year' => '2020-2021', 'term' => 1]);

    $this->actingAs($this->admin);

    expect(listedIds($this->get(route('admin.assignments.index'))))->toBe([$a->id, $b->id, $c->id])
        ->and(listedIds($this->get(route('admin.assignments.index', ['school_year' => '2020-2021']))))->toBe([$old->id])
        ->and(listedIds($this->get(route('admin.assignments.index', ['term' => 2]))))->toBe([$b->id, $c->id])
        ->and(listedIds($this->get(route('admin.assignments.index', ['teacher_id' => $teacherA->id]))))->toBe([$a->id, $c->id])
        ->and(listedIds($this->get(route('admin.assignments.index', ['section_id' => $kinder->id]))))->toBe([$b->id])
        ->and(listedIds($this->get(route('admin.assignments.index', ['subject_id' => $subject2->id]))))->toBe([$b->id, $c->id])
        ->and(listedIds($this->get(route('admin.assignments.index', ['term' => 2, 'teacher_id' => $teacherA->id]))))->toBe([$c->id]);
});

test('unknown filter values fall back instead of breaking the page', function () {
    $assignment = TeacherAssignment::factory()->create(['term' => 1]);

    $this->actingAs($this->admin);

    // A plain year (what the old page asked for) is not a school year.
    $this->get(route('admin.assignments.index', ['school_year' => '2026']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.school_year', $this->year));

    // Summer and junk are not terms.
    foreach (['4', '0', 'x'] as $term) {
        $this->get(route('admin.assignments.index', ['term' => $term]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.term', null));
    }

    expect(listedIds($this->get(route('admin.assignments.index', ['term' => '4']))))->toBe([$assignment->id]);
});

test('the page offers terms 1 to 3 and the totals for a chosen teacher and section', function () {
    $teacher = Teacher::factory()->create();
    $section = Section::factory()->create();
    $otherYearSection = Section::factory()->forSchoolYear('2020-2021')->create();

    TeacherAssignment::factory()->count(2)->create(['teacher_id' => $teacher->id, 'section_id' => $section->id, 'term' => 1]);
    TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'section_id' => $otherYearSection->id, 'school_year' => '2020-2021']);

    $this->actingAs($this->admin)
        ->get(route('admin.assignments.index', ['teacher_id' => $teacher->id, 'section_id' => $section->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('terms', fn ($terms) => collect($terms)->pluck('value')->all() === [1, 2, 3])
            // This teacher, this school year only.
            ->where('teacherTotal', 2)
            ->where('sectionTotal', 2));

    $this->get(route('admin.assignments.index'))
        ->assertInertia(fn (Assert $page) => $page->where('teacherTotal', null)->where('sectionTotal', null));
});

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

test('an admin can create an assignment', function () {
    $teacher = Teacher::factory()->create();
    $subject = subjectOffered();
    $section = Section::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.assignments.store'), assignmentPayload($teacher, $subject, $section, ['term' => 2]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('teacher_assignments', [
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'section_id' => $section->id,
        'school_year' => $this->year,
        'term' => 2,
    ]);
});

test('every field is required', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.assignments.store'), [])
        ->assertSessionHasErrors(['teacher_id', 'subject_id', 'section_id', 'school_year', 'term']);

    expect(TeacherAssignment::query()->count())->toBe(0);
});

test('the teacher, subject and section must exist', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.assignments.store'), [
            'teacher_id' => 99999,
            'subject_id' => 99999,
            'section_id' => 99999,
            'school_year' => $this->year,
            'term' => 1,
        ])
        ->assertSessionHasErrors(['teacher_id', 'subject_id', 'section_id']);
});

test('terms 1 to 3 are accepted', function (int $term) {
    $teacher = Teacher::factory()->create();
    $section = Section::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.assignments.store'), assignmentPayload($teacher, subjectOffered(), $section, ['term' => $term]))
        ->assertSessionHasNoErrors();

    expect(TeacherAssignment::query()->count())->toBe(1);
})->with([1, 2, 3]);

test('summer and other terms are rejected', function (mixed $term) {
    $teacher = Teacher::factory()->create();
    $section = Section::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.assignments.store'), assignmentPayload($teacher, subjectOffered(), $section, ['term' => $term]))
        ->assertSessionHasErrors('term');

    expect(TeacherAssignment::query()->count())->toBe(0);
})->with([4, 0, 5, 'x']);

test('the school year must be YYYY-YYYY with consecutive years', function (string $year) {
    $teacher = Teacher::factory()->create();
    $section = Section::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.assignments.store'), assignmentPayload($teacher, subjectOffered(), $section, ['school_year' => $year]))
        ->assertSessionHasErrors('school_year');

    expect(TeacherAssignment::query()->count())->toBe(0);
})->with(['2026', '2026-27', 'abcd-efgh', '2026-2028', '2027-2026']);

test('the section must belong to the same school year as the assignment', function () {
    $teacher = Teacher::factory()->create();
    $oldSection = Section::factory()->forSchoolYear('2020-2021')->create(['name' => '3-Old']);

    $this->actingAs($this->admin)
        ->post(route('admin.assignments.store'), assignmentPayload($teacher, subjectOffered(), $oldSection, ['school_year' => $this->year]))
        ->assertSessionHasErrors('section_id');

    expect(TeacherAssignment::query()->count())->toBe(0);

    // The same section is fine for its own year.
    $this->post(route('admin.assignments.store'), assignmentPayload($teacher, subjectOffered(), $oldSection))
        ->assertSessionHasNoErrors();

    expect(TeacherAssignment::query()->count())->toBe(1);
});

test('a subject can only be assigned in a term it is offered in', function () {
    $teacher = Teacher::factory()->create();
    $section = Section::factory()->create();
    $secondTermOnly = subjectOffered(2);

    $this->actingAs($this->admin)
        ->post(route('admin.assignments.store'), assignmentPayload($teacher, $secondTermOnly, $section, ['term' => 1]))
        ->assertSessionHasErrors('subject_id');

    expect(TeacherAssignment::query()->count())->toBe(0);

    $this->post(route('admin.assignments.store'), assignmentPayload($teacher, $secondTermOnly, $section, ['term' => 2]))
        ->assertSessionHasNoErrors();

    // A subject offered every term fits any term.
    $this->post(route('admin.assignments.store'), assignmentPayload($teacher, subjectOffered(), $section, ['term' => 3]))
        ->assertSessionHasNoErrors();

    expect(TeacherAssignment::query()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Duplicates
|--------------------------------------------------------------------------
*/

test('the same assignment cannot be created twice, and the message is friendly', function () {
    $teacher = Teacher::factory()->create();
    $subject = subjectOffered();
    $section = Section::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.assignments.store'), assignmentPayload($teacher, $subject, $section))
        ->assertSessionHasNoErrors();

    $this->post(route('admin.assignments.store'), assignmentPayload($teacher, $subject, $section))
        ->assertSessionHasErrors(['assignment' => AssignmentRequest::DUPLICATE_MESSAGE]);

    expect(TeacherAssignment::query()->count())->toBe(1);
});

test('changing any one part of the assignment makes it a different assignment', function () {
    $teacher = Teacher::factory()->create();
    $subject = subjectOffered();
    $section = Section::factory()->create();
    $otherYearSection = Section::factory()->forSchoolYear('2020-2021')->create();

    $this->actingAs($this->admin)
        ->post(route('admin.assignments.store'), assignmentPayload($teacher, $subject, $section))
        ->assertSessionHasNoErrors();

    $variants = [
        assignmentPayload($teacher, $subject, $section, ['term' => 2]),
        assignmentPayload(Teacher::factory()->create(), $subject, $section),
        assignmentPayload($teacher, subjectOffered(), $section),
        assignmentPayload($teacher, $subject, Section::factory()->create()),
        assignmentPayload($teacher, $subject, $otherYearSection),
    ];

    foreach ($variants as $variant) {
        $this->post(route('admin.assignments.store'), $variant)->assertSessionHasNoErrors();
    }

    expect(TeacherAssignment::query()->count())->toBe(6);
});

test('the database itself refuses a duplicate even if the form check is skipped', function () {
    $assignment = TeacherAssignment::factory()->create();

    expect(fn () => TeacherAssignment::create([
        'teacher_id' => $assignment->teacher_id,
        'subject_id' => $assignment->subject_id,
        'section_id' => $assignment->section_id,
        'school_year' => $assignment->school_year,
        'term' => $assignment->term->value,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

test('an assignment can be changed', function () {
    $assignment = TeacherAssignment::factory()->create(['term' => 1]);
    $teacher = Teacher::factory()->create();
    $subject = subjectOffered();
    $section = Section::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.assignments.update', $assignment), assignmentPayload($teacher, $subject, $section, ['term' => 3]))
        ->assertSessionHasNoErrors();

    $assignment->refresh();

    expect($assignment->teacher_id)->toBe($teacher->id)
        ->and($assignment->subject_id)->toBe($subject->id)
        ->and($assignment->section_id)->toBe($section->id)
        ->and($assignment->term->value)->toBe(3);
});

test('saving an assignment unchanged is not a duplicate of itself', function () {
    $subject = subjectOffered();
    $assignment = TeacherAssignment::factory()->create(['subject_id' => $subject->id, 'term' => 1]);

    $this->actingAs($this->admin)
        ->put(route('admin.assignments.update', $assignment), assignmentPayload($assignment->teacher, $subject, $assignment->section))
        ->assertSessionHasNoErrors();
});

test('changing an assignment into another one is rejected', function () {
    $subject = subjectOffered();
    $first = TeacherAssignment::factory()->create(['subject_id' => $subject->id, 'term' => 1]);
    $second = TeacherAssignment::factory()->create([
        'teacher_id' => $first->teacher_id,
        'subject_id' => $subject->id,
        'section_id' => $first->section_id,
        'term' => 2,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.assignments.update', $second), assignmentPayload($first->teacher, $subject, $first->section, ['term' => 1]))
        ->assertSessionHasErrors(['assignment' => AssignmentRequest::DUPLICATE_MESSAGE]);

    expect($second->refresh()->term->value)->toBe(2);
});

test('updating still checks the section year and the subject term', function () {
    $assignment = TeacherAssignment::factory()->create(['term' => 1]);
    $oldSection = Section::factory()->forSchoolYear('2020-2021')->create();

    $this->actingAs($this->admin)
        ->put(route('admin.assignments.update', $assignment), assignmentPayload($assignment->teacher, subjectOffered(), $oldSection, ['school_year' => $this->year]))
        ->assertSessionHasErrors('section_id');

    $this->put(route('admin.assignments.update', $assignment), assignmentPayload($assignment->teacher, subjectOffered(2), $assignment->section, ['term' => 1]))
        ->assertSessionHasErrors('subject_id');
});

/*
|--------------------------------------------------------------------------
| Delete: one assignment, by teacher, by section
|--------------------------------------------------------------------------
*/

test('one assignment can be deleted', function () {
    $assignment = TeacherAssignment::factory()->create();
    $other = TeacherAssignment::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.assignments.destroy', $assignment))
        ->assertRedirect();

    $this->assertModelMissing($assignment);
    $this->assertModelExists($other);
    $this->assertModelExists($assignment->teacher);
    $this->assertModelExists($assignment->subject);
    $this->assertModelExists($assignment->section);
});

test('all of a teacher\'s assignments in a school year can be deleted, and nobody else\'s', function () {
    $teacher = Teacher::factory()->create();
    $otherTeacher = Teacher::factory()->create();
    $section = Section::factory()->create();
    $oldSection = Section::factory()->forSchoolYear('2020-2021')->create();

    $mine = TeacherAssignment::factory()->count(2)->create(['teacher_id' => $teacher->id, 'section_id' => $section->id]);
    $earlierYear = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'section_id' => $oldSection->id, 'school_year' => '2020-2021']);
    $theirs = TeacherAssignment::factory()->create(['teacher_id' => $otherTeacher->id, 'section_id' => $section->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.teachers.assignments.destroy', ['teacher' => $teacher, 'school_year' => $this->year]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    foreach ($mine as $assignment) {
        $this->assertModelMissing($assignment);
    }

    $this->assertModelExists($earlierYear);
    $this->assertModelExists($theirs);
    $this->assertModelExists($teacher);
});

test('deleting a teacher\'s assignments needs a valid school year', function (?string $year) {
    $assignment = TeacherAssignment::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.teachers.assignments.destroy', array_filter(['teacher' => $assignment->teacher, 'school_year' => $year])))
        ->assertSessionHasErrors('school_year');

    $this->assertModelExists($assignment);
})->with([null, '2026', '2026-2028']);

test('deleting for a teacher with nothing to delete is harmless', function () {
    $teacher = Teacher::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.teachers.assignments.destroy', ['teacher' => $teacher, 'school_year' => $this->year]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

test('all of a section\'s assignments can be deleted, and no other section\'s', function () {
    $section = Section::factory()->create();
    $otherSection = Section::factory()->create();

    $inSection = TeacherAssignment::factory()->count(3)->create(['section_id' => $section->id]);
    $elsewhere = TeacherAssignment::factory()->create(['section_id' => $otherSection->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.sections.assignments.destroy', $section))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    foreach ($inSection as $assignment) {
        $this->assertModelMissing($assignment);
    }

    $this->assertModelExists($elsewhere);
    $this->assertModelExists($section);
});

test('clearing assignments frees the teacher and the section to be deleted', function () {
    $teacher = Teacher::factory()->create();
    $section = Section::factory()->create();
    TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'section_id' => $section->id]);

    $this->actingAs($this->admin);

    // Blocked while assignments exist (Slices 5 and 7).
    $this->delete(route('admin.teachers.destroy', $teacher));
    $this->delete(route('admin.sections.destroy', $section));
    $this->assertModelExists($teacher);
    $this->assertModelExists($section);

    $this->delete(route('admin.teachers.assignments.destroy', ['teacher' => $teacher, 'school_year' => $this->year]));

    $this->delete(route('admin.teachers.destroy', $teacher));
    $this->delete(route('admin.sections.destroy', $section));

    $this->assertModelMissing($teacher);
    $this->assertModelMissing($section);
});

/*
|--------------------------------------------------------------------------
| Model scopes (the old list queries)
|--------------------------------------------------------------------------
*/

test('the list scopes combine like the old by-year, by-term, by-teacher, by-section and by-subject queries', function () {
    $teacher = Teacher::factory()->create();
    $subject = subjectOffered();
    $section = Section::factory()->create();
    $match = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'section_id' => $section->id, 'term' => 2]);
    TeacherAssignment::factory()->count(2)->create();

    expect(TeacherAssignment::forSchoolYear($this->year)->count())->toBe(3)
        ->and(TeacherAssignment::forTeacher($teacher)->pluck('id')->all())->toBe([$match->id])
        ->and(TeacherAssignment::forSubject($subject->id)->pluck('id')->all())->toBe([$match->id])
        ->and(TeacherAssignment::forSection($section)->pluck('id')->all())->toBe([$match->id])
        ->and(TeacherAssignment::forSchoolYear($this->year)->forTerm(2)->forTeacher($teacher)->pluck('id')->all())->toBe([$match->id])
        ->and(TeacherAssignment::forSchoolYear('1999-2000')->count())->toBe(0);
});
