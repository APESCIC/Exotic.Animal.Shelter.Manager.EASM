<?php

namespace Tests\Feature;

use App\Update\UpdateException;
use App\Update\UpdateStateMachine;
use App\Update\UpdateStep;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UpdateStateMachineTest extends TestCase
{
    private string $root;

    private UpdateStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/update-'.uniqid('', true));
        File::ensureDirectoryExists($this->root);
        config([
            'easm.updates.state_path' => $this->root,
            'easm.updates.lock_stale_seconds' => 1,
        ]);
        $this->app->forgetInstance(UpdateStateMachine::class);
        $this->machine = new UpdateStateMachine($this->root, lockStaleSeconds: 1);
    }

    protected function tearDown(): void
    {
        $this->machine->reset();
        if (is_dir($this->root)) {
            File::deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    public function test_killing_mid_step_and_reloading_resumes_each_step_without_corruption(): void
    {
        $this->machine->start('0.9.0', ['channel' => 'stable']);

        foreach (UpdateStep::sequence() as $step) {
            $resume = $this->machine->resume('worker-a');
            $this->assertSame($step, $resume['step']);
            $this->assertSame(UpdateStateMachine::STATUS_RUNNING, $resume['state']['status']);

            $this->machine->beginStep($step);
            $mid = $this->machine->state();
            $this->assertSame(UpdateStateMachine::STEP_IN_PROGRESS, $mid['steps'][$step->value]['status']);

            // Simulate a killed request: drop the lock without completing the step.
            // (A real PHP timeout frees flock the same way when the worker exits.)
            $this->machine->release();
            $this->assertFileExists($this->root.'/state.json');

            $again = $this->machine->resume('worker-b');
            $this->assertSame($step, $again['step'], 'Resume should retry the incomplete step '.$step->value);
            $this->assertSame(
                UpdateStateMachine::STEP_PENDING,
                $again['state']['steps'][$step->value]['status'],
            );

            foreach (UpdateStep::sequence() as $earlier) {
                if ($earlier === $step) {
                    break;
                }
                $this->assertSame(
                    UpdateStateMachine::STEP_COMPLETED,
                    $again['state']['steps'][$earlier->value]['status'],
                    'Earlier step '.$earlier->value.' must stay completed',
                );
            }

            $this->machine->beginStep($step);
            $this->machine->completeStep($step, ['last_completed' => $step->value]);
            if ($this->machine->lock()->isHeld()) {
                $this->machine->release();
            }
        }

        $final = $this->machine->state();
        $this->assertSame(UpdateStateMachine::STATUS_COMPLETED, $final['status']);
        $this->assertNull($final['current_step']);
        foreach (UpdateStep::sequence() as $step) {
            $this->assertSame(UpdateStateMachine::STEP_COMPLETED, $final['steps'][$step->value]['status']);
        }
        $this->assertSame('maintenance_off', $final['context']['last_completed']);
        $this->assertFileExists($this->root.'/state.json');
        $decoded = json_decode((string) file_get_contents($this->root.'/state.json'), true);
        $this->assertIsArray($decoded);
        $this->assertSame(UpdateStateMachine::STATUS_COMPLETED, $decoded['status']);
    }

    public function test_lock_blocks_a_second_concurrent_holder(): void
    {
        $this->machine->start('0.9.0');
        $this->machine->resume('owner-1');

        $other = new UpdateStateMachine($this->root, lockStaleSeconds: 60);
        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Another update is already running.');
        $other->resume('owner-2');
    }

    public function test_failed_step_can_be_retried_on_resume(): void
    {
        $this->machine->start('0.9.0');
        $this->machine->resume('worker');
        $this->machine->beginStep(UpdateStep::Preflight);
        $this->machine->failStep(UpdateStep::Preflight, 'disk full');

        $state = $this->machine->state();
        $this->assertSame(UpdateStateMachine::STATUS_FAILED, $state['status']);
        $this->assertSame('disk full', $state['error']);

        $resume = $this->machine->resume('worker-retry');
        $this->assertSame(UpdateStep::Preflight, $resume['step']);
        $this->assertSame(UpdateStateMachine::STATUS_RUNNING, $resume['state']['status']);

        $this->machine->beginStep(UpdateStep::Preflight);
        $this->machine->completeStep(UpdateStep::Preflight);
        $this->assertSame(UpdateStateMachine::STEP_COMPLETED, $this->machine->state()['steps']['preflight']['status']);
        $this->machine->release();
    }

    public function test_state_json_is_written_atomically_and_lists_every_step(): void
    {
        $state = $this->machine->start('0.9.0');
        $this->assertSame(count(UpdateStep::sequence()), count($state['steps']));
        foreach (UpdateStep::sequence() as $step) {
            $this->assertArrayHasKey($step->value, $state['steps']);
        }

        $path = $this->root.'/state.json';
        $this->assertFileExists($path);
        $this->assertDoesNotMatchRegularExpression('/\.tmp$/', $path);
        $this->assertEmpty(glob($this->root.'/*.tmp') ?: []);
    }
}
