<?php

namespace App\Support\Legacy;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads a MySQL dump of the old AMS database into the `legacy` connection.
 *
 * The dump cannot be replayed directly on SQLite (`ENUM`, `SET`, `ENGINE`), so
 * this loader reads the nine `CREATE TABLE` blocks and every `INSERT` row,
 * then writes plain tables to the configured connection. It is deliberately
 * limited to the data needed by the import; indexes and foreign keys from the
 * dump are not recreated.
 */
final class LegacyDumpLoader
{
    /**
     * @return array<string, int> rows loaded per table
     */
    public function load(string $path, string $connection = 'legacy'): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Legacy dump not found: {$path}");
        }

        $sql = file_get_contents($path);

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException("Legacy dump is empty or unreadable: {$path}");
        }

        $schemas = $this->parseSchemas($sql);
        $rows = $this->parseRows($sql);

        if ($schemas === []) {
            throw new RuntimeException('No CREATE TABLE statements found in the dump.');
        }

        $this->ensureDatabaseExists($connection);

        $db = DB::connection($connection);
        $driver = $db->getDriverName();
        $quote = $driver === 'mysql' || $driver === 'mariadb' ? '`' : '"';

        if ($driver === 'sqlite') {
            $db->statement('PRAGMA foreign_keys = OFF');
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $db->statement('SET FOREIGN_KEY_CHECKS=0');
        }

        foreach (array_reverse(array_keys($schemas)) as $table) {
            $db->statement("DROP TABLE IF EXISTS {$quote}{$table}{$quote}");
        }

        foreach ($schemas as $table => $columns) {
            $definitions = [];

            foreach ($columns as $name => $type) {
                $definitions[] = "{$quote}{$name}{$quote} {$type}";
            }

            $db->statement("CREATE TABLE {$quote}{$table}{$quote} (".implode(', ', $definitions).')');
        }

        $counts = [];

        foreach ($rows as $table => $tableRows) {
            $columns = array_keys($schemas[$table] ?? []);

            if ($columns === []) {
                $columns = array_keys($tableRows[0] ?? []);
            }

            $chunkSize = max(1, intdiv(900, max(1, count($columns))));

            foreach (array_chunk($tableRows, $chunkSize) as $chunk) {
                $db->table($table)->insert($chunk);
            }

            $counts[$table] = count($tableRows);
        }

        return $counts;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function parseSchemas(string $sql): array
    {
        $schemas = [];

        $matched = preg_match_all(
            '/CREATE TABLE\s+`?([A-Za-z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=/s',
            $sql,
            $matches,
            PREG_SET_ORDER,
        );

        if ($matched === false) {
            throw new RuntimeException('Could not parse CREATE TABLE statements.');
        }

        foreach ($matches as $match) {
            $table = $match[1];
            $body = $match[2];
            $columns = [];

            foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
                $line = trim($line);

                if (! str_starts_with($line, '`')) {
                    continue;
                }

                if (preg_match('/^`([^`]+)`\s+([A-Za-z]+)/', $line, $column) !== 1) {
                    continue;
                }

                $columns[$column[1]] = $this->sqliteType($column[2]);
            }

            if ($columns === []) {
                throw new RuntimeException("Could not read columns for table {$table}.");
            }

            $schemas[$table] = $columns;
        }

        return $schemas;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function parseRows(string $sql): array
    {
        $rows = [];
        $offset = 0;
        $length = strlen($sql);

        while (($position = stripos($sql, 'INSERT INTO', $offset)) !== false) {
            if (preg_match(
                '/\GINSERT INTO\s+`?([A-Za-z0-9_]+)`?\s*\(([^)]*)\)\s*VALUES\s*/i',
                $sql,
                $match,
                0,
                $position,
            ) !== 1) {
                $offset = $position + 1;

                continue;
            }

            $table = $match[1];
            $columns = array_map(
                fn (string $column): string => trim($column, " `\t\n\r\0\x0B"),
                explode(',', $match[2]),
            );

            $valuesStart = $position + strlen($match[0]);
            $valuesEnd = $this->findStatementEnd($sql, $valuesStart);
            $values = substr($sql, $valuesStart, $valuesEnd - $valuesStart);

            foreach ($this->splitRows($values) as $index => $row) {
                $fields = $this->splitFields($row);

                if (count($fields) !== count($columns)) {
                    throw new RuntimeException(sprintf(
                        'Column mismatch in %s row %d: expected %d, got %d.',
                        $table,
                        $index + 1,
                        count($columns),
                        count($fields),
                    ));
                }

                $rows[$table][] = array_combine(
                    $columns,
                    array_map(fn (string $field): mixed => $this->parseValue($field), $fields),
                );
            }

            $offset = $valuesEnd + 1;
        }

        if ($offset === 0 && $length > 0 && $rows === []) {
            throw new RuntimeException('No INSERT statements found in the dump.');
        }

        return $rows;
    }

    /**
     * Find the semicolon that ends a VALUES clause, ignoring quoted text.
     */
    private function findStatementEnd(string $sql, int $start): int
    {
        $length = strlen($sql);
        $inString = false;
        $depth = 0;

        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                if ($char === '\\') {
                    $i++;

                    continue;
                }

                if ($char === "'") {
                    if ($i + 1 < $length && $sql[$i + 1] === "'") {
                        $i++;

                        continue;
                    }

                    $inString = false;
                }

                continue;
            }

            if ($char === "'") {
                $inString = true;

                continue;
            }

            if ($char === '(') {
                $depth++;

                continue;
            }

            if ($char === ')') {
                $depth--;

                continue;
            }

            if ($char === ';' && $depth === 0) {
                return $i;
            }
        }

        return $length;
    }

    /**
     * Split `(1, 'a'), (2, 'b')` into its parenthesised row bodies.
     *
     * @return list<string>
     */
    private function splitRows(string $values): array
    {
        $rows = [];
        $length = strlen($values);
        $i = 0;

        while ($i < $length) {
            while ($i < $length && (ctype_space($values[$i]) || $values[$i] === ',')) {
                $i++;
            }

            if ($i >= $length) {
                break;
            }

            if ($values[$i] !== '(') {
                $i++;

                continue;
            }

            $start = $i + 1;
            $depth = 1;
            $inString = false;
            $i++;

            while ($i < $length && $depth > 0) {
                $char = $values[$i];

                if ($inString) {
                    if ($char === '\\') {
                        $i += 2;

                        continue;
                    }

                    if ($char === "'") {
                        if ($i + 1 < $length && $values[$i + 1] === "'") {
                            $i += 2;

                            continue;
                        }

                        $inString = false;
                    }

                    $i++;

                    continue;
                }

                if ($char === "'") {
                    $inString = true;
                } elseif ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }

                $i++;
            }

            $rows[] = substr($values, $start, $i - $start - 1);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function splitFields(string $row): array
    {
        $fields = [];
        $current = '';
        $length = strlen($row);
        $inString = false;
        $i = 0;

        while ($i < $length) {
            $char = $row[$i];

            if ($inString) {
                $current .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $row[$i + 1];
                    $i += 2;

                    continue;
                }

                if ($char === "'") {
                    if ($i + 1 < $length && $row[$i + 1] === "'") {
                        $current .= "'";
                        $i += 2;

                        continue;
                    }

                    $inString = false;
                }

                $i++;

                continue;
            }

            if ($char === "'") {
                $inString = true;
                $current .= $char;
                $i++;

                continue;
            }

            if ($char === ',') {
                $fields[] = $current;
                $current = '';
                $i++;

                continue;
            }

            $current .= $char;
            $i++;
        }

        $fields[] = $current;

        return $fields;
    }

    private function parseValue(string $field): mixed
    {
        $value = trim($field);

        if ($value === '' || strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        if ($value[0] === "'") {
            $inner = substr($value, 1, -1);
            $inner = str_replace("''", "'", $inner);

            return str_replace(["\\'", '\\\\'], ["'", '\\'], $inner);
        }

        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (preg_match('/^-?\d+\.\d+$/', $value) === 1) {
            return (float) $value;
        }

        return $value;
    }

    private function sqliteType(string $mysqlType): string
    {
        $type = strtolower($mysqlType);

        if (in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'integer'], true)) {
            return 'integer';
        }

        if (in_array($type, ['decimal', 'numeric', 'float', 'double', 'real'], true)) {
            return 'real';
        }

        return 'text';
    }

    private function ensureDatabaseExists(string $connection): void
    {
        $config = config("database.connections.{$connection}");

        if (! is_array($config) || ($config['driver'] ?? null) !== 'sqlite') {
            return;
        }

        $database = $config['database'] ?? null;

        if (! is_string($database) || $database === ':memory:') {
            return;
        }

        $directory = dirname($database);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (! file_exists($database)) {
            touch($database);
        }
    }
}
