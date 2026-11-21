<?php

use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Enums\Term;
use App\Enums\VerificationStatus;

test('verification status stores the seven legacy values', function () {
    $values = array_map(
        fn (VerificationStatus $status): string => $status->value,
        VerificationStatus::cases(),
    );

    expect($values)->toBe([
        'PROCESSING',
        'VERIFIED',
        'REJECTED',
        'WITHDRAWN',
        'TRANSFERRED_IN',
        'TRANSFERRED_OUT',
        'DROPPED',
    ]);
});

test('every verification status round-trips from its stored value', function () {
    foreach (VerificationStatus::cases() as $status) {
        expect(VerificationStatus::from($status->value))->toBe($status);
    }
});

test('blank and pending statuses normalize to processing', function (?string $value) {
    expect(VerificationStatus::normalize($value))->toBe(VerificationStatus::Processing);
})->with([
    'null' => [null],
    'empty' => [''],
    'spaces' => ['   '],
    'pending' => ['PENDING'],
    'lowercase pending' => ['pending'],
]);

test('unknown statuses fall back to processing instead of throwing', function () {
    expect(VerificationStatus::normalize('SOMETHING_ELSE'))
        ->toBe(VerificationStatus::Processing);
});

test('only processing counts as pending', function () {
    expect(VerificationStatus::Processing->isPending())->toBeTrue()
        ->and(VerificationStatus::Verified->isPending())->toBeFalse();
});

test('grade levels normalize every legacy spelling', function (string $legacy, GradeLevel $expected) {
    expect(GradeLevel::fromLegacy($legacy))->toBe($expected);
})->with([
    'K' => ['K', GradeLevel::Kindergarten],
    'Kindergart' => ['Kindergart', GradeLevel::Kindergarten],
    'KINDERGARTEN' => ['KINDERGARTEN', GradeLevel::Kindergarten],
    '1' => ['1', GradeLevel::Grade1],
    'GRADE 1' => ['GRADE 1', GradeLevel::Grade1],
    'grade 6' => ['grade 6', GradeLevel::Grade6],
    'GRADE6' => ['GRADE6', GradeLevel::Grade6],
]);

test('grade levels reject unknown values and blank strings', function () {
    expect(GradeLevel::fromLegacy('Grade 7'))->toBeNull()
        ->and(GradeLevel::fromLegacy(''))->toBeNull()
        ->and(GradeLevel::fromLegacy(null))->toBeNull();
});

test('kindergarten sorts before grade one', function () {
    expect(GradeLevel::Kindergarten->sortOrder())->toBeLessThan(GradeLevel::Grade1->sortOrder())
        ->and(GradeLevel::Grade6->sortOrder())->toBe(6);
});

test('terms one to three are assignable but summer is not', function () {
    expect(array_map(fn (Term $term): int => $term->value, Term::assignable()))
        ->toBe([1, 2, 3])
        ->and(Term::Summer->isAssignable())->toBeFalse();
});

test('only admin and teacher roles exist', function () {
    $values = array_map(fn (Role $role): string => $role->value, Role::cases());

    expect($values)->toBe(['admin', 'teacher']);
});
