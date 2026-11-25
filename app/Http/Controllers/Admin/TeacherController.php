<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeacherRequest;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(): Response
    {
        $teachers = Teacher::query()
            ->with('user:id,email')
            ->withCount('assignments')
            ->orderedByName()
            ->get()
            ->map(fn (Teacher $teacher): array => [
                'id' => $teacher->id,
                'full_name' => $teacher->fullName(),
                'first_name' => $teacher->first_name,
                'middle_name' => $teacher->middle_name,
                'last_name' => $teacher->last_name,
                'employee_no' => $teacher->employee_no,
                'email' => $teacher->user->email,
                'assignments_count' => (int) $teacher->getAttribute('assignments_count'),
            ])
            ->values();

        // Active Teacher-role accounts that no teacher record uses yet.
        $accounts = User::query()
            ->where('role', Role::Teacher->value)
            ->where('is_active', true)
            ->whereDoesntHave('teacher')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values();

        return Inertia::render('admin/teachers/Index', [
            'teachers' => $teachers,
            'accounts' => $accounts,
        ]);
    }

    public function store(TeacherRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $profile = [
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'employee_no' => $data['employee_no'] ?? null,
        ];

        // The login and the teacher are created together or not at all.
        DB::transaction(function () use ($data, $profile): void {
            $userId = $data['account_mode'] === TeacherRequest::ACCOUNT_NEW
                ? $this->createLogin($data, (new Teacher($profile))->fullName())->id
                : (int) $data['user_id'];

            Teacher::create($profile + ['user_id' => $userId]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Teacher created.')]);

        return to_route('admin.teachers.index');
    }

    public function update(TeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validated();

        // Blank values are real values: the old app ignored them, so a middle
        // name or employee number could never be cleared. The linked account
        // is deliberately not editable here.
        $teacher->update([
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'employee_no' => $data['employee_no'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Teacher updated.')]);

        return to_route('admin.teachers.index');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        // The foreign key cascades, so the database would silently delete the
        // assignments. Refuse here instead.
        if ($teacher->assignments()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __(':name still has assignments and cannot be deleted.', [
                    'name' => $teacher->fullName(),
                ]),
            ]);

            return to_route('admin.teachers.index');
        }

        // Only the teacher record goes. The login account stays, so it can be
        // linked again or removed separately.
        $teacher->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Teacher deleted.')]);

        return to_route('admin.teachers.index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createLogin(array $data, string $name): User
    {
        $user = (new User)->forceFill([
            'name' => $name,
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => Role::Teacher,
            'is_active' => true,
            // The admin vouches for the address, so no verification step.
            'email_verified_at' => now(),
        ]);
        $user->save();

        return $user;
    }
}
