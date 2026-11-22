<?php

use App\Support\Legacy\LegacyDumpLoader;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyTestDatabase;

beforeEach(function () {
    LegacyTestDatabase::configure();
});

afterEach(function () {
    LegacyTestDatabase::cleanup();
});

test('the dump loader parses schemas and inserts quoted values', function () {
    $dump = <<<'SQL'
CREATE TABLE `mother_tongue` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `mother_tongue` (`id`, `name`) VALUES
(1, 'Tagalog'),
(2, 'It''s a ''quoted'' name'),
(3, NULL),
(4, 'A, B; C (D)');

CREATE TABLE `enrollment2` (
  `fk_full_name_bd` varchar(50) NOT NULL,
  `li_learning_modality` set('BLENDED (COMBINATION)','ONLINE') NOT NULL,
  `created-at` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `enrollment2` (`fk_full_name_bd`, `li_learning_modality`, `created-at`) VALUES
('KEY_1', 'BLENDED (COMBINATION),ONLINE', '2026-06-24 05:57:06');
SQL;

    $path = tempnam(sys_get_temp_dir(), 'gems-dump-');

    if ($path === false) {
        $this->fail('Could not create a temporary dump file.');
    }

    file_put_contents($path, $dump);

    try {
        $counts = (new LegacyDumpLoader)->load($path);

        expect($counts)->toBe(['mother_tongue' => 4, 'enrollment2' => 1]);

        $rows = DB::connection('legacy')->table('mother_tongue')->orderBy('id')->get();

        expect($rows)->toHaveCount(4)
            ->and($rows[0]->name)->toBe('Tagalog')
            ->and($rows[1]->name)->toBe("It's a 'quoted' name")
            ->and($rows[2]->name)->toBeNull()
            ->and($rows[3]->name)->toBe('A, B; C (D)')
            ->and($rows[0]->id)->toBe(1);

        $enrollment = DB::connection('legacy')->table('enrollment2')->first();

        expect($enrollment->li_learning_modality)->toBe('BLENDED (COMBINATION),ONLINE')
            ->and($enrollment->{'created-at'})->toBe('2026-06-24 05:57:06');

        // Loading again replaces the tables instead of appending rows.
        $second = (new LegacyDumpLoader)->load($path);

        expect($second)->toBe($counts)
            ->and(DB::connection('legacy')->table('mother_tongue')->count())->toBe(4);
    } finally {
        @unlink($path);
    }
});

test('the dump loader rejects a missing file', function () {
    (new LegacyDumpLoader)->load('missing-dump.sql');
})->throws(RuntimeException::class, 'Legacy dump not found');

test('the dump loader rejects a dump without create table statements', function () {
    $path = tempnam(sys_get_temp_dir(), 'gems-dump-');

    if ($path === false) {
        $this->fail('Could not create a temporary dump file.');
    }

    file_put_contents($path, 'INSERT INTO `nothing` (`id`) VALUES (1);');

    try {
        (new LegacyDumpLoader)->load($path);
    } finally {
        @unlink($path);
    }
})->throws(RuntimeException::class, 'No CREATE TABLE statements');
