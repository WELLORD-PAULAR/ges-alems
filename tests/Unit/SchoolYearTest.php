<?php

use App\Support\SchoolYear;

test('school year normalizes every dirty spelling from the old database', function (?string $input, ?string $expected) {
    expect(SchoolYear::normalize($input))->toBe($expected);
})->with([
    'canonical' => ['2026-2027', '2026-2027'],
    'spaced underscore' => ['2026 _2027', '2026-2027'],
    'truncated' => ['2026 - 202', '2026-2027'],
    'underscore' => ['2025_2026', '2025-2026'],
    'space only' => ['2026 2027', '2026-2027'],
    'trailing space' => ['2026 2027 ', '2026-2027'],
    'single year' => ['2026', '2026-2027'],
    'prefixed' => ['SY 2026-2027', '2026-2027'],
    'blank' => ['', null],
    'null' => [null, null],
    'garbage' => ['not a year', null],
]);

test('school year validity only accepts the canonical format', function () {
    expect(SchoolYear::isValid('2026-2027'))->toBeTrue()
        ->and(SchoolYear::isValid('2026 _2027'))->toBeFalse()
        ->and(SchoolYear::isValid(null))->toBeFalse();
});
