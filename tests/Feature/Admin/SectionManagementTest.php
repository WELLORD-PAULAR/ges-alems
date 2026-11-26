<?php

use App\Enums\GradeLevel;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\TeacherAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->year = (string) config('school.current_school_year');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sectionPayload(array $overrides = []): array
{
    return array_merge([
        'name' => '3-Peace',
        'grade_level' => '3',
        'school_year' => (string) config('school.current_school_year'),
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

test('guests are redirected to the login page', function () {
    $this->get(route('admin.sections.index'))->assertRedirect(route('login'));
    $this->get(route('admin.sectioning.index'))->assertRedirect(route('login'));
});

test('teachers cannot use any section or sectioning route', function () {
    $teacher = User::factory()->teacher()->create();
    $section = Section::factory()->create();
    $enrollment = Enrollment::factory()->create();

    $this->actingAs($teacher);

    $this->get(route('admin.sections.index'))->assertForbidden();
    $this->post(route('admin.sections.store'), sectionPayload())->assertForbidden();
    $this->put(route('admin.sections.update', $section), sectionPayload())->assertForbidden();
    $this->delete(route('admin.sections.destroy', $section))->assertForbidden();

    $this->get(route('admin.sectioning.index'))->assertForbidden();
    $this->put(route('admin.sectioning.assign', $enrollment), ['section_id' => $section->id])->assertForbidden();
    $this->postJson(route('admin.sectioning.bulk'), [
        'assignments' => [['enrollment_id' => $enrollment->id, 'section_id' => $section->id]],
    ])->assertForbidden();

    expect($enrollment->refresh()->section_id)->toBeNull()
        ->and(Section::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Listing
|--------------------------------------------------------------------------
*/

test('sections of the current year are listed from kindergarten up, then by name', function () {
    Section::factory()->forGrade(GradeLevel::Grade2)->create(['name' => 'Zeta']);
    Section::factory()->forGrade(GradeLevel::Grade2)->create(['name' => 'Alpha']);
    Section::factory()->forGrade(GradeLevel::Kindergarten)->create(['name' => 'Kinder']);
    Section::factory()->forSchoolYear('2020-2021')->create(['name' => 'Old']);

    $this->actingAs($this->admin)
        ->get(route('admin.sections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/sections/Index')
            ->where('filter', $this->year)
            ->where('sections', fn ($sections) => collect($sections)->pluck('name')->all() === ['Kinder', 'Alpha', 'Zeta']));
});

test('every school year can be listed, newest first', function () {
    Section::factory()->forSchoolYear('2020-2021')->forGrade(GradeLevel::Grade1)->create(['name' => 'Old']);
    Section::factory()->forGrade(GradeLevel::Grade1)->create(['name' => 'New']);

    $this->actingAs($this->admin)
        ->get(route('admin.sections.index', ['school_year' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections', fn ($sections) => collect($sections)->pluck('name')->all() === ['New', 'Old']));
});

test('each section shows how many students and assignments use it', function () {
    $section = Section::factory()->create();
    Enrollment::factory()->count(2)->create(['section_id' => $section->id]);
    TeacherAssignment::factory()->create(['section_id' => $section->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.sections.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.0.enrollments_count', 2)
            ->where('sections.0.assignments_count', 1));
});

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

test('an admin can create a section', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.sections.store'), sectionPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('sections', [
        'name' => '3-Peace',
        'grade_level' => '3',
        'school_year' => $this->year,
    ]);
});

test('name, grade level and school year are required', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.sections.store'), [])
        ->assertSessionHasErrors(['name', 'grade_level', 'school_year']);

    expect(Section::query()->count())->toBe(0);
});

test('a name must be unique within its school year only', function () {
    Section::factory()->create(['name' => '3-Peace']);

    $this->actingAs($this->admin)
        ->post(route('admin.sections.store'), sectionPayload(['grade_level' => '4']))
        ->assertSessionHasErrors('name');

    $this->post(route('admin.sections.store'), sectionPayload(['school_year' => '2020-2021']))
        ->assertSessionHasNoErrors();

    expect(Section::query()->count())->toBe(2);
});

test('an unknown grade level is rejected', function (string $grade) {
    $this->actingAs($this->admin)
        ->post(route('admin.sections.store'), sectionPayload(['grade_level' => $grade]))
        ->assertSessionHasErrors('grade_level');
})->with(['7', 'GRADE 1', 'x', '0']);

test('the school year must be YYYY-YYYY with consecutive years', function (string $year) {
    $this->actingAs($this->admin)
        ->post(route('admin.sections.store'), sectionPayload(['school_year' => $year]))
        ->assertSessionHasErrors('school_year');

    expect(Section::query()->count())->toBe(0);
})->with(['2026', '2026-27', 'abcd-efgh', '2026-2028', '2027-2026']);

test('every grade level from kindergarten to grade six is accepted', function (string $grade) {
    $this->actingAs($this->admin)
        ->post(route('admin.sections.store'), sectionPayload(['grade_level' => $grade]))
        ->assertSessionHasNoErrors();
})->with(['K', '1', '2', '3', '4', '5', '6']);

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

test('a section can be renamed and keep its own name', function () {
    $section = Section::factory()->forGrade(GradeLevel::Grade3)->create(['name' => '3-Peace']);

    $this->actingAs($this->admin)
        ->put(route('admin.sections.update', $section), sectionPayload(['name' => '3-Peace']))
        ->assertSessionHasNoErrors();

    $this->put(route('admin.sections.update', $section), sectionPayload(['name' => '3-Joy']))
        ->assertSessionHasNoErrors();

    expect($section->refresh()->name)->toBe('3-Joy');
});

test('renaming to another section\'s name is rejected', function () {
    Section::factory()->create(['name' => '3-Faith']);
    $section = Section::factory()->forGrade(GradeLevel::Grade3)->create(['name' => '3-Peace']);

    $this->actingAs($this->admin)
        ->put(route('admin.sections.update', $section), sectionPayload(['name' => '3-Faith']))
        ->assertSessionHasErrors('name');

    expect($section->refresh()->name)->toBe('3-Peace');
});

test('grade level and school year can change while nothing uses the section', function () {
    $section = Section::factory()->forGrade(GradeLevel::Grade3)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.sections.update', $section), sectionPayload([
            'name' => $section->name,
            'grade_level' => '4',
            'school_year' => '2020-2021',
        ]))
        ->assertSessionHasNoErrors();

    $section->refresh();

    expect($section->grade_level)->toBe(GradeLevel::Grade4)
        ->and($section->school_year)->toBe('2020-2021');
});

test('grade level and school year are locked while students use the section', function () {
    $section = Section::factory()->forGrade(GradeLevel::Grade3)->create();
    Enrollment::factory()->create(['section_id' => $section->id, 'grade_level' => GradeLevel::Grade3]);

    $this->actingAs($this->admin)
        ->put(route('admin.sections.update', $section), sectionPayload([
            'name' => $section->name,
            'grade_level' => '4',
            'school_year' => '2020-2021',
        ]))
        ->assertSessionHasErrors(['grade_level', 'school_year']);

    $section->refresh();

    expect($section->grade_level)->toBe(GradeLevel::Grade3)
        ->and($section->school_year)->toBe($this->year);

    // Renaming is still allowed.
    $this->put(route('admin.sections.update', $section), sectionPayload(['name' => 'Renamed']))
        ->assertSessionHasNoErrors();

    expect($section->refresh()->name)->toBe('Renamed');
});

test('grade level is locked while teacher assignments use the section', function () {
    $section = Section::factory()->forGrade(GradeLevel::Grade3)->create();
    TeacherAssignment::factory()->create(['section_id' => $section->id]);

    $this->actingAs($this->admin)
        ->put(route('admin.sections.update', $section), sectionPayload([
            'name' => $section->name,
            'grade_level' => '4',
        ]))
        ->assertSessionHasErrors('grade_level');

    expect($section->refresh()->grade_level)->toBe(GradeLevel::Grade3);
});

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

test('an unused section can be deleted', function () {
    $section = Section::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.sections.destroy', $section))
        ->assertRedirect();

    $this->assertModelMissing($section);
});

test('a section that still has students cannot be deleted', function () {
    $section = Section::factory()->create();
    $enrollment = Enrollment::factory()->create(['section_id' => $section->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.sections.destroy', $section))
        ->assertRedirect();

    $this->assertModelExists($section);
    expect($enrollment->refresh()->section_id)->toBe($section->id);
});

test('a section that still has teacher assignments cannot be deleted', function () {
    $section = Section::factory()->create();
    TeacherAssignment::factory()->create(['section_id' => $section->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.sections.destroy', $section))
        ->assertRedirect();

    $this->assertModelExists($section);
    $this->assertDatabaseCount('teacher_assignments', 1);
});
