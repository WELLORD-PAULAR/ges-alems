<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A school year written as two consecutive years: 2026-2027 passes,
 * 2026, 2026-27 and 2026-2028 do not.
 */
class SchoolYear implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{4})$/', $value, $years) !== 1) {
            $fail('Use the school year format YYYY-YYYY, such as 2026-2027.');

            return;
        }

        if ((int) $years[2] !== (int) $years[1] + 1) {
            $fail('The school year must cover two consecutive years, such as 2026-2027.');
        }
    }
}
