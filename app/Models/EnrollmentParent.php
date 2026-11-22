<?php

namespace App\Models;

use Database\Factories\EnrollmentParentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property string|null $emergency_contact
 */
#[Fillable([
    'enrollment_id',
    'legacy_key',
    'father_last_name',
    'father_first_name',
    'father_middle_name',
    'father_contact_number',
    'father_occupation',
    'father_relationship_status',
    'father_communication',
    'mother_last_name',
    'mother_first_name',
    'mother_middle_name',
    'mother_contact_number',
    'mother_occupation',
    'mother_relationship_status',
    'mother_communication',
    'guardian_last_name',
    'guardian_first_name',
    'guardian_middle_name',
    'guardian_contact_number',
    'guardian_occupation',
    'guardian_relationship_status',
    'guardian_communication',
    'emergency_contact',
])]
class EnrollmentParent extends Model
{
    /** @use HasFactory<EnrollmentParentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
