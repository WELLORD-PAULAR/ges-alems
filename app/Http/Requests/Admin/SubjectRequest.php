<?php

namespace App\Http\Requests\Admin;

use App\Enums\Term;
use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Used for both creating and updating a subject. On update the route's
 * `subject` binding is ignored by the unique-code rule.
 */
class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Codes are stored upper-case so "eng" and "ENG" cannot both exist,
     * whatever collation the database uses.
     */
    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => Str::upper(trim($code))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $subject = $this->route('subject');

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('subjects', 'code')->ignore(
                    $subject instanceof Subject ? $subject->id : null,
                ),
            ],
            'name' => ['required', 'string', 'max:100'],
            // Blank means "offered in every term". Summer is not assignable.
            'offered_term' => [
                'nullable',
                'integer',
                Rule::in(array_map(
                    fn (Term $term): int => $term->value,
                    Term::assignable(),
                )),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'That subject code is already in use.',
            'offered_term.in' => 'Choose the 1st, 2nd or 3rd term, or leave it blank for all terms.',
        ];
    }
}
