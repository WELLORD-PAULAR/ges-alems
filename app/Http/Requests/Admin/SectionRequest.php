<?php

namespace App\Http\Requests\Admin;

use App\Enums\GradeLevel;
use App\Models\Section;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both creating and updating a section. On update the route's
 * `section` binding is ignored by the unique-name rule.
 */
class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $section = $this->route('section');

        return [
            // A name is unique within its school year, as in the database.
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sections', 'name')
                    ->where('school_year', $this->input('school_year'))
                    ->ignore($section instanceof Section ? $section->id : null),
            ],
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
            'school_year' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{4}$/',
                // 2026-2027 is fine, 2026-2028 is not.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value)
                        && preg_match('/^(\d{4})-(\d{4})$/', $value, $years) === 1
                        && (int) $years[2] !== (int) $years[1] + 1) {
                        $fail('The school year must cover two consecutive years, such as 2026-2027.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A section with that name already exists for that school year.',
            'school_year.regex' => 'Use the school year format YYYY-YYYY, such as 2026-2027.',
        ];
    }
}
