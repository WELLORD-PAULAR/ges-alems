<?php

namespace App\Http\Requests\Admin;

use App\Services\SectionAssigner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the shape of the payload is checked here. Whether each row is valid
 * (the enrollment exists, the section matches its grade and year) is reported
 * per row by {@see SectionAssigner}, so one bad row never
 * rejects the whole request.
 */
class BulkAssignSectionsRequest extends FormRequest
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
            'assignments' => ['required', 'array', 'min:1', 'max:500'],
            'assignments.*' => ['array'],
            'assignments.*.enrollment_id' => ['required', 'integer', 'distinct'],
            // `present` so a missing key is never mistaken for "remove".
            'assignments.*.section_id' => ['present', 'nullable', 'integer'],
        ];
    }
}
