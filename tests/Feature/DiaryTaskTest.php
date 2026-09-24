<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\DiaryTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_assign_and_complete_task_from_animal(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Dana Staff']);
        $assignee = User::factory()->staff()->create(['name' => 'Sam Assignee']);
        $animal = Animal::factory()->create(['name' => 'Ziggy', 'species' => 'Corn snake']);

        $this->actingAs($staff)
            ->post(route('animals.diary.store', $animal), [
                'title' => 'Check heat mat',
                'body' => 'Confirm night temp',
                'assigned_to' => $assignee->id,
                'due_on' => '2026-09-25',
            ])
            ->assertRedirect(route('animals.show', $animal));

        $task = DiaryTask::query()->where('animal_id', $animal->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('Check heat mat', $task->title);
        $this->assertSame($assignee->id, $task->assigned_to);
        $this->assertSame($staff->id, $task->created_by);
        $this->assertNull($task->completed_at);

        $this->actingAs($staff)
            ->post(route('diary.complete', $task))
            ->assertRedirect(route('animals.show', $animal));

        $task->refresh();
        $this->assertNotNull($task->completed_at);

        $this->actingAs($staff)
            ->get(route('animals.show', $animal))
            ->assertOk()
            ->assertSee('Diary / tasks', false)
            ->assertSee('Check heat mat', false)
            ->assertSee('Sam Assignee', false);
    }

    public function test_staff_can_complete_from_inbox_and_view_lists(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Ruby']);
        $task = DiaryTask::factory()->create([
            'animal_id' => $animal->id,
            'assigned_to' => $staff->id,
            'created_by' => $staff->id,
            'title' => 'Weigh Ruby',
            'due_on' => '2026-09-26',
            'completed_at' => null,
        ]);

        $this->actingAs($staff)
            ->get(route('diary.index'))
            ->assertOk()
            ->assertSee('Weigh Ruby', false)
            ->assertSee('Ruby', false)
            ->assertSee('26/09/2026', false);

        $this->actingAs($staff)
            ->post(route('diary.complete', $task))
            ->assertRedirect(route('animals.show', $animal));

        $this->actingAs($staff)
            ->get(route('diary.index'))
            ->assertOk()
            ->assertSee('Weigh Ruby', false)
            ->assertSee('Recently completed', false);
    }

    public function test_readonly_and_volunteer_cannot_mutate_diary(): void
    {
        $readonly = User::factory()->readonly()->create();
        $volunteer = User::factory()->volunteer()->create();
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Tess']);
        $task = DiaryTask::factory()->create([
            'animal_id' => $animal->id,
            'assigned_to' => $staff->id,
            'created_by' => $staff->id,
            'title' => 'Visible task',
        ]);

        $this->actingAs($readonly)
            ->get(route('animals.diary.create', $animal))
            ->assertForbidden();

        $this->actingAs($readonly)
            ->post(route('animals.diary.store', $animal), [
                'title' => 'Blocked',
                'assigned_to' => $staff->id,
            ])
            ->assertForbidden();

        $this->actingAs($volunteer)
            ->post(route('diary.complete', $task))
            ->assertForbidden();

        $this->actingAs($readonly)
            ->get(route('animals.show', $animal))
            ->assertOk()
            ->assertSee('Visible task', false)
            ->assertDontSee('Add diary task', false);

        $this->actingAs($readonly)
            ->get(route('diary.index'))
            ->assertOk()
            ->assertSee('Visible task', false)
            ->assertDontSee('>Complete<', false);
    }
}
