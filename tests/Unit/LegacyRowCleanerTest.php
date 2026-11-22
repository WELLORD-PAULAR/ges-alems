<?php

use App\Support\Legacy\LegacyRowCleaner;

test('text trims values and turns empty strings into null', function (mixed $input, ?string $expected) {
    expect(LegacyRowCleaner::text($input))->toBe($expected);
})->with([
    'null' => [null, null],
    'empty' => ['', null],
    'spaces' => ['   ', null],
    'value' => ['  Baguio City  ', 'Baguio City'],
    'zero string' => ['0', '0'],
]);

test('zero dates become null and real dates are normalized', function (mixed $input, ?string $expected) {
    expect(LegacyRowCleaner::date($input))->toBe($expected);
})->with([
    'null' => [null, null],
    'zero date' => ['0000-00-00', null],
    'zero datetime' => ['0000-00-00 00:00:00', null],
    'iso date' => ['2019-01-26', '2019-01-26'],
    'datetime' => ['2026-06-24 05:57:06', '2026-06-24'],
    'garbage' => ['not-a-date', null],
]);

test('zero datetimes become null and real datetimes parse', function () {
    expect(LegacyRowCleaner::dateTime('0000-00-00 00:00:00'))->toBeNull()
        ->and(LegacyRowCleaner::dateTime('2026-06-24 05:57:06')?->toDateTimeString())
        ->toBe('2026-06-24 05:57:06')
        ->and(LegacyRowCleaner::dateTime(''))->toBeNull();
});

test('mysql set columns become trimmed json arrays', function (mixed $input, array $expected) {
    expect(LegacyRowCleaner::set($input))->toBe($expected);
})->with([
    'null' => [null, []],
    'empty' => ['', []],
    'single' => ['ASTHMA', ['ASTHMA']],
    'multiple' => ['BLENDED (COMBINATION),ONLINE', ['BLENDED (COMBINATION)', 'ONLINE']],
    'spaces' => ['ASTHMA, ANEMIA ', ['ASTHMA', 'ANEMIA']],
]);

test('booleans accept the old tinyint and text values', function (mixed $input, bool $expected) {
    expect(LegacyRowCleaner::boolean($input))->toBe($expected);
})->with([
    'one' => [1, true],
    'zero' => [0, false],
    'string one' => ['1', true],
    'yes' => ['YES', true],
    'no' => ['NO', false],
    'null' => [null, false],
]);

test('names collapse repeated whitespace without changing case', function () {
    expect(LegacyRowCleaner::name('  DEL   PRADO '))->toBe('DEL PRADO')
        ->and(LegacyRowCleaner::name(''))->toBeNull();
});

test('psa numbers drop non-answers and all-zero placeholders', function (mixed $input, ?string $expected) {
    expect(LegacyRowCleaner::psaBcn($input))->toBe($expected);
})->with([
    'empty' => ['', null],
    'na' => ['N/A', null],
    'none' => ['NONE', null],
    'zeros' => ['00000', null],
    'spaced' => ['2018 4140436', '2018 4140436'],
    'dashed' => ['2017-01474', '2017-01474'],
    'lettered' => ['RPB-KT-02-10', 'RPB-KT-02-10'],
]);

test('lrn keeps digits only', function (mixed $input, ?string $expected) {
    expect(LegacyRowCleaner::lrn($input))->toBe($expected);
})->with([
    'empty' => ['', null],
    'digits' => ['136402240059', '136402240059'],
    'spaced' => ['1364 0224 0059', '136402240059'],
    'junk' => ['N/A', null],
]);

test('4ps numbers drop non-answers and strip a leading hash', function (mixed $input, ?string $expected) {
    expect(LegacyRowCleaner::fourPsHouseholdNumber($input))->toBe($expected);
})->with([
    'no' => ['NO', null],
    'na' => ['N/A', null],
    'none' => ['NONE', null],
    'hash' => ['#553', '553'],
    'zeros' => ['00000', null],
    'number' => ['515', '515'],
    'address' => ['504 UPPER GIBRALTAR', '504 UPPER GIBRALTAR'],
]);

test('zip codes become four digit strings and zero becomes null', function (mixed $input, ?string $expected) {
    expect(LegacyRowCleaner::zipCode($input))->toBe($expected);
})->with([
    'zero' => [0, null],
    'empty' => ['', null],
    'four digits' => [2600, '2600'],
    'short' => ['97', '0097'],
]);

test('sex normalizes to the stored enum values', function (mixed $input, ?string $expected) {
    expect(LegacyRowCleaner::sex($input))->toBe($expected);
})->with([
    'male' => ['MALE', 'MALE'],
    'female lowercase' => ['female', 'FEMALE'],
    'blank' => ['', null],
    'unknown' => ['OTHER', null],
]);
