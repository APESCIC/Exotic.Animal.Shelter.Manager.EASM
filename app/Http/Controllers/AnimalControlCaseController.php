<?php

namespace App\Http\Controllers;

use App\Enums\AnimalControlCaseStatus;
use App\Enums\UserRole;
use App\Http\Requests\CloseAnimalControlCaseRequest;
use App\Http\Requests\DispatchAnimalControlCaseRequest;
use App\Http\Requests\StoreAnimalControlCaseRequest;
use App\Http\Requests\UpdateAnimalControlCaseRequest;
use App\Models\Animal;
use App\Models\AnimalControlCase;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AnimalControlCaseController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $cases = AnimalControlCase::query()
            ->with(['reporter', 'linkedAnimal', 'assignedUser'])
            ->search($q !== '' ? $q : null)
            ->ofStatus($status !== '' ? $status : null)
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('animal-control.index', [
            'cases' => $cases,
            'statuses' => AnimalControlCaseStatus::cases(),
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('animal-control.create', [
            'case' => new AnimalControlCase([
                'reported_at' => now(),
                'status' => AnimalControlCaseStatus::Open,
            ]),
            'people' => Person::query()->orderBy('name')->get(),
            'animals' => Animal::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreAnimalControlCaseRequest $request): RedirectResponse
    {
        $case = AnimalControlCase::query()->create([
            ...$this->validatedCaseData($request),
            'reference' => AnimalControlCase::nextReference(),
            'status' => AnimalControlCaseStatus::Open,
        ]);

        return redirect()
            ->route('animal-control.show', $case)
            ->with('status', 'Animal control case created.');
    }

    public function show(AnimalControlCase $animalControlCase): View
    {
        $animalControlCase->load(['reporter', 'linkedAnimal', 'assignedUser']);

        return view('animal-control.show', [
            'case' => $animalControlCase,
            'dispatchers' => $this->dispatchers(),
        ]);
    }

    public function edit(AnimalControlCase $animalControlCase): View
    {
        $this->authorizeManage();

        return view('animal-control.edit', [
            'case' => $animalControlCase,
            'people' => Person::query()->orderBy('name')->get(),
            'animals' => Animal::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateAnimalControlCaseRequest $request, AnimalControlCase $animalControlCase): RedirectResponse
    {
        $animalControlCase->update($this->validatedCaseData($request));

        return redirect()
            ->route('animal-control.show', $animalControlCase)
            ->with('status', 'Animal control case updated.');
    }

    public function assignDispatch(DispatchAnimalControlCaseRequest $request, AnimalControlCase $animalControlCase): RedirectResponse
    {
        abort_if($animalControlCase->status === AnimalControlCaseStatus::Closed, 422, 'Closed cases cannot be dispatched.');

        $animalControlCase->update([
            'assigned_user_id' => $request->integer('assigned_user_id'),
            'status' => AnimalControlCaseStatus::Dispatched,
            'dispatched_at' => now(),
        ]);

        return redirect()
            ->route('animal-control.show', $animalControlCase)
            ->with('status', 'Case dispatched.');
    }

    public function close(CloseAnimalControlCaseRequest $request, AnimalControlCase $animalControlCase): RedirectResponse
    {
        abort_if($animalControlCase->status === AnimalControlCaseStatus::Closed, 422, 'Case is already closed.');

        $notes = $request->string('close_notes')->toString();

        $animalControlCase->update([
            'status' => AnimalControlCaseStatus::Closed,
            'closed_at' => now(),
            'close_notes' => $notes !== '' ? $notes : null,
        ]);

        return redirect()
            ->route('animal-control.show', $animalControlCase)
            ->with('status', 'Case closed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCaseData(StoreAnimalControlCaseRequest $request): array
    {
        $data = $request->safe()->all();

        foreach (['species', 'notes', 'reporter_person_id', 'linked_animal_id'] as $nullable) {
            if (($data[$nullable] ?? null) === '') {
                $data[$nullable] = null;
            }
        }

        return $data;
    }

    /**
     * @return Collection<int, User>
     */
    private function dispatchers()
    {
        return User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Staff->value])
            ->orderBy('name')
            ->get();
    }

    private function authorizeManage(): void
    {
        abort_unless($this->requestUserCanManage(), 403);
    }

    private function requestUserCanManage(): bool
    {
        $role = request()->user()?->role;

        return $role !== null && $role->canManageAnimalControl();
    }
}
