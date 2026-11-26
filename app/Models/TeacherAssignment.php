<?php

namespace App\Models;

use App\Enums\Term;
use Database\Factories\TeacherAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $teacher_id
 * @property int $subject_id
 * @property int $section_id
 * @property string $school_year
 * @property Term $term
 * @property string|null $legacy_key
 */
#[Fillable(['teacher_id', 'subject_id', 'section_id', 'school_year', 'term', 'legacy_key'])]
class TeacherAssignment extends Model
{
    /** @use HasFactory<TeacherAssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => Term::class,
        ];
    }

    /**
     * The old list queries, as scopes that can be combined: by school year,
     * by term, by teacher, by subject and by section.
     *
     * @param  Builder<TeacherAssignment>  $query
     * @return Builder<TeacherAssignment>
     */
    public function scopeForSchoolYear(Builder $query, string $schoolYear): Builder
    {
        return $query->where('school_year', $schoolYear);
    }

    /**
     * @param  Builder<TeacherAssignment>  $query
     * @return Builder<TeacherAssignment>
     */
    public function scopeForTerm(Builder $query, Term|int $term): Builder
    {
        return $query->where('term', $term instanceof Term ? $term->value : $term);
    }

    /**
     * @param  Builder<TeacherAssignment>  $query
     * @return Builder<TeacherAssignment>
     */
    public function scopeForTeacher(Builder $query, Teacher|int $teacher): Builder
    {
        return $query->where('teacher_id', $teacher instanceof Teacher ? $teacher->id : $teacher);
    }

    /**
     * @param  Builder<TeacherAssignment>  $query
     * @return Builder<TeacherAssignment>
     */
    public function scopeForSubject(Builder $query, Subject|int $subject): Builder
    {
        return $query->where('subject_id', $subject instanceof Subject ? $subject->id : $subject);
    }

    /**
     * @param  Builder<TeacherAssignment>  $query
     * @return Builder<TeacherAssignment>
     */
    public function scopeForSection(Builder $query, Section|int $section): Builder
    {
        return $query->where('section_id', $section instanceof Section ? $section->id : $section);
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
