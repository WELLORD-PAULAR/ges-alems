<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\Teacher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Used for creating and updating a teacher.
 *
 * Only creating chooses a login account: either link an existing account that
 * has the Teacher role (`account_mode` = existing) or create one in the same
 * step (`account_mode` = new). Updating never changes the linked account.
 */
class TeacherRequest extends FormRequest
{
    public const ACCOUNT_EXISTING = 'existing';

    public const ACCOUNT_NEW = 'new';

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teacher = $this->route('teacher');
        $isUpdate = $teacher instanceof Teacher;

        $rules = [
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            // Optional, but unique when present (blank is stored as null).
            'employee_no' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('teachers', 'employee_no')->ignore(
                    $teacher instanceof Teacher ? $teacher->id : null,
                ),
            ],
        ];

        if ($isUpdate) {
            return $rules;
        }

        $rules['account_mode'] = ['required', Rule::in([self::ACCOUNT_EXISTING, self::ACCOUNT_NEW])];

        if ($this->input('account_mode') === self::ACCOUNT_NEW) {
            $rules['email'] = ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')];
            $rules['password'] = ['required', 'string', Password::defaults()];
        } else {
            $rules['user_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::Teacher->value),
                Rule::unique('teachers', 'user_id'),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_no.unique' => 'That employee number belongs to another teacher.',
            'user_id.required' => 'Choose a teacher account to link.',
            'user_id.exists' => 'Choose an account that has the Teacher role.',
            'user_id.unique' => 'That account is already linked to a teacher.',
            'email.unique' => 'An account with that email already exists.',
            'account_mode.required' => 'Choose whether to link an account or create a login.',
        ];
    }
}
