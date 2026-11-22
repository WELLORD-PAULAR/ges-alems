<?php

namespace App\Models;

use Database\Factories\EnrollmentSpecialNeedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property list<string>|null $diagnosis
 * @property list<string>|null $manifestations
 * @property bool $has_pwd_id
 */
#[Fillable([
    'enrollment_id',
    'legacy_key',
    'diagnosis',
    'shpcd_subtype',
    'vi_subtype',
    'manifestations',
    'has_pwd_id',
])]
class EnrollmentSpecialNeed extends Model
{
    /** @use HasFactory<EnrollmentSpecialNeedFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diagnosis' => 'array',
            'manifestations' => 'array',
            'has_pwd_id' => 'boolean',
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
