<?php

use App\Enums\Role;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function linkExistingPayload(User $account, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ana',
        'middle_name' => 'Dela',
        'last_name' => 'Cruz',
        'employee_no' => 'EMP-1001',
        'account_mode' => 'existing',
        'user_id' => $account->id,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newLoginPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ben',
        'middle_name' => null,
        'last_name' => 'Reyes',
        'employee_no' => 'EMP-2002',
        'account_mode' => 'new',
        'email' => 'ben.reyes@school.test',
        'password' => 'Correct-Horse-9!',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

test('guests are redirected to the login page', function () {
    $this->get(route('admin.teachers.index'))
        ->assertRedirect(route('login'));
});

test('teachers cannot use any teacher route', function () {
    $teacherUser = User::factory()->teacher()->create();
    $teacher = Teacher::factory()->create();
    $free = User::factory()->teacher()->create();

    $this->actingAs($teacherUser);

    $this->get(route('admin.teachers.index'))->assertForbidden();
    $this->post(route('admin.teachers.store'), linkExistingPayload($free))->assertForbidden();
    $this->put(route('admin.teachers.update', $teacher), ['first_name' => 'X', 'last_name' => 'Y'])->assertForbidden();
    $this->delete(route('admin.teachers.destroy', $teacher))->assertForbidden();

    expect(Teacher::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Listing
|--------------------------------------------------------------------------
*/

test('teachers are listed by last name then first name with their full name', function () {
    Teacher::factory()->create(['first_name' => 'Zed', 'middle_name' => null, 'last_name' => 'Adams']);
    Teacher::factory()->create(['first_name' => 'Ann', 'middle_name' => 'Lee', 'last_name' => 'Zulu']);
    Teacher::factory()->create(['first_name' => 'Amy', 'middle_name' => null, 'last_name' => 'Zulu']);

    $this->actingAs($this->admin)
        ->get(route('admin.teachers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/teachers/Index')
            ->has('teachers', 3)
            ->where('teachers.0.full_name', 'Zed Adams')
            ->where('teachers.1.full_name', 'Amy Zulu')
            ->where('teachers.2.full_name', 'Ann Lee Zulu'));
});

test('only active unlinked teacher accounts can be picked', function () {
    $free = User::factory()->teacher()->create(['name' => 'Free Account']);
    User::factory()->teacher()->inactive()->create();
    User::factory()->admin()->create();
    Teacher::factory()->create(); // its account is already linked

    $this->actingAs($this->admin)
        ->get(route('admin.teachers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.id', $free->id)
            ->where('accounts.0.email', $free->email));
});

test('the full name skips blank parts', function () {
    expect((new Teacher(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Cruz']))->fullName())
        ->toBe('Ana Cruz')
        ->and((new Teacher(['first_name' => 'Ana', 'middle_name' => '', 'last_name' => 'Cruz']))->fullName())
        ->toBe('Ana Cruz')
        ->and((new Teacher(['first_name' => 'Ana', 'middle_name' => 'Dela', 'last_name' => 'Cruz']))->fullName())
        ->toBe('Ana Dela Cruz');
});

/*
|--------------------------------------------------------------------------
| Create: link an existing account
|--------------------------------------------------------------------------
*/

test('an admin can create a teacher linked to an existing teacher account', function () {
    $account = User::factory()->teacher()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), linkExistingPayload($account))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.teachers.index'));

    $this->assertDatabaseHas('teachers', [
        'user_id' => $account->id,
        'first_name' => 'Ana',
        'middle_name' => 'Dela',
        'last_name' => 'Cruz',
        'employee_no' => 'EMP-1001',
    ]);
});

test('an account without the teacher role cannot be linked', function () {
    $adminAccount = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), linkExistingPayload($adminAccount))
        ->assertSessionHasErrors('user_id');

    expect(Teacher::query()->count())->toBe(0);
});

test('an account that is already linked cannot be linked again', function () {
    $existing = Teacher::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), linkExistingPayload($existing->user, ['employee_no' => 'EMP-9']))
        ->assertSessionHasErrors('user_id');

    expect(Teacher::query()->count())->toBe(1);
});

test('linking a missing account is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), [
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'account_mode' => 'existing',
            'user_id' => 99999,
        ])
        ->assertSessionHasErrors('user_id');
});

/*
|--------------------------------------------------------------------------
| Create: make the login at the same time
|--------------------------------------------------------------------------
*/

test('an admin can create the login and the teacher together', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), newLoginPayload(['email' => ' Ben.Reyes@School.test ']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.teachers.index'));

    $user = User::query()->where('email', 'ben.reyes@school.test')->firstOrFail();

    expect($user->role)->toBe(Role::Teacher)
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->name)->toBe('Ben Reyes')
        ->and(Hash::check('Correct-Horse-9!', $user->password))->toBeTrue();

    $this->assertDatabaseHas('teachers', [
        'user_id' => $user->id,
        'first_name' => 'Ben',
        'last_name' => 'Reyes',
    ]);
});

test('no login is created when the teacher details are invalid', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), newLoginPayload(['first_name' => '']))
        ->assertSessionHasErrors('first_name');

    expect(User::query()->where('email', 'ben.reyes@school.test')->exists())->toBeFalse()
        ->and(Teacher::query()->count())->toBe(0);
});

test('a login needs an unused email and a valid password', function () {
    User::factory()->create(['email' => 'taken@school.test']);

    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), newLoginPayload(['email' => 'taken@school.test']))
        ->assertSessionHasErrors('email');

    $this->post(route('admin.teachers.store'), newLoginPayload(['password' => 'short']))
        ->assertSessionHasErrors('password');

    $this->post(route('admin.teachers.store'), newLoginPayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors('email');

    expect(Teacher::query()->count())->toBe(0);
});

test('an account option is required when creating', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), ['first_name' => 'Ana', 'last_name' => 'Cruz'])
        ->assertSessionHasErrors('account_mode');
});

/*
|--------------------------------------------------------------------------
| Validation shared by create and update
|--------------------------------------------------------------------------
*/

test('first and last name are required', function () {
    $account = User::factory()->teacher()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), linkExistingPayload($account, ['first_name' => '', 'last_name' => '']))
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

test('a duplicate employee number is rejected', function () {
    Teacher::factory()->create(['employee_no' => 'EMP-1001']);
    $account = User::factory()->teacher()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), linkExistingPayload($account))
        ->assertSessionHasErrors('employee_no');

    expect(Teacher::query()->count())->toBe(1);
});

test('several teachers may have no employee number', function () {
    $first = User::factory()->teacher()->create();
    $second = User::factory()->teacher()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.teachers.store'), linkExistingPayload($first, ['employee_no' => '']))
        ->assertSessionHasNoErrors();

    $this->post(route('admin.teachers.store'), linkExistingPayload($second, ['employee_no' => null]))
        ->assertSessionHasNoErrors();

    expect(Teacher::query()->whereNull('employee_no')->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

test('an admin can update a teacher and keep their own employee number', function () {
    $teacher = Teacher::factory()->create(['employee_no' => 'EMP-1001', 'first_name' => 'Ana']);

    $this->actingAs($this->admin)
        ->put(route('admin.teachers.update', $teacher), [
            'first_name' => 'Anna',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'employee_no' => 'EMP-1001',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.teachers.index'));

    $teacher->refresh();

    expect($teacher->first_name)->toBe('Anna')
        ->and($teacher->middle_name)->toBe('Dela');
});

test('a middle name and employee number can be cleared', function () {
    // The old app ignored blank values on update, so this was impossible.
    $teacher = Teacher::factory()->create(['middle_name' => 'Dela', 'employee_no' => 'EMP-1001']);

    $this->actingAs($this->admin)
        ->put(route('admin.teachers.update', $teacher), [
            'first_name' => $teacher->first_name,
            'middle_name' => '',
            'last_name' => $teacher->last_name,
            'employee_no' => '',
        ])
        ->assertSessionHasNoErrors();

    $teacher->refresh();

    expect($teacher->middle_name)->toBeNull()
        ->and($teacher->employee_no)->toBeNull();
});

test('updating to another teacher\'s employee number is rejected', function () {
    Teacher::factory()->create(['employee_no' => 'EMP-1001']);
    $teacher = Teacher::factory()->create(['employee_no' => 'EMP-2002']);

    $this->actingAs($this->admin)
        ->put(route('admin.teachers.update', $teacher), [
            'first_name' => $teacher->first_name,
            'last_name' => $teacher->last_name,
            'employee_no' => 'EMP-1001',
        ])
        ->assertSessionHasErrors('employee_no');

    expect($teacher->refresh()->employee_no)->toBe('EMP-2002');
});

test('updating never changes the linked account', function () {
    $teacher = Teacher::factory()->create();
    $originalUserId = $teacher->user_id;
    $other = User::factory()->teacher()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.teachers.update', $teacher), [
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'user_id' => $other->id,
        ])
        ->assertSessionHasNoErrors();

    expect($teacher->refresh()->user_id)->toBe($originalUserId);
});

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

test('a teacher without assignments can be deleted and the account stays', function () {
    $teacher = Teacher::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.teachers.destroy', $teacher))
        ->assertRedirect(route('admin.teachers.index'));

    $this->assertModelMissing($teacher);
    $this->assertModelExists($teacher->user);
});

test('a teacher with assignments cannot be deleted', function () {
    $teacher = Teacher::factory()->create();
    TeacherAssignment::factory()->create(['teacher_id' => $teacher->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.teachers.destroy', $teacher))
        ->assertRedirect(route('admin.teachers.index'));

    $this->assertModelExists($teacher);
    $this->assertDatabaseCount('teacher_assignments', 1);
});
