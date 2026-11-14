<?php

namespace App\Backup;

use Illuminate\Support\Facades\DB;

/**
 * Pure-PHP restore from an EASM SQL dump, statement by statement.
 */
class DatabaseRestorer
{
    public function __construct(private int $statementsPerChunk = 100) {}

    /**
     * @param  array{offset: int}  $state
     * @return array{done: bool, offset: int, ran: int}
     */
    public function restoreChunk(string $sqlPath, array $state): array
    {
        if (! is_file($sqlPath)) {
            throw new BackupException('Database dump file is missing.');
        }

        $offset = (int) ($state['offset'] ?? 0);
        $contents = (string) file_get_contents($sqlPath);
        $statements = $this->splitStatements($contents);

        if ($offset === 0) {
            $this->disableForeignKeys();
        }

        $ran = 0;
        $index = $offset;

        try {
            while ($ran < $this->statementsPerChunk && $index < count($statements)) {
                $sql = $this->stripLeadingComments(trim($statements[$index]));
                $index++;
                if ($sql === '') {
                    continue;
                }
                DB::unprepared($sql);
                $ran++;
            }
        } finally {
            if ($index >= count($statements)) {
                $this->enableForeignKeys();
            }
        }

        return [
            'done' => $index >= count($statements),
            'offset' => $index,
            'ran' => $ran,
        ];
    }

    /**
     * Split on semicolons outside single-quoted string literals ('' escapes).
     *
     * @return list<string>
     */
    private function splitStatements(string $contents): array
    {
        $out = [];
        $buffer = '';
        $inSingle = false;
        $length = strlen($contents);

        for ($i = 0; $i < $length; $i++) {
            $char = $contents[$i];

            if ($inSingle) {
                $buffer .= $char;
                if ($char === "'" && $i + 1 < $length && $contents[$i + 1] === "'") {
                    $buffer .= $contents[++$i];
                } elseif ($char === "'") {
                    $inSingle = false;
                }

                continue;
            }

            if ($char === "'") {
                $inSingle = true;
                $buffer .= $char;

                continue;
            }

            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $out[] = $trimmed;
                }
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $out[] = $trimmed;
        }

        return $out;
    }

    private function stripLeadingComments(string $sql): string
    {
        while ($sql !== '' && str_starts_with($sql, '--')) {
            $newline = strpos($sql, "\n");
            if ($newline === false) {
                return '';
            }
            $sql = ltrim(substr($sql, $newline + 1));
        }

        return $sql;
    }

    private function disableForeignKeys(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } elseif ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }
    }

    private function enableForeignKeys(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } elseif ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
