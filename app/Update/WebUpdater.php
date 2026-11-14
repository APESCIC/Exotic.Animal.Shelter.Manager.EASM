<?php

namespace App\Update;

use App\Backup\BackupService;
use App\Install\ChunkedMigrator;
use App\Support\AppVersion;
use Easm\Package\ChunkedDownloader;
use Easm\Package\ReleaseVerifier;
use Easm\Package\SafeZipExtractor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Runs one updater AJAX step against the state machine.
 */
class WebUpdater
{
    public const TIME_BUDGET_SECONDS = 20.0;

    public function __construct(
        private UpdateStateMachine $machine,
        private BackupService $backups,
        private ChunkedMigrator $migrator,
        private ReleaseSwapper $swapper = new ReleaseSwapper,
        private ?UpdateRollback $rollback = null,
        private ?ChunkedDownloader $downloader = null,
        private ?SafeZipExtractor $extractor = null,
        private ?ReleaseVerifier $verifier = null,
        private ?string $appRoot = null,
        private ?string $workRoot = null,
        private ?string $publicKeyPath = null,
    ) {
        $this->appRoot ??= base_path();
        $this->workRoot ??= (string) config('easm.updates.state_path', storage_path('app/update'));
        $this->publicKeyPath ??= base_path('resources/keys/easm-release.minisign.pub');
        $this->downloader ??= new ChunkedDownloader(chunkBytes: 512_000, maxBytes: 104_857_600, timeoutSeconds: 20);
        $this->extractor ??= new SafeZipExtractor(backend: SafeZipExtractor::BACKEND_AUTO);
        $this->verifier ??= new ReleaseVerifier($this->publicKeyPath);
        $this->rollback ??= new UpdateRollback(
            backups: $this->backups,
            swapper: $this->swapper,
            appRoot: $this->appRoot,
            workRoot: $this->workRoot,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function start(string $targetVersion, array $options = []): array
    {
        $context = array_merge([
            'package_url' => $options['package_url'] ?? null,
            'signature_url' => $options['signature_url'] ?? null,
            'sha256' => $options['sha256'] ?? null,
            'package_path' => $options['package_path'] ?? null,
            'signature_path' => $options['signature_path'] ?? null,
            'target_version' => $targetVersion,
            'from_version' => AppVersion::current($this->appRoot),
        ], $options['context'] ?? []);

        return $this->machine->start($targetVersion, $context);
    }

    /**
     * Run the next incomplete step (or continue a chunked one). Returns JSON-ready payload.
     *
     * @return array<string, mixed>
     */
    public function runNextStep(string $owner = 'web'): array
    {
        $started = microtime(true);
        $resume = $this->machine->resume($owner);
        $step = $resume['step'];

        if ($step === null) {
            return [
                'done' => true,
                'step' => null,
                'state' => $resume['state'],
                'message' => 'Update complete.',
            ];
        }

        try {
            $this->machine->beginStep($step);
            $context = is_array($resume['state']['context'] ?? null) ? $resume['state']['context'] : [];
            $this->maybeSimulateFailure($step, $context);
            $result = $this->execute($step, $context, $started);

            if ($result['step_done']) {
                $state = $this->machine->completeStep($step, $result['context']);
                if ($this->machine->lock()->isHeld()) {
                    $this->machine->release();
                }

                return [
                    'done' => ($state['status'] ?? null) === UpdateStateMachine::STATUS_COMPLETED,
                    'step' => $step->value,
                    'step_done' => true,
                    'state' => $state,
                    'message' => $result['message'],
                ];
            }

            // Chunked step still running — leave in_progress, save context, release lock.
            $state = $this->machine->mergeContext($result['context']);
            $this->machine->release();

            return [
                'done' => false,
                'step' => $step->value,
                'step_done' => false,
                'state' => $state,
                'message' => $result['message'],
            ];
        } catch (Throwable $e) {
            $context = is_array($resume['state']['context'] ?? null) ? $resume['state']['context'] : [];
            // Prefer context from a partial step write when present.
            $latest = $this->machine->state();
            if (is_array($latest['context'] ?? null)) {
                $context = array_merge($context, $latest['context']);
            }

            $rollback = $this->rollback->run($context, $e->getMessage());
            $aftermath = [
                'rolled_back' => (bool) ($rollback['ok'] ?? false),
                'rollback' => $rollback,
            ];

            if ($this->machine->lock()->isHeld()) {
                try {
                    $this->machine->failStep($step, $e->getMessage(), $aftermath);
                } catch (Throwable) {
                    $this->machine->release();
                }
            }

            return [
                'done' => false,
                'step' => $step->value,
                'step_done' => false,
                'error' => $e->getMessage(),
                'rolled_back' => (bool) ($rollback['ok'] ?? false),
                'rollback' => $rollback,
                'state' => $this->machine->state(),
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->machine->state();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function execute(UpdateStep $step, array $context, float $started): array
    {
        return match ($step) {
            UpdateStep::Preflight => $this->preflight($context),
            UpdateStep::Backup => $this->backup($context, $started),
            UpdateStep::MaintenanceOn => $this->maintenanceOn($context),
            UpdateStep::Download => $this->download($context, $started),
            UpdateStep::Verify => $this->verify($context),
            UpdateStep::Extract => $this->extract($context, $started),
            UpdateStep::Swap => $this->swap($context),
            UpdateStep::Migrate => $this->migrate($context, $started),
            UpdateStep::ClearCaches => $this->clearCaches($context),
            UpdateStep::Health => $this->health($context),
            UpdateStep::MaintenanceOff => $this->maintenanceOff($context),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function preflight(array $context): array
    {
        foreach (['storage/app', 'storage/framework', 'bootstrap/cache'] as $relative) {
            $path = $this->appRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! is_dir($path) || ! is_writable($path)) {
                throw new UpdateException('Preflight failed: '.$relative.' is not writable.');
            }
        }

        if (! extension_loaded('sodium')) {
            throw new UpdateException('Preflight failed: sodium extension is required.');
        }

        $context['preflight_ok'] = true;

        return ['step_done' => true, 'context' => $context, 'message' => 'Preflight passed.'];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function backup(array $context, float $started): array
    {
        if (empty($context['backup_id'])) {
            $startedBackup = $this->backups->start();
            $context['backup_id'] = $startedBackup['id'];
        }

        $dump = ['done' => false, 'table' => null];
        do {
            $dump = $this->backups->dumpStep((string) $context['backup_id']);
            if ($dump['done']) {
                break;
            }
        } while ($this->withinBudget($started));

        if (! $dump['done']) {
            return [
                'step_done' => false,
                'context' => $context,
                'message' => 'Backup dump in progress ('.($dump['table'] ?? 'table').').',
            ];
        }

        $this->backups->archiveFiles((string) $context['backup_id']);
        $this->backups->finalize((string) $context['backup_id']);

        return ['step_done' => true, 'context' => $context, 'message' => 'Backup '.$context['backup_id'].' ready.'];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function maintenanceOn(array $context): array
    {
        Artisan::call('down', [
            '--retry' => 60,
            '--secret' => (string) ($context['maintenance_secret'] ?? 'easm-update'),
        ]);
        $context['maintenance'] = true;

        return ['step_done' => true, 'context' => $context, 'message' => 'Maintenance mode on.'];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function download(array $context, float $started): array
    {
        if (! empty($context['package_path']) && is_file((string) $context['package_path']) && empty($context['package_url'])) {
            return ['step_done' => true, 'context' => $context, 'message' => 'Using uploaded package.'];
        }

        $url = (string) ($context['package_url'] ?? '');
        if ($url === '') {
            throw new UpdateException('No package URL or uploaded package path was provided.');
        }

        $dest = (string) ($context['package_path'] ?? ($this->workRoot.'/packages/release.zip'));
        File::ensureDirectoryExists(dirname($dest));

        do {
            $result = $this->downloader->downloadChunk($url, $dest);
            $context['package_path'] = $result['path'];
            $context['download_bytes'] = $result['bytes'];
            if ($result['done']) {
                return ['step_done' => true, 'context' => $context, 'message' => 'Package downloaded.'];
            }
        } while ($this->withinBudget($started));

        return [
            'step_done' => false,
            'context' => $context,
            'message' => 'Downloading package ('.$result['bytes'].' bytes so far).',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function verify(array $context): array
    {
        $package = (string) ($context['package_path'] ?? '');
        if ($package === '' || ! is_file($package)) {
            throw new UpdateException('Package file is missing for verification.');
        }

        $signature = (string) ($context['signature_path'] ?? '');
        if ($signature === '' && ! empty($context['signature_url'])) {
            $signature = $this->workRoot.'/packages/release.zip.minisig';
            File::ensureDirectoryExists(dirname($signature));
            $response = Http::timeout(20)->get((string) $context['signature_url']);
            if (! $response->successful()) {
                throw new UpdateException('Could not download the package signature.');
            }
            file_put_contents($signature, $response->body());
            $context['signature_path'] = $signature;
        }

        $sha = (string) ($context['sha256'] ?? '');
        if ($sha === '') {
            throw new UpdateException('SHA-256 checksum is required to verify the package.');
        }
        if ($signature === '' || ! is_file($signature)) {
            throw new UpdateException('Minisign signature file is required to verify the package.');
        }

        $this->verifier->verify($package, $sha, $signature);
        $context['verified'] = true;

        return ['step_done' => true, 'context' => $context, 'message' => 'Package verified.'];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function extract(array $context, float $started): array
    {
        $package = (string) ($context['package_path'] ?? '');
        if ($package === '' || ! is_file($package)) {
            throw new UpdateException('Package file is missing for extraction.');
        }

        $staging = (string) ($context['staging_path'] ?? ($this->workRoot.'/staging/'.($context['target_version'] ?? 'next')));
        File::ensureDirectoryExists($staging);
        $context['staging_path'] = $staging;

        $index = (int) ($context['extract_index'] ?? 0);
        do {
            $result = $this->extractor->extractChunk($package, $staging, $index, 50);
            $index = $result['next_index'];
            $context['extract_index'] = $index;
            $context['extract_backend'] = $result['backend'];
            if ($result['done']) {
                $context['extracted'] = true;

                return [
                    'step_done' => true,
                    'context' => $context,
                    'message' => 'Package extracted ('.$result['backend'].').',
                ];
            }
        } while ($this->withinBudget($started));

        return [
            'step_done' => false,
            'context' => $context,
            'message' => 'Extracting package (entry '.$index.').',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function swap(array $context): array
    {
        $staging = (string) ($context['staging_path'] ?? '');
        if ($staging === '' || ! is_dir($staging)) {
            throw new UpdateException('Staging directory is missing for swap.');
        }

        // Packages may nest as easm-X.Y.Z/… — swap from the directory that holds VERSION/composer.json.
        $source = $this->resolvePackageRoot($staging);
        $context['previous_path'] = $this->rollback->snapshotLiveCode();
        $this->swapper->swap($source, $this->appRoot);
        $context['swapped'] = true;

        return ['step_done' => true, 'context' => $context, 'message' => 'Release swapped into place.'];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function maybeSimulateFailure(UpdateStep $step, array $context): void
    {
        $target = $context['fail_at_step'] ?? null;
        if (! is_string($target) || $target === '') {
            return;
        }

        if ($target === $step->value) {
            throw new UpdateException('Simulated failure at '.$step->value.'.');
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function migrate(array $context, float $started): array
    {
        do {
            $result = $this->migrator->runStep();
            if ($result['done']) {
                $context['migrated'] = true;

                return ['step_done' => true, 'context' => $context, 'message' => 'Migrations finished.'];
            }
        } while ($this->withinBudget($started));

        return [
            'step_done' => false,
            'context' => $context,
            'message' => 'Migrating ('.$result['pending'].' pending).',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function clearCaches(array $context): array
    {
        Artisan::call('optimize:clear');
        $context['caches_cleared'] = true;

        return ['step_done' => true, 'context' => $context, 'message' => 'Caches cleared.'];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function health(array $context): array
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            throw new UpdateException('Health check failed: database unreachable.');
        }

        $version = AppVersion::fromFile($this->appRoot);
        $context['health_version'] = $version;

        return ['step_done' => true, 'context' => $context, 'message' => 'Health OK ('.$version.').'];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{step_done: bool, context: array<string, mixed>, message: string}
     */
    private function maintenanceOff(array $context): array
    {
        Artisan::call('up');
        $context['maintenance'] = false;

        return ['step_done' => true, 'context' => $context, 'message' => 'Maintenance mode off.'];
    }

    private function withinBudget(float $started): bool
    {
        return (microtime(true) - $started) < self::TIME_BUDGET_SECONDS;
    }

    private function resolvePackageRoot(string $staging): string
    {
        if (is_file($staging.DIRECTORY_SEPARATOR.'VERSION') || is_file($staging.DIRECTORY_SEPARATOR.'composer.json')) {
            return $staging;
        }

        $children = array_values(array_filter(glob($staging.DIRECTORY_SEPARATOR.'*') ?: [], 'is_dir'));
        if (count($children) === 1) {
            return $children[0];
        }

        return $staging;
    }
}
