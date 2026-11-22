<?php

namespace App\Models;

use Database\Factories\IndigenousGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $legacy_key
 */
#[Fillable(['name', 'legacy_key'])]
class IndigenousGroup extends Model
{
    /** @use HasFactory<IndigenousGroupFactory> */
    use HasFactory;

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
