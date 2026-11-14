<?php

namespace App\Update;

/**
 * Persisted updater session: resume after timeouts or closed tabs without corruption.
 */
class UpdateStateMachine
{
    public const STATUS_IDLE = 'idle';

    public const STATUS_RUNNING = 'running';

    public const STATUS_FAILED = 'failed';

    public const STATUS_COMPLETED = 'completed';

    public const STEP_PENDING = 'pending';

    public const STEP_IN_PROGRESS = 'in_progress';

    public const STEP_COMPLETED = 'completed';

    public const STEP_FAILED = 'failed';

    private UpdateLock $lock;

    private UpdateStateStore $store;

    public function __construct(?string $root = null, int $lockStaleSeconds = 120)
    {
        $root ??= (string) config('easm.updates.state_path', storage_path('app/update'));
        $this->store = new UpdateStateStore($root.DIRECTORY_SEPARATOR.'state.json');
        $this->lock = new UpdateLock($root.DIRECTORY_SEPARATOR.'update.lock', $lockStaleSeconds);
    }

    public function store(): UpdateStateStore
    {
        return $this->store;
    }

    public function lock(): UpdateLock
    {
        return $this->lock;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return $this->store->read() ?? $this->emptyState();
    }

    /**
     * Start a new update session (refuses if another is running with a held lock).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function start(?string $targetVersion = null, array $context = []): array
    {
        $existing = $this->store->read();
        if (is_array($existing) && ($existing['status'] ?? null) === self::STATUS_RUNNING) {
            // Allow restart only when the lock is stale / free.
            try {
                $this->lock->acquire('probe-'.bin2hex(random_bytes(2)));
                $this->lock->release();
            } catch (UpdateException $e) {
                throw new UpdateException('An update is already in progress.', 0, $e);
            }
        }

        $steps = [];
        foreach (UpdateStep::sequence() as $step) {
            $steps[$step->value] = [
                'status' => self::STEP_PENDING,
                'started_at' => null,
                'finished_at' => null,
                'error' => null,
            ];
        }

        $state = [
            'id' => now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)),
            'status' => self::STATUS_RUNNING,
            'target_version' => $targetVersion,
            'current_step' => UpdateStep::Preflight->value,
            'steps' => $steps,
            'context' => $context,
            'error' => null,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
        ];

        $this->store->write($state);

        return $state;
    }

    /**
     * Acquire the lock and return the next step to run (reclaims killed in-progress steps).
     *
     * @return array{state: array<string, mixed>, step: UpdateStep|null, owner: string}
     */
    public function resume(string $owner): array
    {
        $this->lock->acquire($owner);

        $state = $this->store->read();
        if ($state === null || ($state['status'] ?? null) === self::STATUS_IDLE) {
            $this->lock->release();
            throw new UpdateException('No update session to resume. Call start() first.');
        }

        if (($state['status'] ?? null) === self::STATUS_COMPLETED) {
            $this->lock->release();

            return ['state' => $state, 'step' => null, 'owner' => $owner];
        }

        $state = $this->reclaimInProgress($state);
        $step = $this->nextPendingStep($state);

        if ($step === null) {
            $state['status'] = self::STATUS_COMPLETED;
            $state['current_step'] = null;
            $state['finished_at'] = now()->toIso8601String();
            $state['error'] = null;
            $this->store->write($state);
            $this->lock->release();

            return ['state' => $state, 'step' => null, 'owner' => $owner];
        }

        $state['status'] = self::STATUS_RUNNING;
        $state['current_step'] = $step->value;
        $state['error'] = null;
        $this->store->write($state);

        return ['state' => $state, 'step' => $step, 'owner' => $owner];
    }

    /**
     * Mark a step as in progress (must hold the lock).
     *
     * @return array<string, mixed>
     */
    public function beginStep(UpdateStep $step): array
    {
        $this->assertLocked();
        $state = $this->requireRunningState();
        $expected = $this->nextPendingStep($state);

        if ($expected === null || $expected !== $step) {
            throw new UpdateException(
                'Cannot begin step '.$step->value.'; expected '.($expected?->value ?? 'none').'.'
            );
        }

        $state['steps'][$step->value] = [
            'status' => self::STEP_IN_PROGRESS,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'error' => null,
        ];
        $state['current_step'] = $step->value;
        $state['status'] = self::STATUS_RUNNING;
        $this->store->write($state);

        return $state;
    }

    /**
     * Mark the current in-progress step completed.
     *
     * @param  array<string, mixed>  $contextMerge
     * @return array<string, mixed>
     */
    public function completeStep(UpdateStep $step, array $contextMerge = []): array
    {
        $this->assertLocked();
        $state = $this->requireRunningState();
        $record = $state['steps'][$step->value] ?? null;

        if (! is_array($record) || ($record['status'] ?? null) !== self::STEP_IN_PROGRESS) {
            throw new UpdateException('Step '.$step->value.' is not in progress.');
        }

        $state['steps'][$step->value] = [
            'status' => self::STEP_COMPLETED,
            'started_at' => $record['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'error' => null,
        ];
        if ($contextMerge !== []) {
            $state['context'] = array_merge(is_array($state['context'] ?? null) ? $state['context'] : [], $contextMerge);
        }

        $next = $step->next();
        $state['current_step'] = $next?->value;

        if ($next === null) {
            $state['status'] = self::STATUS_COMPLETED;
            $state['finished_at'] = now()->toIso8601String();
            $this->store->write($state);
            $this->lock->release();

            return $state;
        }

        $this->store->write($state);

        return $state;
    }

    /**
     * Mark a step failed; keeps the session so resume can retry the same step.
     *
     * @param  array<string, mixed>  $contextMerge
     * @return array<string, mixed>
     */
    public function failStep(UpdateStep $step, string $message, array $contextMerge = []): array
    {
        $this->assertLocked();
        $state = $this->requireRunningState();
        $record = $state['steps'][$step->value] ?? [];

        $state['steps'][$step->value] = [
            'status' => self::STEP_FAILED,
            'started_at' => is_array($record) ? ($record['started_at'] ?? now()->toIso8601String()) : now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'error' => $message,
        ];
        $state['status'] = self::STATUS_FAILED;
        $state['current_step'] = $step->value;
        $state['error'] = $message;
        if ($contextMerge !== []) {
            $state['context'] = array_merge(is_array($state['context'] ?? null) ? $state['context'] : [], $contextMerge);
        }
        $this->store->write($state);
        $this->lock->release();

        return $state;
    }

    /**
     * After failStep, reset the failed step to pending so resume retries it.
     *
     * @return array<string, mixed>
     */
    public function clearFailure(): array
    {
        $state = $this->requireState();
        if (($state['status'] ?? null) !== self::STATUS_FAILED) {
            return $state;
        }

        $stepValue = $state['current_step'] ?? null;
        if (is_string($stepValue) && isset($state['steps'][$stepValue])) {
            $state['steps'][$stepValue] = [
                'status' => self::STEP_PENDING,
                'started_at' => null,
                'finished_at' => null,
                'error' => null,
            ];
        }
        $state['status'] = self::STATUS_RUNNING;
        $state['error'] = null;
        $this->store->write($state);

        return $state;
    }

    /**
     * Persist context without changing step status (for chunked download/extract).
     *
     * @param  array<string, mixed>  $contextMerge
     * @return array<string, mixed>
     */
    public function mergeContext(array $contextMerge): array
    {
        $this->assertLocked();
        $state = $this->requireState();
        $state['context'] = array_merge(is_array($state['context'] ?? null) ? $state['context'] : [], $contextMerge);
        $this->store->write($state);

        return $state;
    }

    public function release(): void
    {
        $this->lock->release();
    }

    public function reset(): void
    {
        if ($this->lock->isHeld()) {
            $this->lock->release();
        }
        $this->store->clear();
        if (is_file($this->lock->path())) {
            @unlink($this->lock->path());
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function reclaimInProgress(array $state): array
    {
        $changed = false;
        foreach ($state['steps'] ?? [] as $name => $record) {
            if (! is_array($record)) {
                continue;
            }
            if (($record['status'] ?? null) === self::STEP_IN_PROGRESS) {
                $state['steps'][$name] = [
                    'status' => self::STEP_PENDING,
                    'started_at' => null,
                    'finished_at' => null,
                    'error' => null,
                ];
                $state['current_step'] = $name;
                $changed = true;
            }
            if (($record['status'] ?? null) === self::STEP_FAILED) {
                $state['steps'][$name] = [
                    'status' => self::STEP_PENDING,
                    'started_at' => null,
                    'finished_at' => null,
                    'error' => null,
                ];
                $state['current_step'] = $name;
                $state['status'] = self::STATUS_RUNNING;
                $state['error'] = null;
                $changed = true;
            }
        }

        if ($changed) {
            $this->store->write($state);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function nextPendingStep(array $state): ?UpdateStep
    {
        foreach (UpdateStep::sequence() as $step) {
            $status = $state['steps'][$step->value]['status'] ?? self::STEP_PENDING;
            if ($status !== self::STEP_COMPLETED) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireRunningState(): array
    {
        $state = $this->requireState();
        if (! in_array($state['status'] ?? null, [self::STATUS_RUNNING, self::STATUS_FAILED], true)) {
            throw new UpdateException('Update session is not running.');
        }

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireState(): array
    {
        $state = $this->store->read();
        if ($state === null) {
            throw new UpdateException('Update state.json is missing.');
        }

        return $state;
    }

    private function assertLocked(): void
    {
        if (! $this->lock->isHeld()) {
            throw new UpdateException('Update lock is not held. Call resume() first.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyState(): array
    {
        return [
            'id' => null,
            'status' => self::STATUS_IDLE,
            'target_version' => null,
            'current_step' => null,
            'steps' => [],
            'context' => [],
            'error' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
