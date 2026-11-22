<?php

namespace App\Models;

use App\Enums\Sex;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $legacy_key
 * @property string|null $psa_bcn
 * @property string $last_name
 * @property string $first_name
 * @property string|null $middle_name
 * @property string|null $extension
 * @property Carbon|null $birth_date
 * @property Sex|null $sex
 * @property string|null $place_of_birth
 * @property int|null $mother_tongue_id
 * @property int|null $religion_id
 * @property int|null $indigenous_group_id
 */
#[Fillable([
    'legacy_key',
    'psa_bcn',
    'last_name',
    'first_name',
    'middle_name',
    'extension',
    'birth_date',
    'sex',
    'place_of_birth',
    'mother_tongue_id',
    'religion_id',
    'indigenous_group_id',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'sex' => Sex::class,
        ];
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->extension,
        ])));
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return BelongsTo<MotherTongue, $this>
     */
    public function motherTongue(): BelongsTo
    {
        return $this->belongsTo(MotherTongue::class);
    }

    /**
     * @return BelongsTo<Religion, $this>
     */
    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religion::class);
    }

    /**
     * @return BelongsTo<IndigenousGroup, $this>
     */
    public function indigenousGroup(): BelongsTo
    {
        return $this->belongsTo(IndigenousGroup::class);
    }
}
