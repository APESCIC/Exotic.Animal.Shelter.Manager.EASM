<?php

namespace App\Http\Controllers;

use App\Enums\AnimalTab;
use App\Http\Requests\StoreAnimalObservationRequest;
use App\Models\Animal;
use App\Models\AnimalObservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnimalObservationController extends Controller
{
    public function create(Animal $animal): View
    {
        $this->authorizeManage();

        return view('observations.create', [
            'animal' => $animal,
            'observation' => new AnimalObservation([
                'observed_on' => now()->toDateString(),
            ]),
        ]);
    }

    public function store(StoreAnimalObservationRequest $request, Animal $animal): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()?->id;

        $animal->observations()->create($data);

        return redirect()
            ->to(AnimalTab::Log->url($animal))
            ->with('status', 'Observation recorded.');
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageMedical(), 403);
    }
}
