<?php

namespace App\Models;

use Database\Factories\EnrollmentAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $enrollment_id
 */
#[Fillable([
    'enrollment_id',
    'legacy_key',
    'current_house_number',
    'current_street_name',
    'current_barangay',
    'current_municipality',
    'current_province',
    'current_country',
    'current_zip_code',
    'current_address_status',
    'permanent_house_number',
    'permanent_street_name',
    'permanent_barangay',
    'permanent_municipality',
    'permanent_province',
    'permanent_country',
    'permanent_zip_code',
    'permanent_address_status',
])]
class EnrollmentAddress extends Model
{
    /** @use HasFactory<EnrollmentAddressFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
