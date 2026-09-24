<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreDiaryTaskRequest;
use App\Models\Animal;
use App\Models\DiaryTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DiaryTaskController extends Controller
{
    public function index(): View
    {
        $openTasks = DiaryTask::query()
            ->with(['animal', 'assignee', 'creator'])
            ->whereNull('completed_at')
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->orderByDesc('id')
            ->get();

        $recentlyCompleted = DiaryTask::query()
            ->with(['animal', 'assignee', 'creator'])
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(20)
            ->get();

        return view('diary.index', [
            'openTasks' => $openTasks,
            'recentlyCompleted' => $recentlyCompleted,
        ]);
    }

    public function create(Animal $animal): View
    {
        $this->authorizeManage();

        return view('diary.create', [
            'animal' => $animal,
            'task' => new DiaryTask([
                'due_on' => now()->toDateString(),
                'assigned_to' => request()->user()?->id,
            ]),
            'assignees' => $this->assignees(),
        ]);
    }

    public function store(StoreDiaryTaskRequest $request, Animal $animal): RedirectResponse
    {
        $data = $request->safe()->only(['title', 'body', 'assigned_to', 'due_on']);
        $data['created_by'] = $request->user()->id;
        $data['animal_id'] = $animal->id;

        $animal->diaryTasks()->create($data);

        return redirect()
            ->route('animals.show', $animal)
            ->with('status', 'Diary task created.');
    }

    public function complete(DiaryTask $task): RedirectResponse
    {
        $this->authorizeManage();

        if ($task->completed_at === null) {
            $task->update(['completed_at' => now()]);
        }

        return $this->redirectAfterAction($task, 'Diary task marked complete.');
    }

    public function reopen(DiaryTask $task): RedirectResponse
    {
        $this->authorizeManage();

        if ($task->completed_at !== null) {
            $task->update(['completed_at' => null]);
        }

        return $this->redirectAfterAction($task, 'Diary task reopened.');
    }

    private function redirectAfterAction(DiaryTask $task, string $status): RedirectResponse
    {
        if ($task->animal_id !== null) {
            return redirect()
                ->route('animals.show', $task->animal_id)
                ->with('status', $status);
        }

        return redirect()
            ->route('diary.index')
            ->with('status', $status);
    }

    /**
     * @return Collection<int, User>
     */
    private function assignees()
    {
        return User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Staff->value, UserRole::Volunteer->value])
            ->orderBy('name')
            ->get();
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageDiary(), 403);
    }
}
