<?php

use App\Enums\Role;
use App\Enums\Term;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

test('guests are redirected to the login page', function () {
    $this->get(route('admin.subjects.index'))
        ->assertRedirect(route('login'));
});

test('teachers cannot use any subject route', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $subject = Subject::factory()->create();

    $this->actingAs($teacher);

    $this->get(route('admin.subjects.index'))->assertForbidden();
    $this->post(route('admin.subjects.store'), ['code' => 'X', 'name' => 'X'])->assertForbidden();
    $this->put(route('admin.subjects.update', $subject), ['code' => 'X', 'name' => 'X'])->assertForbidden();
    $this->delete(route('admin.subjects.destroy', $subject))->assertForbidden();

    expect(Subject::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Listing
|--------------------------------------------------------------------------
*/

test('admins see subjects ordered by name with term labels from config', function () {
    Subject::factory()->create(['code' => 'SCI', 'name' => 'Science', 'offered_term' => 2]);
    Subject::factory()->create(['code' => 'ENG', 'name' => 'English', 'offered_term' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.subjects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/Index')
            ->has('subjects', 2)
            ->where('subjects.0.code', 'ENG')
            ->where('subjects.0.term_label', 'All terms (year-round)')
            ->where('subjects.1.code', 'SCI')
            ->where('subjects.1.term_label', config('school.terms.2.label'))
            ->has('terms', 3));
});

test('summer is not offered as a term choice', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.subjects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('terms', fn ($terms) => collect($terms)->pluck('value')->all() === [1, 2, 3]));
});

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

test('an admin can create a subject and the code is stored upper-case', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.subjects.store'), [
            'code' => ' eng-1 ',
            'name' => 'English',
            'offered_term' => 1,
        ])
        ->assertRedirect(route('admin.subjects.index'));

    $this->assertDatabaseHas('subjects', [
        'code' => 'ENG-1',
        'name' => 'English',
        'offered_term' => 1,
    ]);
});

test('a blank term creates a subject offered in every term', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.subjects.store'), [
            'code' => 'MATH',
            'name' => 'Mathematics',
            'offered_term' => '',
        ])
        ->assertSessionHasNoErrors();

    expect(Subject::query()->where('code', 'MATH')->firstOrFail()->offered_term)->toBeNull();
});

test('code and name are required', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.subjects.store'), [])
        ->assertSessionHasErrors(['code', 'name']);

    expect(Subject::query()->count())->toBe(0);
});

test('a duplicate subject code is rejected, whatever its case', function (string $code) {
    Subject::factory()->create(['code' => 'MATH']);

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.store'), ['code' => $code, 'name' => 'Another Math'])
        ->assertSessionHasErrors('code');

    expect(Subject::query()->count())->toBe(1);
})->with(['MATH', 'math', ' Math ']);

test('only terms 1 to 3 or blank are accepted', function (mixed $term) {
    $this->actingAs($this->admin)
        ->post(route('admin.subjects.store'), [
            'code' => 'ART',
            'name' => 'Arts',
            'offered_term' => $term,
        ])
        ->assertSessionHasNoErrors();

    expect(Subject::query()->count())->toBe(1);
})->with([1, 2, 3, null, '']);

test('summer and other invalid terms are rejected', function (mixed $term) {
    $this->actingAs($this->admin)
        ->post(route('admin.subjects.store'), [
            'code' => 'ART',
            'name' => 'Arts',
            'offered_term' => $term,
        ])
        ->assertSessionHasErrors('offered_term');

    expect(Subject::query()->count())->toBe(0);
})->with([Term::Summer->value, 0, 5, 'x']);

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

test('an admin can update a subject and keep its own code', function () {
    $subject = Subject::factory()->create(['code' => 'ENG', 'name' => 'English', 'offered_term' => 1]);

    $this->actingAs($this->admin)
        ->put(route('admin.subjects.update', $subject), [
            'code' => 'ENG',
            'name' => 'English Language',
            'offered_term' => 3,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.subjects.index'));

    $subject->refresh();

    expect($subject->name)->toBe('English Language')
        ->and($subject->offered_term)->toBe(3);
});

test('a subject can be switched back to every term', function () {
    // The old app ignored a blank term on update, so this was impossible.
    $subject = Subject::factory()->create(['code' => 'SCI', 'offered_term' => 2]);

    $this->actingAs($this->admin)
        ->put(route('admin.subjects.update', $subject), [
            'code' => 'SCI',
            'name' => $subject->name,
            'offered_term' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($subject->refresh()->offered_term)->toBeNull();
});

test('updating to another subject\'s code is rejected', function () {
    Subject::factory()->create(['code' => 'MATH']);
    $subject = Subject::factory()->create(['code' => 'SCI']);

    $this->actingAs($this->admin)
        ->put(route('admin.subjects.update', $subject), [
            'code' => 'math',
            'name' => $subject->name,
        ])
        ->assertSessionHasErrors('code');

    expect($subject->refresh()->code)->toBe('SCI');
});

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

test('an unassigned subject can be deleted', function () {
    $subject = Subject::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.subjects.destroy', $subject))
        ->assertRedirect(route('admin.subjects.index'));

    $this->assertModelMissing($subject);
});

test('a subject assigned to a teacher cannot be deleted', function () {
    $subject = Subject::factory()->create();
    TeacherAssignment::factory()->create(['subject_id' => $subject->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.subjects.destroy', $subject))
        ->assertRedirect(route('admin.subjects.index'));

    $this->assertModelExists($subject);
    $this->assertDatabaseCount('teacher_assignments', 1);
});

/*
|--------------------------------------------------------------------------
| Model rules
|--------------------------------------------------------------------------
*/

test('listing a term returns that term plus the all-term subjects', function () {
    Subject::factory()->create(['name' => 'Alpha', 'offered_term' => 1]);
    Subject::factory()->create(['name' => 'Bravo', 'offered_term' => 2]);
    Subject::factory()->create(['name' => 'Charlie', 'offered_term' => null]);
    Subject::factory()->create(['name' => 'Delta', 'offered_term' => 3]);

    expect(Subject::offeredIn(2)->orderBy('name')->pluck('name')->all())
        ->toBe(['Bravo', 'Charlie'])
        ->and(Subject::offeredIn(Term::Third)->orderBy('name')->pluck('name')->all())
        ->toBe(['Charlie', 'Delta']);
});

test('term labels come from config/school.php', function () {
    config(['school.terms.2.label' => 'Custom second term']);

    $subject = Subject::factory()->make(['offered_term' => 2]);

    expect($subject->termLabel())->toBe('Custom second term');
});
