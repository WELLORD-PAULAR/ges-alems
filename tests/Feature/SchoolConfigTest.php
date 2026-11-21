<?php

test('school config exposes the current school year', function () {
    expect(config('school.current_school_year'))->toBe('2026-2027');
});

test('school config offers last year through two years ahead', function () {
    expect(config('school.school_years'))->toBe([
        '2025-2026',
        '2026-2027',
        '2027-2028',
        '2028-2029',
    ]);
});

test('school config exposes three assignable terms plus summer', function () {
    $terms = config('school.terms');

    expect($terms)->toHaveCount(4)
        ->and($terms[1]['label'])->toBe('1st Term (June - August)')
        ->and($terms[2]['label'])->toBe('2nd Term (September - November)')
        ->and($terms[3]['label'])->toBe('3rd Term (December - April)')
        ->and($terms[4]['label'])->toBe('Summer')
        ->and($terms[4]['assignable'])->toBeFalse();
});
