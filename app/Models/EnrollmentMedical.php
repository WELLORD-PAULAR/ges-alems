<?php

namespace App\Models;

use Database\Factories\EnrollmentMedicalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property list<string>|null $medical_conditions
 * @property list<string>|null $chronic_conditions
 * @property Carbon|null $surgery_date
 * @property bool $exposed_to_covid
 */
#[Fillable([
    'enrollment_id',
    'legacy_key',
    'allergy_medicine',
    'allergy_pollen',
    'allergy_food',
    'allergy_others',
    'medical_conditions',
    'medical_conditions_others',
    'surgery_date',
    'surgery_hospital',
    'surgery_body_part',
    'medication_type',
    'medication_dosage_schedule',
    'chronic_conditions',
    'cancer_type',
    'chronic_conditions_others',
    'exposed_to_covid',
    'pertinent_information',
])]
class EnrollmentMedical extends Model
{
    /** @use HasFactory<EnrollmentMedicalFactory> */
    use HasFactory;

    /**
     * The migration uses the legacy singular table name.
     *
     * @var string
     */
    protected $table = 'enrollment_medical';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'medical_conditions' => 'array',
            'chronic_conditions' => 'array',
            'surgery_date' => 'date',
            'exposed_to_covid' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
