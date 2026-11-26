<?php

namespace App\Http\Requests\Admin;

use App\Enums\Term;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Rules\SchoolYear;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Used for both creating and updating a teacher assignment.
 *
 * Beyond the field rules, three things are checked once the fields are valid:
 * the section belongs to the assignment's school year, the subject is offered
 * in the assignment's term, and the same five-part combination does not already
 * exist. The database's unique index is the last line of defence for the third.
 */
class AssignmentRequest extends FormRequest
{
    public const DUPLICATE_MESSAGE = 'That teacher already has this subject in this section for that school year and term.';

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', Rule::exists('teachers', 'id')],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'school_year' => ['required', 'string', new SchoolYear],
            // Summer is a label on the calendar, not a term anyone is assigned to.
            'term' => [
                'required',
                'integer',
                Rule::in(array_map(fn (Term $term): int => $term->value, Term::assignable())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'teacher_id.exists' => 'Choose a teacher from the list.',
            'subject_id.exists' => 'Choose a subject from the list.',
            'section_id.exists' => 'Choose a section from the list.',
            'term.in' => 'Choose the 1st, 2nd or 3rd term.',
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            // Only cross-check once every field is individually valid.
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $schoolYear = $this->string('school_year')->toString();
            $term = $this->integer('term');

            $section = Section::query()->find($this->integer('section_id'));
            $subject = Subject::query()->find($this->integer('subject_id'));

            if ($section !== null && $section->school_year !== $schoolYear) {
                $validator->errors()->add('section_id', sprintf(
                    '%s belongs to school year %s, not %s.',
                    $section->name,
                    $section->school_year,
                    $schoolYear,
                ));
            }

            if ($subject !== null && $subject->offered_term !== null && (int) $subject->offered_term !== $term) {
                $validator->errors()->add('subject_id', sprintf(
                    '%s is only offered in the %s.',
                    $subject->name,
                    $subject->termLabel(),
                ));
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $current = $this->route('assignment');

            $duplicate = TeacherAssignment::query()
                ->forTeacher($this->integer('teacher_id'))
                ->forSubject($this->integer('subject_id'))
                ->forSection($this->integer('section_id'))
                ->forSchoolYear($schoolYear)
                ->forTerm($term)
                ->when(
                    $current instanceof TeacherAssignment,
                    fn ($query) => $query->whereKeyNot($current instanceof TeacherAssignment ? $current->id : 0),
                )
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('assignment', self::DUPLICATE_MESSAGE);
            }
        }];
    }
}
