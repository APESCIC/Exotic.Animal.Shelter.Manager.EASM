<?php

namespace App\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pure-PHP database dump in row chunks (no mysqldump / shell).
 */
class DatabaseDumper
{
    public function __construct(private int $rowsPerChunk = 200) {}

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

            return array_values(array_map(fn ($row) => (string) $row->name, $rows));
        }

        return array_values(array_filter(
            Schema::getTableListing(),
            fn (string $table) => ! str_starts_with($table, 'sqlite_')
        ));
    }

    /**
     * Write the next dump chunk into $sqlPath.
     *
     * @param  array{table_index: int, row_offset: int}  $state
     * @return array{done: bool, table_index: int, row_offset: int, wrote: int, table: string|null}
     */
    public function dumpChunk(string $sqlPath, array $state): array
    {
        $tables = $this->tables();
        $tableIndex = (int) ($state['table_index'] ?? 0);
        $rowOffset = (int) ($state['row_offset'] ?? 0);

        if ($tables === []) {
            if (! is_file($sqlPath)) {
                file_put_contents($sqlPath, "-- EASM empty database backup\n");
            }

            return [
                'done' => true,
                'table_index' => 0,
                'row_offset' => 0,
                'wrote' => 0,
                'table' => null,
            ];
        }

        if ($tableIndex >= count($tables)) {
            return [
                'done' => true,
                'table_index' => $tableIndex,
                'row_offset' => 0,
                'wrote' => 0,
                'table' => null,
            ];
        }

        $table = $tables[$tableIndex];
        $handle = fopen($sqlPath, 'ab');
        if ($handle === false) {
            throw new BackupException('Could not open the database dump file for writing.');
        }

        try {
            if ($rowOffset === 0) {
                $quote = DB::getDriverName() === 'sqlite' ? '"' : '`';
                $safe = $quote.str_replace($quote, $quote.$quote, $table).$quote;
                fwrite($handle, "\n-- Table: {$table}\n");
                fwrite($handle, "DROP TABLE IF EXISTS {$safe};\n");
                fwrite($handle, $this->createStatement($table)."\n");
            }

            $rows = DB::table($table)->offset($rowOffset)->limit($this->rowsPerChunk)->get();
            $wrote = 0;
            foreach ($rows as $row) {
                $values = get_object_vars($row);
                fwrite($handle, $this->insertStatement($table, $values)."\n");
                $wrote++;
            }
        } finally {
            fclose($handle);
        }

        if ($wrote < $this->rowsPerChunk) {
            return [
                'done' => $tableIndex + 1 >= count($tables),
                'table_index' => $tableIndex + 1,
                'row_offset' => 0,
                'wrote' => $wrote,
                'table' => $table,
            ];
        }

        return [
            'done' => false,
            'table_index' => $tableIndex,
            'row_offset' => $rowOffset + $wrote,
            'wrote' => $wrote,
            'table' => $table,
        ];
    }

    private function createStatement(string $table): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::selectOne('SELECT sql FROM sqlite_master WHERE type = ? AND name = ?', ['table', $table]);
            $sql = is_object($row) ? (string) ($row->sql ?? '') : '';

            return $sql !== '' ? $sql.';' : '-- missing create for '.$table;
        }

        $key = 'Create Table';
        $row = DB::selectOne('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`');
        $sql = is_object($row) ? (string) ($row->{$key} ?? '') : '';

        return $sql !== '' ? $sql.';' : '-- missing create for '.$table;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertStatement(string $table, array $values): string
    {
        $driver = DB::getDriverName();
        $quote = $driver === 'sqlite' ? '"' : '`';
        $columns = [];
        $escaped = [];

        foreach ($values as $column => $value) {
            $columns[] = $quote.str_replace($quote, $quote.$quote, (string) $column).$quote;
            $escaped[] = $this->quoteValue($value);
        }

        return 'INSERT INTO '.$quote.str_replace($quote, $quote.$quote, $table).$quote
            .' ('.implode(', ', $columns).') VALUES ('.implode(', ', $escaped).');';
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;

        return "'".str_replace(['\\', "'"], ['\\\\', "''"], $string)."'";
    }
}
