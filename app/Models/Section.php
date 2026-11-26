<?php

namespace App\Models;

use App\Enums\GradeLevel;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property GradeLevel $grade_level
 * @property string $school_year
 * @property string|null $legacy_key
 */
#[Fillable(['name', 'grade_level', 'school_year', 'legacy_key'])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade_level' => GradeLevel::class,
        ];
    }

    /**
     * A student can only be placed in a section of their own grade level and
     * school year.
     */
    public function accepts(Enrollment $enrollment): bool
    {
        return $this->grade_level === $enrollment->grade_level
            && $this->school_year === $enrollment->school_year;
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<TeacherAssignment, $this>
     */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
