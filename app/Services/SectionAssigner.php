<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * The one place that decides whether a student may be put in a section.
 *
 * Both the single and the bulk assignment go through here, so they cannot
 * disagree. A section must match the enrollment's grade level and school year.
 * A null section removes the student from their section.
 *
 * @phpstan-type Result array{enrollment_id: int, ok: bool, message: string|null}
 */
class SectionAssigner
{
    /**
     * @return Result
     */
    public function assign(Enrollment $enrollment, ?int $sectionId): array
    {
        $section = $sectionId === null ? null : Section::query()->find($sectionId);

        return $this->apply($enrollment->id, $enrollment, $sectionId, $section);
    }

    /**
     * Applies every valid row and reports one result per row, in the order
     * given. A bad row never stops the good ones, and is never skipped
     * silently (the old app did both).
     *
     * @param  list<array{enrollment_id: int, section_id: int|null}>  $rows
     * @return list<Result>
     */
    public function assignMany(array $rows): array
    {
        $enrollmentIds = array_column($rows, 'enrollment_id');
        $sectionIds = array_filter(array_column($rows, 'section_id'), fn (?int $id): bool => $id !== null);

        $enrollments = Enrollment::query()->whereIn('id', $enrollmentIds)->get()->keyBy('id');
        $sections = Section::query()->whereIn('id', $sectionIds)->get()->keyBy('id');

        return DB::transaction(function () use ($rows, $enrollments, $sections): array {
            $results = [];

            foreach ($rows as $row) {
                $sectionId = $row['section_id'];

                $results[] = $this->apply(
                    $row['enrollment_id'],
                    $enrollments->get($row['enrollment_id']),
                    $sectionId,
                    $sectionId === null ? null : $sections->get($sectionId),
                );
            }

            return $results;
        });
    }

    /**
     * @return Result
     */
    private function apply(int $enrollmentId, ?Enrollment $enrollment, ?int $sectionId, ?Section $section): array
    {
        if ($enrollment === null) {
            return $this->result($enrollmentId, 'That student enrollment was not found.');
        }

        if ($sectionId !== null && $section === null) {
            return $this->result($enrollmentId, 'That section was not found.');
        }

        if ($section !== null && ! $section->accepts($enrollment)) {
            return $this->result($enrollmentId, $this->mismatchMessage($section, $enrollment));
        }

        $enrollment->update(['section_id' => $section?->id]);

        return $this->result($enrollmentId);
    }

    private function mismatchMessage(Section $section, Enrollment $enrollment): string
    {
        if ($section->grade_level !== $enrollment->grade_level) {
            return sprintf(
                '%s is a %s section, but this student is in %s.',
                $section->name,
                $section->grade_level->label(),
                $enrollment->grade_level->label(),
            );
        }

        return sprintf(
            '%s belongs to school year %s, but this enrollment is for %s.',
            $section->name,
            $section->school_year,
            $enrollment->school_year,
        );
    }

    /**
     * @return Result
     */
    private function result(int $enrollmentId, ?string $failure = null): array
    {
        return [
            'enrollment_id' => $enrollmentId,
            'ok' => $failure === null,
            'message' => $failure,
        ];
    }
}
