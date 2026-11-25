<?php

namespace App\Models;

use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $employee_no
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string|null $legacy_key
 */
#[Fillable(['user_id', 'employee_no', 'first_name', 'last_name', 'middle_name', 'legacy_key'])]
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use HasFactory;

    /**
     * First, middle and last name joined with single spaces, skipping blanks.
     */
    public function fullName(): string
    {
        return implode(' ', array_filter(
            [$this->first_name, $this->middle_name, $this->last_name],
            fn (?string $part): bool => filled($part),
        ));
    }

    /**
     * Teacher lists sort by last name, then first name.
     *
     * @param  Builder<Teacher>  $query
     * @return Builder<Teacher>
     */
    public function scopeOrderedByName(Builder $query): Builder
    {
        return $query->orderBy('last_name')->orderBy('first_name');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TeacherAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
