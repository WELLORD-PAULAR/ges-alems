<?php

use App\Enums\GradeLevel;
use App\Enums\VerificationStatus;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->year = (string) config('school.current_school_year');
});

/**
 * An enrollment for a named student, in the current school year unless told otherwise.
 *
 * @param  array<string, mixed>  $overrides
 */
function enrolled(string $first, string $last, GradeLevel $grade, array $overrides = []): Enrollment
{
    return Enrollment::factory()
        ->for(Student::factory()->state([
            'first_name' => $first,
            'last_name' => $last,
            'middle_name' => null,
            'extension' => null,
        ]))
        ->create(array_merge(['grade_level' => $grade], $overrides));
}

/*
|--------------------------------------------------------------------------
| The sectioning screen
|--------------------------------------------------------------------------
*/

test('the screen opens on kindergarten and sorts students by last then first name', function () {
    enrolled('Ben', 'Zamora', GradeLevel::Kindergarten);
    enrolled('Zoe', 'Abad', GradeLevel::Kindergarten);
    enrolled('Amy', 'Abad', GradeLevel::Kindergarten);
    enrolled('Gina', 'Cruz', GradeLevel::Grade1);
    enrolled('Old', 'Student', GradeLevel::Kindergarten, ['school_year' => '2020-2021']);

    $this->actingAs($this->admin)
        ->get(route('admin.sectioning.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/sectioning/Index')
            ->where('schoolYear', $this->year)
            ->where('grade', 'K')
            ->has('students', 3)
            ->where('students.0.full_name', 'Amy Abad')
            ->where('students.1.full_name', 'Zoe Abad')
            ->where('students.2.full_name', 'Ben Zamora'));
});

test('a grade tab shows only that grade, and an unknown grade falls back to kindergarten', function () {
    enrolled('Gina', 'Cruz', GradeLevel::Grade3);
    enrolled('Kai', 'Reyes', GradeLevel::Kindergarten);

    $this->actingAs($this->admin)
        ->get(route('admin.sectioning.index', ['grade' => '3']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('grade', '3')
            ->has('students', 1)
            ->where('students.0.full_name', 'Gina Cruz'));

    $this->get(route('admin.sectioning.index', ['grade' => 'banana']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('grade', 'K')
            ->where('students.0.full_name', 'Kai Reyes'));
});

test('students can be filtered by verification status', function () {
    enrolled('Ver', 'Ified', GradeLevel::Grade1, ['verification' => VerificationStatus::Verified]);
    enrolled('Pro', 'Cessing', GradeLevel::Grade1, ['verification' => VerificationStatus::Processing]);

    $this->actingAs($this->admin)
        ->get(route('admin.sectioning.index', ['grade' => '1', 'status' => 'VERIFIED']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'VERIFIED')
            ->has('students', 1)
            ->where('students.0.full_name', 'Ver Ified')
            ->where('students.0.verification.label', 'Verified'));

    // An unknown status is ignored rather than breaking the page.
    $this->get(route('admin.sectioning.index', ['grade' => '1', 'status' => 'nonsense']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', null)
            ->has('students', 2));
});

test('only the sections for that grade and school year are offered', function () {
    Section::factory()->forGrade(GradeLevel::Grade3)->create(['name' => '3-Peace']);
    Section::factory()->forGrade(GradeLevel::Grade3)->create(['name' => '3-Faith']);
    Section::factory()->forGrade(GradeLevel::Grade4)->create(['name' => '4-Charity']);
    Section::factory()->forGrade(GradeLevel::Grade3)->forSchoolYear('2020-2021')->create(['name' => '3-Old']);

    $this->actingAs($this->admin)
        ->get(route('admin.sectioning.index', ['grade' => '3']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections', fn ($sections) => collect($sections)->pluck('name')->all() === ['3-Faith', '3-Peace']));
});

test('each grade tab shows how many students it has in that school year', function () {
    enrolled('A', 'One', GradeLevel::Kindergarten);
    enrolled('B', 'Two', GradeLevel::Kindergarten);
    enrolled('C', 'Three', GradeLevel::Grade3);
    enrolled('D', 'Four', GradeLevel::Grade3, ['school_year' => '2020-2021']);

    $this->actingAs($this->admin)
        ->get(route('admin.sectioning.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('gradeCounts.K', 2)
            ->where('gradeCounts.3', 1)
            ->where('gradeCounts.6', 0));
});

/*
|--------------------------------------------------------------------------
| Assigning one student
|--------------------------------------------------------------------------
*/

test('a student can be assigned to a section of their grade and year', function () {
    $section = Section::factory()->forGrade(GradeLevel::Grade3)->create();
    $enrollment = enrolled('Gina', 'Cruz', GradeLevel::Grade3);

    $this->actingAs($this->admin)
        ->from(route('admin.sectioning.index', ['grade' => '3']))
        ->put(route('admin.sectioning.assign', $enrollment), ['section_id' => $section->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.sectioning.index', ['grade' => '3']));

    expect($enrollment->refresh()->section_id)->toBe($section->id);
});

test('a section of another grade is rejected', function () {
    $section = Section::factory()->forGrade(GradeLevel::Grade4)->create(['name' => '4-Charity']);
    $enrollment = enrolled('Gina', 'Cruz', GradeLevel::Grade3);

    $this->actingAs($this->admin)
        ->put(route('admin.sectioning.assign', $enrollment), ['section_id' => $section->id])
        ->assertSessionHasErrors('section_id');

    expect($enrollment->refresh()->section_id)->toBeNull();
});

test('a section of another school year is rejected', function () {
    $section = Section::factory()->forGrade(GradeLevel::Grade3)->forSchoolYear('2020-2021')->create();
    $enrollment = enrolled('Gina', 'Cruz', GradeLevel::Grade3);

    $this->actingAs($this->admin)
        ->put(route('admin.sectioning.assign', $enrollment), ['section_id' => $section->id])
        ->assertSessionHasErrors('section_id');

    expect($enrollment->refresh()->section_id)->toBeNull();
});

test('a student can be removed from their section', function () {
    $section = Section::factory()->forGrade(GradeLevel::Grade3)->create();
    $enrollment = enrolled('Gina', 'Cruz', GradeLevel::Grade3, ['section_id' => $section->id]);

    $this->actingAs($this->admin)
        ->put(route('admin.sectioning.assign', $enrollment), ['section_id' => null])
        ->assertSessionHasNoErrors();

    expect($enrollment->refresh()->section_id)->toBeNull();
});

test('a missing section is rejected, and the section id must be sent', function () {
    $enrollment = enrolled('Gina', 'Cruz', GradeLevel::Grade3);

    $this->actingAs($this->admin)
        ->put(route('admin.sectioning.assign', $enrollment), ['section_id' => 99999])
        ->assertSessionHasErrors('section_id');

    $this->put(route('admin.sectioning.assign', $enrollment), [])
        ->assertSessionHasErrors('section_id');
});

/*
|--------------------------------------------------------------------------
| Bulk assignment
|--------------------------------------------------------------------------
*/

test('bulk assign saves every row and reports each one', function () {
    $section = Section::factory()->forGrade(GradeLevel::Grade3)->create();
    $first = enrolled('Gina', 'Cruz', GradeLevel::Grade3);
    $second = enrolled('Hal', 'Dizon', GradeLevel::Grade3);

    $this->actingAs($this->admin)
        ->postJson(route('admin.sectioning.bulk'), [
            'assignments' => [
                ['enrollment_id' => $first->id, 'section_id' => $section->id],
                ['enrollment_id' => $second->id, 'section_id' => $section->id],
            ],
        ])
        ->assertOk()
        ->assertJson(['saved' => 2, 'failed' => 0])
        ->assertJsonPath('results.0.ok', true)
        ->assertJsonPath('results.1.ok', true);

    expect($first->refresh()->section_id)->toBe($section->id)
        ->and($second->refresh()->section_id)->toBe($section->id);
});

test('one bad row does not stop the others and is reported, not ignored', function () {
    $grade3 = Section::factory()->forGrade(GradeLevel::Grade3)->create();
    $grade4 = Section::factory()->forGrade(GradeLevel::Grade4)->create(['name' => '4-Charity']);

    $good = enrolled('Gina', 'Cruz', GradeLevel::Grade3);
    $wrongGrade = enrolled('Hal', 'Dizon', GradeLevel::Grade3);
    $placed = enrolled('Ivy', 'Eala', GradeLevel::Grade3, ['section_id' => $grade3->id]);
    $last = enrolled('Jon', 'Fabi', GradeLevel::Grade3);

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.sectioning.bulk'), [
            'assignments' => [
                ['enrollment_id' => $good->id, 'section_id' => $grade3->id],
                ['enrollment_id' => $wrongGrade->id, 'section_id' => $grade4->id],
                ['enrollment_id' => 99999, 'section_id' => $grade3->id],
                ['enrollment_id' => $placed->id, 'section_id' => null],
                ['enrollment_id' => $last->id, 'section_id' => $grade3->id],
            ],
        ])
        ->assertOk()
        ->assertJson(['saved' => 3, 'failed' => 2]);

    // One result per row, in the order sent.
    $response->assertJsonPath('results.0.enrollment_id', $good->id)
        ->assertJsonPath('results.0.ok', true)
        ->assertJsonPath('results.1.enrollment_id', $wrongGrade->id)
        ->assertJsonPath('results.1.ok', false)
        ->assertJsonPath('results.2.enrollment_id', 99999)
        ->assertJsonPath('results.2.ok', false)
        ->assertJsonPath('results.3.ok', true)
        ->assertJsonPath('results.4.ok', true);

    expect($response->json('results.1.message'))->toContain('4-Charity')->toContain('Grade 4')->toContain('Grade 3');

    expect($good->refresh()->section_id)->toBe($grade3->id)
        ->and($wrongGrade->refresh()->section_id)->toBeNull()
        ->and($placed->refresh()->section_id)->toBeNull()
        ->and($last->refresh()->section_id)->toBe($grade3->id);
});

test('bulk assign rejects a malformed request', function (array $payload) {
    $this->actingAs($this->admin)
        ->postJson(route('admin.sectioning.bulk'), $payload)
        ->assertUnprocessable();
})->with([
    'nothing to save' => [['assignments' => []]],
    'no assignments key' => [[]],
    'section id missing' => [['assignments' => [['enrollment_id' => 1]]]],
    'same student twice' => [['assignments' => [
        ['enrollment_id' => 1, 'section_id' => null],
        ['enrollment_id' => 1, 'section_id' => null],
    ]]],
]);
