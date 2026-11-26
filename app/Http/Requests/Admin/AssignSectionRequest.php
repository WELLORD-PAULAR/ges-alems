<?php

namespace App\Http\Requests\Admin;

use App\Services\SectionAssigner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Assign one student to a section. A null `section_id` removes the student
 * from their section. Whether the section suits the student is decided by
 * {@see SectionAssigner}.
 */
class AssignSectionRequest extends FormRequest
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
        return [
            'section_id' => ['present', 'nullable', 'integer'],
        ];
    }
}
