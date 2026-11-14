<?php

namespace App\Install;

use Illuminate\Support\Facades\Artisan;

/**
 * Runs database migrations one file at a time so shared hosts with a 30s
 * max_execution_time can finish over several AJAX requests.
 */
class ChunkedMigrator
{
    /**
     * @return array{done: bool, pending: int, ran: int, output: string}
     */
    public function runStep(): array
    {
        $before = $this->pendingCount();
        if ($before === 0) {
            return [
                'done' => true,
                'pending' => 0,
                'ran' => 0,
                'output' => 'Nothing to migrate.',
            ];
        }

        $exitCode = Artisan::call('migrate', [
            '--force' => true,
            '--step' => true,
        ]);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new \RuntimeException($output !== '' ? $output : 'Database migration step failed.');
        }

        $after = $this->pendingCount();

        return [
            'done' => $after === 0,
            'pending' => $after,
            'ran' => max(0, $before - $after),
            'output' => $output,
        ];
    }

    public function pendingCount(): int
    {
        $migrator = app('migrator');
        $paths = [$this->migrationsPath()];
        $files = $migrator->getMigrationFiles($paths);

        if ($files === []) {
            return 0;
        }

        if (! $migrator->repositoryExists()) {
            return count($files);
        }

        $ran = $migrator->getRepository()->getRan();

        return count(array_diff(array_keys($files), $ran));
    }

    private function migrationsPath(): string
    {
        return database_path('migrations');
    }
}
