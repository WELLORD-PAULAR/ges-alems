<?php

namespace App\Models;

use App\Enums\Term;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $offered_term
 * @property string|null $legacy_key
 */
#[Fillable(['code', 'name', 'offered_term', 'legacy_key'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    /**
     * Subjects taught in the given term: those offered in that term plus the
     * ones offered in every term (a null `offered_term`).
     *
     * @param  Builder<Subject>  $query
     * @return Builder<Subject>
     */
    public function scopeOfferedIn(Builder $query, Term|int $term): Builder
    {
        $value = $term instanceof Term ? $term->value : $term;

        return $query->where(function (Builder $query) use ($value): void {
            $query->where('offered_term', $value)->orWhereNull('offered_term');
        });
    }

    public function isOfferedEveryTerm(): bool
    {
        return $this->offered_term === null;
    }

    /**
     * Human label for the offered term, read from `config/school.php`.
     */
    public function termLabel(): string
    {
        if ($this->offered_term === null) {
            return 'All terms (year-round)';
        }

        return (string) config("school.terms.{$this->offered_term}.label", 'Unknown term');
    }

    /**
     * @return HasMany<TeacherAssignment, $this>
     */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
