<?php

namespace App\Models;

use App\Enums\GradeLevel;
use App\Enums\VerificationStatus;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $legacy_key
 * @property int $student_id
 * @property string $school_year
 * @property GradeLevel $grade_level
 * @property int|null $section_id
 * @property string|null $lrn
 * @property string|null $learning_classification
 * @property string|null $last_grade_level_completed
 * @property string|null $last_school_year_completed
 * @property string|null $last_school_attended
 * @property string|null $last_school_id
 * @property string|null $attended_early_learning_program_name
 * @property string|null $four_ps_household_number
 * @property list<string>|null $learning_modality
 * @property VerificationStatus $verification
 * @property int|null $encoded_by
 */
#[Fillable([
    'legacy_key',
    'student_id',
    'school_year',
    'grade_level',
    'section_id',
    'lrn',
    'learning_classification',
    'last_grade_level_completed',
    'last_school_year_completed',
    'last_school_attended',
    'last_school_id',
    'attended_early_learning_program_name',
    'four_ps_household_number',
    'learning_modality',
    'verification',
    'encoded_by',
])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade_level' => GradeLevel::class,
            'learning_modality' => 'array',
            'verification' => VerificationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function encodedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    /**
     * @return HasOne<EnrollmentAddress, $this>
     */
    public function address(): HasOne
    {
        return $this->hasOne(EnrollmentAddress::class);
    }

    /**
     * @return HasOne<EnrollmentParent, $this>
     */
    public function parents(): HasOne
    {
        return $this->hasOne(EnrollmentParent::class);
    }

    /**
     * @return HasOne<EnrollmentMedical, $this>
     */
    public function medical(): HasOne
    {
        return $this->hasOne(EnrollmentMedical::class);
    }

    /**
     * @return HasOne<EnrollmentSpecialNeed, $this>
     */
    public function specialNeeds(): HasOne
    {
        return $this->hasOne(EnrollmentSpecialNeed::class);
    }
}
