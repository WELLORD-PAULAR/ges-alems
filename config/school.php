<?php

use App\Enums\Term;

/*
|--------------------------------------------------------------------------
| School configuration
|--------------------------------------------------------------------------
|
| Mirrors the constants in the old `ams/config/config.php`. Change the school
| year in `.env` (SCHOOL_YEAR) instead of editing code.
|
*/

$currentSchoolYear = (string) env('SCHOOL_YEAR', '2026-2027');
$startYear = (int) substr($currentSchoolYear, 0, 4);

$terms = [];

foreach (Term::cases() as $term) {
    $terms[$term->value] = [
        'label' => $term->label(),
        'short' => $term->shortLabel(),
        'months' => $term->months(),
        'assignable' => $term->isAssignable(),
    ];
}

return [
    'name' => env('SCHOOL_NAME', 'GEMS-AMES'),

    'current_school_year' => $currentSchoolYear,

    /*
     * The old section dropdown offered last year through two years ahead.
     */
    'school_years' => array_map(
        fn (int $year): string => $year.'-'.($year + 1),
        range($startYear - 1, $startYear + 2),
    ),

    'terms' => $terms,

    /*
     * Section names from the old `SectionAssignmentHandler::SECTIONS` constant.
     * The importer seeds these for every school year found in the legacy data.
     */
    'sections' => [
        'K' => ['K-Obedience', 'K-Kindness', 'K-Joy'],
        '1' => ['1-Care', '1-Love', '1-Hope'],
        '2' => ['2-Integrity', '2-Patience', '2-Unity'],
        '3' => ['3-Peace', '3-Faith'],
        '4' => ['4-Charity', '4-Generosity'],
        '5' => ['5-Loyalty', '5-Honesty'],
        '6' => ['6-Humility', '6-Purity'],
    ],
];
