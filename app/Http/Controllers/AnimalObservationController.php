<?php

namespace App\Http\Controllers;

use App\Enums\AnimalTab;
use App\Http\Requests\StoreAnimalObservationRequest;
use App\Http\Requests\UpdateAnimalObservationRequest;
use App\Models\Animal;
use App\Models\AnimalObservation;
use App\Models\ObservationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnimalObservationController extends Controller
{
    public function create(Animal $animal): View
    {
        $this->authorizeRecord();

        $types = ObservationType::activeForSpecies($animal->species);

        return view('observations.create', [
            'animal' => $animal,
            'observation' => new AnimalObservation([
                'observed_on' => now()->toDateString(),
            ]),
            'types' => $types,
        ]);
    }

    public function store(StoreAnimalObservationRequest $request, Animal $animal): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()?->id;

        if (! isset($data['observation_type_id'])) {
            $data['observation_type_id'] = null;
        }

        $animal->observations()->create($data);

        return redirect()
            ->to(AnimalTab::Log->url($animal))
            ->with('status', 'Observation recorded.');
    }

    public function edit(Animal $animal, AnimalObservation $observation): View
    {
        $this->authorizeModify($observation);
        abort_unless($observation->animal_id === $animal->id, 404);

        $types = ObservationType::activeForSpecies($animal->species);

        return view('observations.edit', [
            'animal' => $animal,
            'observation' => $observation,
            'types' => $types,
        ]);
    }

    public function update(UpdateAnimalObservationRequest $request, Animal $animal, AnimalObservation $observation): RedirectResponse
    {
        abort_unless($observation->animal_id === $animal->id, 404);

        $observation->update($request->validated());

        return redirect()
            ->to(AnimalTab::Log->url($animal))
            ->with('status', 'Observation updated.');
    }

    public function destroy(Animal $animal, AnimalObservation $observation): RedirectResponse
    {
        $this->authorizeModify($observation);
        abort_unless($observation->animal_id === $animal->id, 404);

        $observation->delete();

        return redirect()
            ->to(AnimalTab::Log->url($animal))
            ->with('status', 'Observation deleted.');
    }

    private function authorizeRecord(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canRecordObservations(), 403);
    }

    private function authorizeModify(AnimalObservation $observation): void
    {
        $this->authorizeRecord();
        abort_unless($observation->canBeModifiedBy(request()->user()), 403);
    }
}
